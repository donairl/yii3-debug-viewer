<?php

declare(strict_types=1);

namespace Dxn\DebugViewer\Tests;

use Dxn\DebugViewer\Csrf;
use Dxn\DebugViewer\DumpStorage;
use Dxn\DebugViewer\IndexAction;
use Dxn\DebugViewer\Template;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\StreamInterface;
use Psr\Http\Message\UriInterface;
use Yiisoft\Aliases\Aliases;
use Yiisoft\Router\UrlGeneratorInterface;

final class IndexActionTest extends TestCase
{
    private const TOKEN = '0123456789abcdef0123456789abcdef';
    private const ID = '6ac70ae05b8e1284954720';

    private string $root;

    /** @var list<string> */
    private array $cookies = [];

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/dxn-index-' . bin2hex(random_bytes(6));
        $dir = "$this->root/debug/2026-10-08/" . self::ID;
        mkdir($dir, 0777, true);
        file_put_contents($dir . '/summary.json', json_encode(['summary' => []]));
        file_put_contents($dir . '/data.json', '{}');
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

    private function render(array $query = [], array $cookies = [], bool $allowDelete = true, string $scheme = 'http'): string
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
            $name === 'Set-Cookie' && $this->cookies[] = (string)$value;

            return $response;
        });
        $factory = $this->createMock(ResponseFactoryInterface::class);
        $factory->method('createResponse')->willReturn($response);

        $urls = $this->createMock(UrlGeneratorInterface::class);
        $urls->method('generate')->willReturnCallback(static fn(string $name, array $arguments = []): string => match ($name) {
            'debug.index' => '/app/debug',
            'debug.view' => '/app/debug/' . $arguments['id'],
            'debug.delete' => '/app/debug/' . $arguments['id'] . '/delete',
            'debug.clear' => '/app/debug/clear',
            default => '/',
        });

        $uri = $this->createMock(UriInterface::class);
        $uri->method('getScheme')->willReturn($scheme);
        $request = $this->createMock(ServerRequestInterface::class);
        $request->method('getQueryParams')->willReturn($query);
        $request->method('getCookieParams')->willReturn($cookies);
        $request->method('getUri')->willReturn($uri);

        (new IndexAction(
            storage: new DumpStorage(new Aliases(['@runtime' => $this->root])),
            template: new Template($factory),
            urlGenerator: $urls,
            csrf: new Csrf(),
            enabled: true,
            allowDelete: $allowDelete,
        ))($request);

        return $html;
    }

    public function testEachRowHasADeleteFormAndThereIsADeleteAllForm(): void
    {
        $html = $this->render(cookies: [Csrf::COOKIE => self::TOKEN]);

        self::assertStringContainsString('action="/app/debug/' . self::ID . '/delete"', $html);
        self::assertStringContainsString('action="/app/debug/clear"', $html);
        self::assertSame(2, substr_count($html, 'name="_csrf" value="' . self::TOKEN . '"'));
        self::assertStringContainsString("confirm('Delete ALL request dumps?", $html);
        self::assertSame(2, substr_count($html, 'method="post"'), 'both are POST forms, never links');
    }

    public function testCookieIsScopedToTheViewerPathIncludingTheHostsPrefix(): void
    {
        $this->render();

        self::assertCount(1, $this->cookies);
        self::assertMatchesRegularExpression('/^dxn_debug_csrf=[a-f0-9]{32}; Path=\/app\/debug; HttpOnly; SameSite=Strict$/', $this->cookies[0]);

        $this->render(scheme: 'https');
        self::assertStringEndsWith('; Secure', $this->cookies[0]);

        $this->render(cookies: [Csrf::COOKIE => self::TOKEN]);
        self::assertSame([], $this->cookies, 'a visitor who has the cookie is not given another');
    }

    public function testNothingToDeleteWithDeletingOff(): void
    {
        $html = $this->render(allowDelete: false);

        self::assertStringNotContainsString('method="post"', $html);
        self::assertStringNotContainsString('name="_csrf"', $html);
        self::assertSame([], $this->cookies);
    }

    public function testSaysHowManyDumpsTheLastDeleteRemoved(): void
    {
        self::assertStringContainsString('Deleted <b>3</b> request dumps.', $this->render(['deleted' => '3']));
        self::assertStringContainsString('Deleted <b>1</b> request dump.', $this->render(['deleted' => '1']));
        self::assertStringContainsString('already gone', $this->render(['deleted' => '0']));
        self::assertStringNotContainsString('class="dash-notice"', $this->render());
    }

    public function testTheDeletedParameterCanOnlyEverBeANumber(): void
    {
        foreach (['<script>alert(1)</script>', '3<b>', '-1', '1.5', '', '1234567890', ['x']] as $evil) {
            $html = $this->render(['deleted' => $evil]);

            self::assertStringNotContainsString('class="dash-notice"', $html, var_export($evil, true));
            self::assertStringNotContainsString('<script>alert(1)', $html);
        }
    }
}
