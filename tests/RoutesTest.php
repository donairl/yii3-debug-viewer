<?php

declare(strict_types=1);

namespace Dxn\DebugViewer\Tests;

use Dxn\DebugViewer\DeleteAction;
use Dxn\DebugViewer\ExportAction;
use Dxn\DebugViewer\IndexAction;
use Dxn\DebugViewer\Routes;
use Dxn\DebugViewer\ViewAction;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\UriInterface;
use Yiisoft\Router\FastRoute\UrlGenerator;
use Yiisoft\Router\FastRoute\UrlMatcher;
use Yiisoft\Router\MatchingResult;
use Yiisoft\Router\RouteCollection;
use Yiisoft\Router\RouteCollector;

/**
 * The route table, run through the real router the host uses.
 */
final class RoutesTest extends TestCase
{
    private UrlMatcher $matcher;

    private UrlGenerator $generator;

    protected function setUp(): void
    {
        $collection = new RouteCollection((new RouteCollector())->addRoute(...Routes::create('/debug')));
        $this->matcher = new UrlMatcher($collection);
        $this->generator = new UrlGenerator($collection);
    }

    private function match(string $method, string $path): MatchingResult
    {
        $uri = $this->createMock(UriInterface::class);
        $uri->method('getPath')->willReturn($path);
        $uri->method('getHost')->willReturn('localhost');
        $request = $this->createMock(ServerRequestInterface::class);
        $request->method('getMethod')->willReturn($method);
        $request->method('getUri')->willReturn($uri);

        return $this->matcher->match($request);
    }

    /**
     * @return array{0: string, 1: array<string, string>} Route name and arguments of a successful match.
     */
    private function matched(string $method, string $path): array
    {
        $result = $this->match($method, $path);
        self::assertTrue($result->isSuccess(), "$method $path");

        return [(string)$result->route()->getData('name'), $result->arguments()];
    }

    public function testEveryPageRouteMatches(): void
    {
        self::assertSame(['debug.index', []], $this->matched('GET', '/debug'));
        self::assertSame(['debug.index', []], $this->matched('GET', '/debug/'));
        self::assertSame(['debug.view', ['id' => '6ac70ae05b8e1284954720']], $this->matched('GET', '/debug/6ac70ae05b8e1284954720'));
        self::assertSame(['debug.export', ['id' => 'abc123']], $this->matched('GET', '/debug/abc123/export'));
    }

    public function testDeletingIsPostOnly(): void
    {
        self::assertSame(['debug.clear', []], $this->matched('POST', '/debug/clear'));
        self::assertSame(['debug.delete', ['id' => 'abc123']], $this->matched('POST', '/debug/abc123/delete'));

        foreach (['GET', 'PUT', 'DELETE'] as $method) {
            self::assertFalse($this->match($method, '/debug/abc123/delete')->isSuccess(), "$method delete");
            self::assertTrue($this->match($method, '/debug/abc123/delete')->isMethodFailure(), "$method is a method failure, not a page");
        }
    }

    public function testClearIsNotAnIdForTheViewRoute(): void
    {
        // GET /debug/clear opens the "view" route for an id called "clear" (a 404 page), it deletes nothing
        self::assertSame(['debug.view', ['id' => 'clear']], $this->matched('GET', '/debug/clear'));
        self::assertSame(['debug.clear', []], $this->matched('POST', '/debug/clear'));
    }

    public function testIdsCannotSmuggleInPaths(): void
    {
        foreach (['/debug/../etc/delete', '/debug/a%2Fb/delete', '/debug/a b/delete', '/debug//delete'] as $path) {
            self::assertFalse($this->match('POST', $path)->isSuccess(), $path);
        }
    }

    public function testUrlsAreGeneratedFromTheRouteNames(): void
    {
        // the index pattern is `/debug[/]`: generated with the slash, which is why the CSRF cookie path is rtrim()'d
        self::assertSame('/debug/', $this->generator->generate('debug.index'));
        self::assertSame('/debug/abc', $this->generator->generate('debug.view', ['id' => 'abc']));
        self::assertSame('/debug/abc/export?format=json&reveal=1', $this->generator->generate('debug.export', ['id' => 'abc'], ['format' => 'json', 'reveal' => '1']));
        self::assertSame('/debug/abc/delete', $this->generator->generate('debug.delete', ['id' => 'abc']));
        self::assertSame('/debug/clear', $this->generator->generate('debug.clear'));
        self::assertSame('/debug/?deleted=2', $this->generator->generate('debug.index', [], ['deleted' => '2']));
    }

    public function testActionsAreTheClassesTheContainerWillBuild(): void
    {
        $actions = [];
        foreach (Routes::create('/debug') as $route) {
            $actions[(string)$route->getData('name')] = $route->getData('enabledMiddlewares')[0];
        }

        self::assertSame(IndexAction::class, $actions['debug.index']);
        self::assertSame(ViewAction::class, $actions['debug.view']);
        self::assertSame(ExportAction::class, $actions['debug.export']);
        self::assertSame([DeleteAction::class, 'all'], $actions['debug.clear']);
        self::assertSame([DeleteAction::class, 'one'], $actions['debug.delete']);
        self::assertTrue(method_exists(DeleteAction::class, 'one') && method_exists(DeleteAction::class, 'all'));
    }

    public function testPrefixIsNormalised(): void
    {
        $collection = new RouteCollection((new RouteCollector())->addRoute(...Routes::create('_tools/debug/')));
        $generator = new UrlGenerator($collection);

        self::assertSame('/_tools/debug/clear', $generator->generate('debug.clear'));
    }
}
