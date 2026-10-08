<?php

declare(strict_types=1);

namespace Dxn\DebugViewer\Tests;

use Dxn\DebugViewer\DumpReader;
use Dxn\DebugViewer\DumpStorage;
use Dxn\DebugViewer\DumpView;
use Dxn\DebugViewer\EditorLinker;
use Dxn\DebugViewer\ExportAction;
use Dxn\DebugViewer\Redactor;
use Dxn\DebugViewer\Template;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\StreamInterface;
use Yiisoft\Aliases\Aliases;
use Yiisoft\Router\CurrentRoute;
use Yiisoft\Router\Route;

final class ExportActionTest extends TestCase
{
    private const ID = '6ac70ae05b8e1284954720';
    private const SECRETS = ['URLSECRET', 'COOKIESECRET', 'PASSWORDSECRET', 'DBSECRET99'];

    private string $root;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/dxn-export-' . bin2hex(random_bytes(6));
        $dir = $this->root . '/debug/2026-10-08/' . self::ID;
        mkdir($dir, 0777, true);

        file_put_contents($dir . '/summary.json', json_encode(['summary' => [
            DumpView::REQUEST => ['request' => ['method' => 'POST', 'path' => '/login', 'url' => 'http://a.test/login?token=URLSECRET'], 'response' => ['statusCode' => 302]],
        ]]));
        file_put_contents($dir . '/data.json', json_encode([
            DumpView::REQUEST => [
                'requestUrl' => 'http://a.test/login?token=URLSECRET', 'requestMethod' => 'POST',
                'requestRaw' => "POST /login?token=URLSECRET HTTP/1.1\r\nHost: a.test\r\nCookie: SESSID=COOKIESECRET\r\n\r\n",
            ],
            DumpView::LOG => [['level' => 'info', 'message' => 'login', 'time' => 1.0, 'context' => ['user' => 'ann', 'password' => 'PASSWORDSECRET']]],
            DumpView::DB => ['queries' => ['h' => ['position' => 0, 'sql' => 'SELECT 1 WHERE password = :qp0', 'rawSql' => "SELECT 1 WHERE password = 'DBSECRET99'", 'params' => [':qp0' => 'DBSECRET99'], 'status' => 'success']]],
        ]));
    }

    protected function tearDown(): void
    {
        $rm = function (string $p) use (&$rm): void {
            if (is_file($p)) {
                unlink($p);
                return;
            }
            foreach (glob($p . '/*') ?: [] as $c) {
                $rm($c);
            }
            @rmdir($p);
        };
        $rm($this->root);
    }

    /**
     * @return array{status: int, headers: array<string, string>, body: string}
     */
    private function export(array $query, bool $redact = true, bool $enabled = true, string $id = self::ID): array
    {
        $captured = ['status' => 0, 'headers' => [], 'body' => ''];

        $stream = $this->createMock(StreamInterface::class);
        $stream->method('write')->willReturnCallback(static function (string $s) use (&$captured): int {
            $captured['body'] .= $s;

            return strlen($s);
        });
        $response = $this->createMock(ResponseInterface::class);
        $response->method('getBody')->willReturn($stream);
        $response->method('withHeader')->willReturnCallback(function (string $name, $value) use (&$captured, $response): ResponseInterface {
            $captured['headers'][$name] = (string)$value;

            return $response;
        });
        $factory = $this->createMock(ResponseFactoryInterface::class);
        $factory->method('createResponse')->willReturnCallback(function (int $code = 200) use (&$captured, $response): ResponseInterface {
            $captured['status'] = $code;

            return $response;
        });

        $route = new CurrentRoute();
        $route->setRouteWithArguments(Route::get('/debug/{id}/export'), ['id' => $id]);
        $request = $this->createMock(ServerRequestInterface::class);
        $request->method('getQueryParams')->willReturn($query);

        $action = new ExportAction(
            reader: new DumpReader(new DumpStorage(new Aliases(['@runtime' => $this->root])), new Redactor(), $redact),
            template: new Template($factory),
            currentRoute: $route,
            editor: new EditorLinker(),
            enabled: $enabled,
        );
        $action($request);

        return $captured;
    }

    public function testJsonIsTheDefaultAndIsAnAttachment(): void
    {
        $r = $this->export([]);

        self::assertSame(200, $r['status']);
        self::assertSame('application/json; charset=UTF-8', $r['headers']['Content-Type']);
        self::assertSame('attachment; filename="debug-' . self::ID . '.json"', $r['headers']['Content-Disposition']);
        self::assertSame('nosniff', $r['headers']['X-Content-Type-Options']);
    }

    public function testJsonCarriesTheDataWithSecretsMasked(): void
    {
        $r = $this->export(['format' => 'json']);
        $doc = json_decode($r['body'], true, flags: JSON_THROW_ON_ERROR);

        self::assertSame('dxn/yii3-debug-viewer', $doc['generator']);
        self::assertSame(self::ID, $doc['id']);
        self::assertMatchesRegularExpression('/^\d{4}-\d\d-\d\d \d\d:\d\d:\d\d UTC$/', $doc['exportedAt']);
        self::assertSame(['POST', '/login'], [$doc['meta']['method'], $doc['meta']['path']]);
        self::assertTrue($doc['redaction']['enabled']);
        self::assertFalse($doc['redaction']['revealed']);
        self::assertGreaterThanOrEqual(4, $doc['redaction']['masked']);
        self::assertSame('ann', $doc['data'][DumpView::LOG][0]['context']['user']);
        self::assertSame('[REDACTED]', $doc['data'][DumpView::LOG][0]['context']['password']);
        self::assertArrayHasKey(DumpView::DB, $doc['data']);
        foreach (self::SECRETS as $secret) {
            self::assertStringNotContainsString($secret, $r['body']);
        }
        self::assertStringEndsWith("\n", $r['body']);
    }

    public function testRevealIncludesTheRealValuesAndSaysSo(): void
    {
        $r = $this->export(['format' => 'json', 'reveal' => '1']);
        $doc = json_decode($r['body'], true, flags: JSON_THROW_ON_ERROR);

        self::assertTrue($doc['redaction']['revealed']);
        self::assertSame(0, $doc['redaction']['masked']);
        self::assertSame('PASSWORDSECRET', $doc['data'][DumpView::LOG][0]['context']['password']);
    }

    public function testHtmlIsOneSelfContainedSnapshot(): void
    {
        $r = $this->export(['format' => 'html']);
        $html = $r['body'];

        self::assertSame('text/html; charset=UTF-8', $r['headers']['Content-Type']);
        self::assertSame('attachment; filename="debug-' . self::ID . '.html"', $r['headers']['Content-Disposition']);
        self::assertStringContainsString('<b>Snapshot</b> of request', $html);
        self::assertStringContainsString('POST', $html);
        // nothing to fetch, nothing pointing back at the live viewer
        self::assertStringNotContainsString('<link ', $html);
        self::assertDoesNotMatchRegularExpression('/\bsrc="/', $html);
        self::assertStringNotContainsString('href="/debug', $html);
        self::assertStringNotContainsString('id="btn-refresh"', $html);
        self::assertStringNotContainsString('Replay as cURL</span>' . "\n" . '                    </div>' . "\n" . '                    <div style="display: flex; align-items: center; gap: 0.75rem;">' . "\n" . '                        <label', $html);
        self::assertStringNotContainsString('>Export</span>', $html, 'a snapshot does not offer to export itself');
        self::assertStringNotContainsString('href=""', $html);
    }

    public function testHtmlSnapshotKeepsSecretsOutByDefault(): void
    {
        $html = $this->export(['format' => 'html'])['body'];

        foreach (self::SECRETS as $secret) {
            self::assertStringNotContainsString($secret, $html, "$secret leaked into the snapshot");
        }
        self::assertStringContainsString('sensitive values are masked', $html);
    }

    public function testHtmlSnapshotWarnsWhenItCarriesRealValues(): void
    {
        $html = $this->export(['format' => 'html', 'reveal' => '1'])['body'];

        self::assertStringContainsString('includes the real sensitive values', $html);
        self::assertStringContainsString('COOKIESECRET', $html);
    }

    public function testHtmlSnapshotSaysWhenRedactionIsOff(): void
    {
        $html = $this->export(['format' => 'html'], redact: false)['body'];

        self::assertStringContainsString('not masked', $html);
        self::assertStringContainsString('COOKIESECRET', $html);
    }

    public function testFormatIsCaseInsensitive(): void
    {
        self::assertSame('application/json; charset=UTF-8', $this->export(['format' => 'JSON'])['headers']['Content-Type']);
    }

    public function testUnknownFormatIsABadRequest(): void
    {
        $r = $this->export(['format' => 'xml']);

        self::assertSame(400, $r['status']);
        self::assertSame('text/plain; charset=UTF-8', $r['headers']['Content-Type']);
        self::assertArrayNotHasKey('Content-Disposition', $r['headers']);
        self::assertStringContainsString('format=json or format=html', $r['body']);
    }

    public function testUnknownDumpIsNotFound(): void
    {
        $r = $this->export(['format' => 'json'], id: 'doesnotexist');

        self::assertSame(404, $r['status']);
        self::assertArrayNotHasKey('Content-Disposition', $r['headers']);
    }

    public function testNothingIsExportedWhileTheViewerIsOff(): void
    {
        $r = $this->export(['format' => 'json', 'reveal' => '1'], enabled: false);

        self::assertSame(404, $r['status']);
        foreach (self::SECRETS as $secret) {
            self::assertStringNotContainsString($secret, $r['body']);
        }
    }
}
