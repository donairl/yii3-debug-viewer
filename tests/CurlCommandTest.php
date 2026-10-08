<?php

declare(strict_types=1);

namespace Dxn\DebugViewer\Tests;

use Dxn\DebugViewer\CurlCommand;
use Dxn\DebugViewer\DumpView;
use PHPUnit\Framework\TestCase;

final class CurlCommandTest extends TestCase
{
    /**
     * Runs the command in a real shell with a stand-in `curl` that reports its
     * arguments, so quoting is checked by the shell and not by string matching.
     *
     * @return list<string>
     */
    private static function argv(string $command): array
    {
        $script = "curl() { for a in \"\$@\"; do printf '%s\\0' \"\$a\"; done; }\n" . $command;
        $proc = proc_open(['sh', '-c', $script], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
        self::assertIsResource($proc);
        $out = stream_get_contents($pipes[1]);
        $err = stream_get_contents($pipes[2]);
        proc_close($proc);
        self::assertSame('', $err, 'the shell must not complain about the command');

        return explode("\0", rtrim($out, "\0"));
    }

    public function testSimpleGet(): void
    {
        $r = CurlCommand::build('GET', 'http://app.test/orders?id=3', ['User-Agent' => ['Agent/1.0'], 'Accept' => ['text/html']]);

        self::assertSame("curl 'http://app.test/orders?id=3' \\\n  -H 'User-Agent: Agent/1.0' \\\n  -H 'Accept: text/html'", $r['command']);
        self::assertSame([], $r['notes']);
        self::assertSame(['http://app.test/orders?id=3', '-H', 'User-Agent: Agent/1.0', '-H', 'Accept: text/html'], self::argv($r['command']));
    }

    public function testMethodIsOnlySpelledOutWhenCurlWouldNotInferIt(): void
    {
        self::assertStringNotContainsString('-X', CurlCommand::build('GET', 'http://a/', [])['command']);
        self::assertStringNotContainsString('-X', CurlCommand::build('POST', 'http://a/', [], 'x=1')['command']);
        self::assertStringContainsString("-X 'POST'", CurlCommand::build('POST', 'http://a/', [])['command'], 'a POST without a body');
        self::assertStringContainsString("-X 'DELETE'", CurlCommand::build('DELETE', 'http://a/x', [])['command']);
        self::assertStringContainsString("-X 'PUT'", CurlCommand::build('put', 'http://a/x', [], '{}')['command']);
        self::assertStringContainsString("-X 'GET'", CurlCommand::build('GET', 'http://a/', [], 'odd')['command'], 'a GET with a body');
        self::assertStringContainsString('--head', CurlCommand::build('HEAD', 'http://a/', [])['command']);
        self::assertStringNotContainsString('-X', CurlCommand::build('HEAD', 'http://a/', [])['command']);
    }

    public function testHeadersCurlManagesAreDropped(): void
    {
        $r = CurlCommand::build('POST', 'http://app.test/x', [
            'Host' => ['app.test'], 'Content-Length' => ['3'], 'Connection' => ['keep-alive'],
            'Accept-Encoding' => ['gzip, deflate'], 'Content-Type' => ['text/plain'],
        ], 'abc');

        self::assertSame(['http://app.test/x', '--compressed', '-H', 'Content-Type: text/plain', '--data-raw', 'abc'], self::argv($r['command']));
    }

    public function testAHostHeaderThatDiffersFromTheUrlIsKept(): void
    {
        $r = CurlCommand::build('GET', 'http://127.0.0.1:8080/', ['Host' => ['shop.example']]);

        self::assertStringContainsString("-H 'Host: shop.example'", $r['command']);
    }

    public function testQuotingSurvivesAHostileBody(): void
    {
        $body = "it's \"quoted\" \$(touch /tmp/dxn-pwned) `id` \\n\nline two; rm -rf /";
        $r = CurlCommand::build('POST', "http://a/?q=it's", ['X-Odd' => ["a'b\$c"]], $body, redact: false);

        self::assertSame(["http://a/?q=it's", '-H', "X-Odd: a'b\$c", '--data-raw', $body], self::argv($r['command']));
        self::assertFileDoesNotExist('/tmp/dxn-pwned');
    }

    public function testCredentialsAreMaskedByDefaultKeepingNamesAndSchemes(): void
    {
        $r = CurlCommand::build('GET', 'http://a/', [
            'Authorization' => ['Bearer eyJhbGciOi.secret.sig'],
            'Cookie' => ['SESSID=abc123; theme=dark'],
            'X-Api-Key' => ['k-12345'],
            'X-CSRF-Token' => ['t0ken'],
            'Accept' => ['text/html'],
        ]);

        self::assertSame(
            ['http://a/', '-H', 'Authorization: Bearer [REDACTED]', '-H', 'Cookie: SESSID=[REDACTED]; theme=[REDACTED]',
                '-H', 'X-Api-Key: [REDACTED]', '-H', 'X-CSRF-Token: [REDACTED]', '-H', 'Accept: text/html'],
            self::argv($r['command']),
        );
        self::assertStringNotContainsString('abc123', $r['command']);
        self::assertStringNotContainsString('eyJhbGciOi', $r['command']);
        self::assertStringNotContainsString('k-12345', $r['command']);
    }

    public function testCredentialsCanBeIncluded(): void
    {
        $r = CurlCommand::build('GET', 'http://a/?token=abc', ['Authorization' => ['Bearer xyz'], 'Cookie' => ['S=1']], redact: false);

        self::assertStringContainsString('Authorization: Bearer xyz', $r['command']);
        self::assertStringContainsString('Cookie: S=1', $r['command']);
        self::assertStringContainsString('token=abc', $r['command']);
    }

    public function testQueryStringSecretsAreMasked(): void
    {
        $r = CurlCommand::build('GET', 'http://a/p?page=2&api_key=SECRET&access%5Ftoken=T#frag', []);

        self::assertSame(['http://a/p?page=2&api_key=%5BREDACTED%5D&access%5Ftoken=%5BREDACTED%5D#frag'], self::argv($r['command']));
    }

    public function testFormBodySecretsAreMasked(): void
    {
        $r = CurlCommand::build('POST', 'http://a/login', ['Content-Type' => ['application/x-www-form-urlencoded']], 'user=ann&password=hunter2&remember=1&_csrf=abc');

        self::assertSame('user=ann&password=%5BREDACTED%5D&remember=1&_csrf=%5BREDACTED%5D', array_slice(self::argv($r['command']), -1)[0]);
    }

    public function testJsonBodySecretsAreMaskedWithoutReformatting(): void
    {
        $body = "{\n  \"email\": \"a@b.c\",\n  \"password\" :  \"p\\\"w\",\n  \"nested\": {\"apiToken\": \"zzz\", \"age\": 5},\n  \"note\": \"my password is fine\"\n}";
        $r = CurlCommand::build('POST', 'http://a/', ['Content-Type' => ['application/json; charset=utf-8']], $body);

        $sent = array_slice(self::argv($r['command']), -1)[0];
        self::assertSame(
            "{\n  \"email\": \"a@b.c\",\n  \"password\" :  \"[REDACTED]\",\n  \"nested\": {\"apiToken\": \"[REDACTED]\", \"age\": 5},\n  \"note\": \"my password is fine\"\n}",
            $sent,
        );
        self::assertJson($sent);
    }

    public function testBodiesOfOtherTypesAreLeftAloneAndMultipartIsFlagged(): void
    {
        $plain = CurlCommand::build('POST', 'http://a/', ['Content-Type' => ['text/plain']], 'password=keep');
        self::assertStringContainsString('password=keep', $plain['command']);

        $multi = CurlCommand::build('POST', 'http://a/', ['Content-Type' => ['multipart/form-data; boundary=x']], "--x\r\nbody\r\n--x--");
        self::assertCount(1, $multi['notes']);
        self::assertStringContainsString('Multipart', $multi['notes'][0]);
        self::assertSame([], CurlCommand::build('POST', 'http://a/', ['Content-Type' => ['multipart/form-data; boundary=x']], 'x', redact: false)['notes']);
    }

    public function testBinaryBodyIsLeftOutWithANote(): void
    {
        $r = CurlCommand::build('POST', 'http://a/', [], "\xff\xfe\x00\x01");

        self::assertStringNotContainsString('--data-raw', $r['command']);
        self::assertSame(['The request body is binary (4 bytes) and is not included.'], $r['notes']);
    }

    public function testMissingHostFallsBackWithANote(): void
    {
        $r = CurlCommand::build('GET', null, []);

        self::assertStringStartsWith("curl 'http://localhost/'", $r['command']);
        self::assertCount(1, $r['notes']);
    }

    public function testBuiltFromADump(): void
    {
        $raw = "POST /login?next=%2F HTTP/1.1\r\nHost: 127.0.0.1:8080\r\nContent-Type: application/x-www-form-urlencoded\r\nCookie: S=1\r\n\r\nuser=ann&password=pw";
        $view = new DumpView([DumpView::REQUEST => [
            'requestUrl' => 'http://127.0.0.1:8080/login?next=%2F', 'requestMethod' => 'POST', 'requestRaw' => $raw,
        ]]);

        $r = CurlCommand::fromView($view);

        self::assertSame(
            ['http://127.0.0.1:8080/login?next=%2F', '-H', 'Content-Type: application/x-www-form-urlencoded', '-H', 'Cookie: S=[REDACTED]', '--data-raw', 'user=ann&password=%5BREDACTED%5D'],
            self::argv($r['command']),
        );
    }

    public function testUrlIsRebuiltFromTheHostHeaderWhenNotRecorded(): void
    {
        $view = new DumpView([DumpView::REQUEST => ['requestRaw' => "GET /a/b?c=1 HTTP/1.1\r\nHost: shop.test\r\n\r\n"]]);

        self::assertSame(['http://shop.test/a/b?c=1'], self::argv(CurlCommand::fromView($view)['command']));
    }

    public function testNothingToBuildWithoutARequest(): void
    {
        self::assertNull(CurlCommand::fromView(new DumpView([])));
    }
}
