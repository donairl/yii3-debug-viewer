<?php

declare(strict_types=1);

namespace Dxn\DebugViewer\Tests;

use Dxn\DebugViewer\Csrf;
use Dxn\DebugViewer\DeleteAction;
use Dxn\DebugViewer\DumpStorage;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\StreamInterface;
use Dxn\DebugViewer\Template;
use Yiisoft\Aliases\Aliases;
use Yiisoft\Router\CurrentRoute;
use Yiisoft\Router\Route;
use Yiisoft\Router\UrlGeneratorInterface;

final class DeleteActionTest extends TestCase
{
    private const TOKEN = '0123456789abcdef0123456789abcdef';
    private const A = '6ac70ae05b8e1284954720';
    private const B = '6ac70ae05b8e2284954721';

    private string $root;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/dxn-delete-' . bin2hex(random_bytes(6));
        foreach ([['2026-10-08', self::A], ['2026-10-08', self::B], ['2026-10-07', '6ac70ae05b8e3284954722']] as [$date, $id]) {
            mkdir("$this->root/debug/$date/$id", 0777, true);
            file_put_contents("$this->root/debug/$date/$id/summary.json", '{"summary":{}}');
            file_put_contents("$this->root/debug/$date/$id/data.json", '{}');
        }
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

    private function remaining(): int
    {
        return count((new DumpStorage(new Aliases(['@runtime' => $this->root])))->list());
    }

    /**
     * @param 'one'|'all' $which
     *
     * @return array{status: int, headers: array<string, string>, body: string}
     */
    private function call(string $which, string $method = 'POST', ?array $cookies = null, mixed $parsed = [Csrf::FIELD => self::TOKEN], array $headers = [], bool $enabled = true, bool $allowDelete = true, string $id = self::A, string $raw = ''): array
    {
        $captured = ['status' => 0, 'headers' => [], 'body' => ''];

        $stream = $this->createMock(StreamInterface::class);
        $stream->method('write')->willReturnCallback(static function (string $s) use (&$captured): int {
            $captured['body'] .= $s;

            return strlen($s);
        });
        $stream->method('__toString')->willReturn($raw);
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

        $urls = $this->createMock(UrlGeneratorInterface::class);
        $urls->method('generate')->willReturnCallback(
            static fn(string $name, array $arguments = [], array $query = []): string => '/debug' . ($query !== [] ? '?' . http_build_query($query) : ''),
        );
        $route = new CurrentRoute();
        $route->setRouteWithArguments(Route::post('/debug/{id}/delete'), ['id' => $id]);

        $request = $this->createMock(ServerRequestInterface::class);
        $request->method('getMethod')->willReturn($method);
        $request->method('getCookieParams')->willReturn($cookies ?? [Csrf::COOKIE => self::TOKEN]);
        $request->method('getParsedBody')->willReturn($parsed);
        $request->method('getBody')->willReturn($stream);
        $request->method('getHeaderLine')->willReturnCallback(static fn(string $name): string => $headers[$name] ?? '');

        $action = new DeleteAction(
            storage: new DumpStorage(new Aliases(['@runtime' => $this->root])),
            template: new Template($factory),
            currentRoute: $route,
            urlGenerator: $urls,
            csrf: new Csrf(),
            enabled: $enabled,
            allowDelete: $allowDelete,
        );
        $action->$which($request);

        return $captured;
    }

    public function testDeletingOneRedirectsToTheListAndRemovesOnlyThatDump(): void
    {
        $r = $this->call('one');

        self::assertSame(303, $r['status']);
        self::assertSame('/debug?deleted=1', $r['headers']['Location']);
        self::assertSame(2, $this->remaining());
        self::assertDirectoryDoesNotExist("$this->root/debug/2026-10-08/" . self::A);
        self::assertDirectoryExists("$this->root/debug/2026-10-08/" . self::B);
    }

    public function testDeletingADumpThatIsAlreadyGoneSaysZero(): void
    {
        $r = $this->call('one', id: '6ac70ae05b8effffffffff');

        self::assertSame('/debug?deleted=0', $r['headers']['Location']);
        self::assertSame(3, $this->remaining());
    }

    public function testClearingRemovesEverythingAndCountsIt(): void
    {
        $r = $this->call('all');

        self::assertSame(303, $r['status']);
        self::assertSame('/debug?deleted=3', $r['headers']['Location']);
        self::assertSame(0, $this->remaining());
    }

    public function testWorksWhenTheHostDoesNotParseFormBodies(): void
    {
        $r = $this->call('one', parsed: null, headers: ['Content-Type' => 'application/x-www-form-urlencoded'], raw: '_csrf=' . self::TOKEN);

        self::assertSame(303, $r['status']);
        self::assertSame(2, $this->remaining());
    }

    #[DataProvider('refusals')]
    public function testNothingIsDeletedWithoutAValidToken(string $which, ?array $cookies, mixed $parsed, array $headers): void
    {
        $r = $this->call($which, cookies: $cookies, parsed: $parsed, headers: $headers);

        self::assertSame(403, $r['status']);
        self::assertArrayNotHasKey('Location', $r['headers']);
        self::assertSame(3, $this->remaining());
    }

    public static function refusals(): iterable
    {
        $other = 'fedcba9876543210fedcba9876543210';
        foreach (['one', 'all'] as $which) {
            yield "$which: no cookie" => [$which, [], [Csrf::FIELD => self::TOKEN], []];
            yield "$which: no field" => [$which, null, [], []];
            yield "$which: no body" => [$which, null, null, []];
            yield "$which: wrong token" => [$which, null, [Csrf::FIELD => $other], []];
            yield "$which: cross-site" => [$which, null, [Csrf::FIELD => self::TOKEN], ['Sec-Fetch-Site' => 'cross-site']];
        }
    }

    public function testOnlyPostDeletes(): void
    {
        foreach (['GET', 'HEAD', 'DELETE', 'PUT'] as $method) {
            $r = $this->call('all', method: $method);

            self::assertSame(405, $r['status'], $method);
            self::assertSame('POST', $r['headers']['Allow'], $method);
        }
        self::assertSame(3, $this->remaining());
    }

    public function testRefusesWhenDeletingIsSwitchedOff(): void
    {
        self::assertSame(403, $this->call('all', allowDelete: false)['status']);
        self::assertSame(403, $this->call('one', allowDelete: false)['status']);
        self::assertSame(3, $this->remaining());
    }

    public function testDoesNothingWhileTheViewerIsOff(): void
    {
        self::assertSame(404, $this->call('all', enabled: false)['status']);
        self::assertSame(3, $this->remaining());
    }
}
