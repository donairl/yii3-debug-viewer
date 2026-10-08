<?php

declare(strict_types=1);

namespace Dxn\DebugViewer\Tests;

use Dxn\DebugViewer\Csrf;
use Dxn\DebugViewer\DumpReader;
use Dxn\DebugViewer\DumpStorage;
use Dxn\DebugViewer\DumpView;
use Dxn\DebugViewer\EditorLinker;
use Dxn\DebugViewer\Redactor;
use Dxn\DebugViewer\Template;
use Dxn\DebugViewer\ViewAction;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\StreamInterface;
use Psr\Http\Message\UriInterface;
use Yiisoft\Aliases\Aliases;
use Yiisoft\Router\CurrentRoute;
use Yiisoft\Router\Route;
use Yiisoft\Router\UrlGeneratorInterface;

final class ViewActionTest extends TestCase
{
    private const ID = '6ac70ae05b8e1284954720';

    private string $root;

    /** @var list<string> Set-Cookie headers of the last render() */
    private array $cookies = [];

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/dxn-view-' . bin2hex(random_bytes(6));
        $dir = $this->root . '/debug/2026-10-08/' . self::ID;
        mkdir($dir, 0777, true);

        file_put_contents($dir . '/summary.json', json_encode(['summary' => [
            DumpView::REQUEST => ['request' => ['method' => 'GET', 'path' => '/p', 'url' => 'http://a.test/p?token=URLSECRET&page=2'], 'response' => ['statusCode' => 200]],
        ]]));
        file_put_contents($dir . '/data.json', json_encode([
            DumpView::REQUEST => [
                'requestUrl' => 'http://a.test/p?token=URLSECRET&page=2', 'requestMethod' => 'GET',
                'requestRaw' => "GET /p?token=URLSECRET&page=2 HTTP/1.1\r\nHost: a.test\r\nCookie: SESSID=COOKIESECRET\r\nAuthorization: Bearer BEARERSECRET123\r\n\r\n",
                'responseRaw' => "HTTP/1.1 200 OK\r\nContent-Type: application/json\r\n\r\n{\"access_token\":\"RESPONSESECRET\"}",
            ],
            DumpView::LOG => [['level' => 'info', 'message' => 'login', 'time' => 1.0, 'context' => ['user' => 'ann', 'password' => 'PASSWORDSECRET']]],
            DumpView::DB => ['queries' => ['h' => ['position' => 0, 'sql' => 'SELECT * FROM u WHERE password = :qp0', 'rawSql' => "SELECT * FROM u WHERE password = 'DBSECRET99'", 'params' => [':qp0' => 'DBSECRET99'], 'status' => 'success']]],
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

    private const SECRETS = ['URLSECRET', 'COOKIESECRET', 'BEARERSECRET123', 'RESPONSESECRET', 'PASSWORDSECRET', 'DBSECRET99'];

    private function render(array $query = [], bool $redact = true, array $cookies = [], bool $allowDelete = true): string
    {
        $html = '';
        $this->cookies = [];
        $stream = $this->createMock(StreamInterface::class);
        $stream->method('write')->willReturnCallback(static function (string $s) use (&$html): int {
            $html = $s;

            return strlen($s);
        });
        $response = $this->createMock(ResponseInterface::class);
        $response->method('getBody')->willReturn($stream);
        $response->method('withHeader')->willReturnSelf();
        $response->method('withAddedHeader')->willReturnCallback(function (string $name, $value) use ($response): ResponseInterface {
            if ($name === 'Set-Cookie') {
                $this->cookies[] = (string)$value;
            }

            return $response;
        });
        $factory = $this->createMock(ResponseFactoryInterface::class);
        $factory->method('createResponse')->willReturn($response);

        $urls = $this->createMock(UrlGeneratorInterface::class);
        $urls->method('generate')->willReturnCallback(
            static fn(string $name, array $arguments = [], array $queryParameters = []): string => '/debug' . (isset($arguments['id']) ? '/' . $arguments['id'] : '')
                . ($name === 'debug.delete' ? '/delete' : '')
                . ($queryParameters !== [] ? '?' . http_build_query($queryParameters) : ''),
        );

        $route = new CurrentRoute();
        $route->setRouteWithArguments(Route::get('/debug/{id}'), ['id' => self::ID]);

        $request = $this->createMock(ServerRequestInterface::class);
        $request->method('getQueryParams')->willReturn($query);
        $request->method('getCookieParams')->willReturn($cookies);
        $uri = $this->createMock(UriInterface::class);
        $uri->method('getScheme')->willReturn('http');
        $request->method('getUri')->willReturn($uri);

        $action = new ViewAction(
            reader: new DumpReader(new DumpStorage(new Aliases(['@runtime' => $this->root])), new Redactor(), $redact),
            template: new Template($factory),
            currentRoute: $route,
            editor: new EditorLinker(),
            urlGenerator: $urls,
            csrf: new Csrf(),
            enabled: true,
            allowDelete: $allowDelete,
        );

        $response = $action($request);
        self::assertInstanceOf(ResponseInterface::class, $response);
        self::assertNotSame('', $html, 'the page was written');

        return $html;
    }

    public function testSecretsNeverReachThePageByDefault(): void
    {
        $html = $this->render();

        foreach (self::SECRETS as $secret) {
            self::assertStringNotContainsString($secret, $html, "$secret leaked into the page");
        }
        // the structure around them is still there
        self::assertStringContainsString('SESSID=[REDACTED]', $html);
        self::assertStringContainsString('page=2', $html);
        self::assertStringContainsString('ann', $html);
    }

    public function testBannerSaysHowManyAreMaskedAndOffersToShowThem(): void
    {
        $html = $this->render();

        self::assertMatchesRegularExpression('/<b>\d+<\/b> sensitive values are masked/', $html);
        self::assertStringContainsString('href="/debug/' . self::ID . '?reveal=1"', $html);
        self::assertStringNotContainsString('Sensitive values are <b>shown</b>', $html);
    }

    public function testRevealShowsTheRealValuesAndOffersToMaskThemAgain(): void
    {
        $html = $this->render(['reveal' => '1']);

        foreach (self::SECRETS as $secret) {
            self::assertStringContainsString($secret, $html, "$secret should be shown when revealed");
        }
        self::assertStringContainsString('Sensitive values are <b>shown</b>', $html);
        self::assertStringContainsString('href="/debug/' . self::ID . '"', $html);
        self::assertStringNotContainsString('sensitive values are masked', $html);
    }

    public function testOnlyTheValueOneStaysRevealedForTheCurlCommand(): void
    {
        $masked = $this->render();
        $revealed = $this->render(['reveal' => '1']);

        self::assertStringContainsString('Credentials are masked in this view', $masked);
        self::assertStringNotContainsString('id="curl-secrets"', $masked, 'nothing real to include while masked');
        self::assertStringContainsString('id="curl-secrets"', $revealed);
    }

    public function testRedactionCanBeSwitchedOffEntirely(): void
    {
        $html = $this->render(redact: false);

        foreach (self::SECRETS as $secret) {
            self::assertStringContainsString($secret, $html);
        }
        self::assertStringNotContainsString('class="redaction-note', $html, 'no banner');
        self::assertStringNotContainsString('?reveal=1', $html);
    }

    public function testRevealParameterIsIgnoredWhenRedactionIsOff(): void
    {
        $html = $this->render(['reveal' => '1'], redact: false);

        self::assertStringNotContainsString('Sensitive values are <b>shown</b>', $html);
    }

    public function testOnlyTheExactValueOneRevealsAnything(): void
    {
        foreach (['0', 'true', 'yes', '', '1 '] as $value) {
            self::assertStringNotContainsString('COOKIESECRET', $this->render(['reveal' => $value]), "reveal=$value");
        }
    }

    public function testExportLinksCarryTheRevealStateOfThePage(): void
    {
        $masked = $this->render();
        $revealed = $this->render(['reveal' => '1']);

        self::assertStringContainsString('href="/debug/' . self::ID . '?format=json"', $masked);
        self::assertStringContainsString('href="/debug/' . self::ID . '?format=html"', $masked);
        self::assertStringNotContainsString('format=json&amp;reveal=1', $masked, 'a masked page never links to a revealed export');
        self::assertStringContainsString('format=json&amp;reveal=1', $revealed);
        self::assertStringContainsString('format=html&amp;reveal=1', $revealed);
    }

    private const TOKEN = '0123456789abcdef0123456789abcdef';

    public function testDeleteButtonPostsToTheDeleteRouteWithAToken(): void
    {
        $html = $this->render();

        self::assertStringContainsString('action="/debug/' . self::ID . '/delete"', $html);
        self::assertMatchesRegularExpression('/name="_csrf" value="[a-f0-9]{32}"/', $html);
        self::assertStringContainsString("confirm('Delete this request dump?", $html);
    }

    public function testAFirstVisitSetsTheCsrfCookieAndTheFormCarriesTheSameToken(): void
    {
        $html = $this->render();

        self::assertCount(1, $this->cookies);
        self::assertMatchesRegularExpression('/^dxn_debug_csrf=([a-f0-9]{32}); Path=\/debug; HttpOnly; SameSite=Strict$/', $this->cookies[0]);
        preg_match('/dxn_debug_csrf=([a-f0-9]{32})/', $this->cookies[0], $cookie);
        self::assertStringContainsString('name="_csrf" value="' . $cookie[1] . '"', $html);
    }

    public function testAnExistingCookieIsKeptNotReissued(): void
    {
        $html = $this->render(cookies: [Csrf::COOKIE => self::TOKEN]);

        self::assertSame([], $this->cookies);
        self::assertStringContainsString('name="_csrf" value="' . self::TOKEN . '"', $html);
    }

    public function testNoDeleteButtonAndNoCookieWhenDeletingIsOff(): void
    {
        $html = $this->render(allowDelete: false);

        self::assertStringNotContainsString('/delete"', $html);
        self::assertStringNotContainsString('name="_csrf"', $html);
        self::assertSame([], $this->cookies);
    }
}
