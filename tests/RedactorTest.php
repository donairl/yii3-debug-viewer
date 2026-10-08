<?php

declare(strict_types=1);

namespace Dxn\DebugViewer\Tests;

use Dxn\DebugViewer\DumpView;
use Dxn\DebugViewer\Redactor;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class RedactorTest extends TestCase
{
    private const M = '[REDACTED]';

    #[DataProvider('keys')]
    public function testSensitiveKeyNames(string $key, bool $expected): void
    {
        self::assertSame($expected, (new Redactor())->isSensitiveKey($key), $key);
    }

    public static function keys(): iterable
    {
        foreach (['password', 'Password', 'user_password', 'passwordConfirm', 'passwd', 'pwd', 'token', 'accessToken', 'access_token',
            'api_key', 'apiKey', 'X-Api-Key', 'X-CSRF-Token', '_csrf', 'Authorization', 'Proxy-Authorization', 'Cookie', 'Set-Cookie',
            'secret', 'client_secret', 'tokens', 'private_key', 'MOBSCO_SESSID', ':password', ':apiToken'] as $key) {
            yield "sensitive $key" => [$key, true];
        }
        foreach (['compass', 'bypass', 'passenger', 'passes', 'passed', 'tokenizer', 'username', 'email', 'path', 'Content-Type', 'User-Agent', 'author', 'keyboard', 'id', ''] as $key) {
            yield "plain $key" => [$key, false];
        }
    }

    public function testExtraWordsExtendTheList(): void
    {
        $r = new Redactor(['ssn', 'cardNumber']);

        self::assertTrue($r->isSensitiveKey('ssn'));
        self::assertTrue($r->isSensitiveKey('user_ssn'));
        self::assertTrue($r->isSensitiveKey('card_number'));
        self::assertFalse((new Redactor())->isSensitiveKey('ssn'));
    }

    public function testHeaders(): void
    {
        $r = new Redactor();

        self::assertSame('Bearer ' . self::M, $r->header('Authorization', 'Bearer eyJ.abc.def'));
        self::assertSame('Basic ' . self::M, $r->header('authorization', 'Basic dXNlcjpwdw=='));
        self::assertSame(self::M, $r->header('Authorization', 'rawtoken'));
        self::assertSame('SID=' . self::M . '; theme=' . self::M, $r->header('Cookie', 'SID=abc; theme=dark'));
        self::assertSame('SID=' . self::M . '; Path=/; HttpOnly', $r->header('Set-Cookie', 'SID=abc; Path=/; HttpOnly'));
        self::assertSame(self::M, $r->header('X-Api-Key', 'k-123'));
        self::assertSame('text/html', $r->header('Accept', 'text/html'));
        self::assertSame('', $r->header('Cookie', ''));
    }

    public function testUrlAndQuery(): void
    {
        $r = new Redactor();

        self::assertSame('http://a/p?page=2&api_key=%5BREDACTED%5D&access%5Ftoken=%5BREDACTED%5D#f', $r->url('http://a/p?page=2&api_key=S&access%5Ftoken=T#f'));
        self::assertSame('http://a/p?token=', $r->url('http://a/p?token='), 'an empty value hides nothing');
        self::assertSame('a=1&token=%5BREDACTED%5D', $r->query('a=1&token=x'));
        self::assertSame('', $r->query(''));
    }

    public function testBodies(): void
    {
        $r = new Redactor();

        self::assertSame(
            'user=ann&password=%5BREDACTED%5D&_csrf=%5BREDACTED%5D',
            $r->body('application/x-www-form-urlencoded; charset=UTF-8', 'user=ann&password=hunter2&_csrf=abc'),
        );

        $json = "{\n  \"email\": \"a@b.c\",\n  \"password\" :  \"p\\\"w\",\n  \"pin\": 1234,\n  \"ok\": true,\n  \"secret\": \"\",\n  \"nested\": {\"apiToken\": \"zzz\"},\n  \"note\": \"my password is fine\"\n}";
        $masked = $r->body('application/json', $json);
        self::assertSame(
            "{\n  \"email\": \"a@b.c\",\n  \"password\" :  \"[REDACTED]\",\n  \"pin\": 1234,\n  \"ok\": true,\n  \"secret\": \"\",\n  \"nested\": {\"apiToken\": \"[REDACTED]\"},\n  \"note\": \"my password is fine\"\n}",
            $masked,
        );
        self::assertJson($masked);

        self::assertSame('password=keep', $r->body('text/plain', 'password=keep'));
    }

    public function testRawHttpRequest(): void
    {
        $raw = "POST /login?token=abc&x=1 HTTP/1.1\r\nHost: a.test\r\nAuthorization: Bearer abc.def.ghi\r\nCookie: S=1\r\n"
            . "Content-Type: application/x-www-form-urlencoded\r\n\r\nuser=ann&password=pw";

        $r = new Redactor();
        $masked = $r->http($raw);

        self::assertSame(
            "POST /login?token=%5BREDACTED%5D&x=1 HTTP/1.1\r\nHost: a.test\r\nAuthorization: Bearer [REDACTED]\r\nCookie: S=[REDACTED]\r\n"
            . "Content-Type: application/x-www-form-urlencoded\r\n\r\nuser=ann&password=%5BREDACTED%5D",
            $masked,
        );
    }

    public function testRawHttpResponseKeepsHtmlAndMasksSetCookieAndJson(): void
    {
        $r = new Redactor();

        $html = "HTTP/1.1 200 OK\r\nSet-Cookie: SID=zzz; Path=/\r\nContent-Type: text/html\r\n\r\n<input name=\"password\" value=\"\"><p>token=visible-in-html</p>";
        self::assertSame(
            "HTTP/1.1 200 OK\r\nSet-Cookie: SID=[REDACTED]; Path=/\r\nContent-Type: text/html\r\n\r\n<input name=\"password\" value=\"\"><p>token=visible-in-html</p>",
            $r->http($html),
        );

        $json = "HTTP/1.1 200 OK\nContent-Type: application/json\n\n{\"access_token\":\"abc\",\"user\":\"ann\"}";
        self::assertSame("HTTP/1.1 200 OK\nContent-Type: application/json\n\n{\"access_token\":\"[REDACTED]\",\"user\":\"ann\"}", $r->http($json));

        self::assertSame('', $r->http(''));
        self::assertSame("GET / HTTP/1.1\r\nCookie: A=[REDACTED]", $r->http("GET / HTTP/1.1\r\nCookie: A=1"), 'headers only, no body');
    }

    public function testSqlParamsNamedLikeSecrets(): void
    {
        [$sql, $params] = (new Redactor())->sql("SELECT * FROM u WHERE email = 'a@b.c' AND token = 'abcd1234'", [':email' => 'a@b.c', ':token' => 'abcd1234']);

        self::assertSame("SELECT * FROM u WHERE email = 'a@b.c' AND token = '" . self::M . "'", $sql);
        self::assertSame([':email' => 'a@b.c', ':token' => self::M], $params);
    }

    public function testSqlParamsBoundToASensitiveColumn(): void
    {
        $r = new Redactor();
        $params = [':qp0' => 'ann', ':qp1' => "hun'ter2"];
        [$sql, $masked] = $r->sql("SELECT * FROM u WHERE name = 'ann' AND u.`password` = 'hun''ter2'", $params);

        // values are masked by the column-literal rule even though the param names say nothing
        self::assertStringNotContainsString('hun', $sql);
        self::assertStringContainsString("name = 'ann'", $sql);

        [$sql, $masked] = $r->sql('SELECT * FROM u WHERE name = :qp0 AND password = :qp1', $params);
        self::assertSame($params[':qp0'], $masked[':qp0']);
        self::assertSame(self::M, $masked[':qp1']);
        self::assertSame('SELECT * FROM u WHERE name = :qp0 AND password = :qp1', $sql, 'placeholders stay');
    }

    public function testSqlLiteralRuleOnlyTouchesSensitiveColumns(): void
    {
        [$sql] = (new Redactor())->sql("UPDATE u SET secret = 's3cr3t', title = 'my token', note = 'password' WHERE id = 7 AND passenger = 'x'", []);

        self::assertSame("UPDATE u SET secret = '" . self::M . "', title = 'my token', note = 'password' WHERE id = 7 AND passenger = 'x'", $sql);
    }

    public function testAWholeDump(): void
    {
        $r = new Redactor();
        $data = [
            DumpView::DB => ['queries' => [
                'h' => ['position' => 0, 'sql' => 'SELECT * FROM u WHERE password = :qp0', 'rawSql' => "SELECT * FROM u WHERE password = 'hunter22'",
                    'params' => [':qp0' => 'hunter22'], 'line' => '/a.php:1', 'status' => 'success', 'actions' => [['time' => 1.5]]],
            ]],
            DumpView::LOG => [
                ['level' => 'info', 'message' => 'Login with password=hunter22 and Bearer abcdefgh12345 ok', 'context' => ['user' => 'ann', 'password' => 'hunter22', 'response' => '{"token":"t-1","id":3}']],
            ],
            DumpView::REQUEST => [
                'requestUrl' => 'http://a/p?token=x', 'requestQuery' => 'token=x', 'requestPath' => '/p', 'requestMethod' => 'GET',
                'requestRaw' => "GET /p?token=x HTTP/1.1\r\nCookie: S=1\r\n\r\n", 'responseRaw' => "HTTP/1.1 200 OK\r\n\r\nok",
            ],
            DumpView::EXCEPTION => [
                ['class' => 'E', 'message' => 'bad token: abc123', 'trace' => [['file' => '/a.php', 'line' => 3, 'function' => 'login', 'args' => ['ann', 'hunter22']]], 'traceAsString' => '#0 x'],
            ],
            DumpView::SERVICE => [['service' => 'X', 'arguments' => ['config'], 'status' => 'success']],
        ];

        [$out, $count] = $r->dump($data);
        $json = json_encode($out, JSON_UNESCAPED_SLASHES);

        foreach (['hunter22', 'abcdefgh12345', 't-1', 'token=x', 'S=1', 'abc123'] as $secret) {
            self::assertStringNotContainsString($secret, (string)$json, "$secret leaked");
        }
        self::assertGreaterThanOrEqual(9, $count);

        $q = $out[DumpView::DB]['queries']['h'];
        self::assertSame("SELECT * FROM u WHERE password = '" . self::M . "'", $q['rawSql']);
        self::assertSame('SELECT * FROM u WHERE password = :qp0', $q['sql']);
        self::assertSame([':qp0' => self::M], $q['params']);
        self::assertSame(['position' => 0, 'line' => '/a.php:1', 'status' => 'success', 'actions' => [['time' => 1.5]]], array_intersect_key($q, array_flip(['position', 'line', 'status', 'actions'])));

        self::assertSame('ann', $out[DumpView::LOG][0]['context']['user']);
        self::assertSame('{"token":"[REDACTED]","id":3}', $out[DumpView::LOG][0]['context']['response']);
        self::assertSame('/p', $out[DumpView::REQUEST]['requestPath']);
        self::assertSame([self::M], $out[DumpView::EXCEPTION][0]['trace'][0]['args']);
        self::assertSame('login', $out[DumpView::EXCEPTION][0]['trace'][0]['function']);
        self::assertSame($data[DumpView::SERVICE], $out[DumpView::SERVICE], 'nothing sensitive, nothing changed');
    }

    public function testUrlsWithSecretsAreMaskedWhereverTheySit(): void
    {
        $r = new Redactor();
        $summary = [DumpView::REQUEST => ['request' => ['url' => 'http://a.test/login?token=SECRET1&page=2', 'path' => '/login']]];
        $http = ['Yiisoft\\Yii\\Debug\\Collector\\HttpClientCollector' => [['uri' => 'https://api.example/v1/x?api_key=SECRET2', 'method' => 'GET']]];

        [$out, $count] = $r->dump($summary + $http);

        self::assertSame('http://a.test/login?token=%5BREDACTED%5D&page=2', $out[DumpView::REQUEST]['request']['url']);
        self::assertSame('/login', $out[DumpView::REQUEST]['request']['path']);
        self::assertSame('https://api.example/v1/x?api_key=%5BREDACTED%5D', array_values($out)[1][0]['uri']);
        self::assertSame(2, $count);
    }

    public function testQuestionMarksInOrdinaryTextAreLeftAlone(): void
    {
        $data = ['C' => ['message' => 'Is the token valid? yes', 'note' => 'see /docs?', 'q' => 'a ? b', 'url' => 'http://a.test/x?page=2']];

        [$out, $count] = (new Redactor())->dump($data);

        self::assertSame($data, $out);
        self::assertSame(0, $count);
    }

    public function testNothingToMaskReturnsTheSameDumpAndZero(): void
    {
        $data = [DumpView::LOG => [['level' => 'info', 'message' => 'hello', 'time' => 1.0]], DumpView::DB => ['queries' => []], 'Other' => 'scalar'];

        [$out, $count] = (new Redactor())->dump($data);

        self::assertSame($data, $out);
        self::assertSame(0, $count);
    }

    public function testCountIsPerCall(): void
    {
        $r = new Redactor();

        self::assertSame(1, $r->dump(['C' => ['password' => 'x']])[1]);
        self::assertSame(1, $r->dump(['C' => ['password' => 'y']])[1]);
        self::assertSame(0, $r->dump(['C' => ['user' => 'y']])[1]);
    }

    public function testEmptyValuesAreNotCountedOrMasked(): void
    {
        [$out, $count] = (new Redactor())->dump(['C' => ['password' => '', 'token' => null, 'secret' => []]]);

        self::assertSame(['password' => '', 'token' => null, 'secret' => []], $out['C']);
        self::assertSame(0, $count);
    }
}
