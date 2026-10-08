<?php

declare(strict_types=1);

namespace Dxn\DebugViewer\Tests;

use Dxn\DebugViewer\Template;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

final class IndexTemplateTest extends TestCase
{
    private static function row(array $over = []): array
    {
        return $over + [
            'id' => 'abc123', 'time' => 1791429329.0, 'method' => 'GET', 'path' => '/orders', 'url' => '', 'status' => 200,
            'queries' => 4, 'queryErrors' => 0, 'logs' => 2, 'exceptions' => 0, 'durationMs' => 120.5, 'memoryMb' => 3.25,
            'action' => 'App\\Orders', 'routeName' => 'Orders.Show',
        ];
    }

    private static function render(array $rows, int $limit = 100): string
    {
        $params = ['title' => 't', 'indexUrl' => '/debug', 'rows' => $rows, 'limit' => $limit, 'viewUrl' => static fn(string $id): string => "/debug/$id"];

        return (string)(new ReflectionMethod(Template::class, 'render'))
            ->invoke(null, dirname(__DIR__) . '/src/templates/index.php', $params);
    }

    public function testRowsCarryTheDataTheFiltersAndSortingRead(): void
    {
        $html = self::render([self::row(['exceptions' => 1, 'queryErrors' => 2])]);

        foreach ([
            'data-route="orders.show"' => 'route is lower-cased for search',
            'data-problems="3"' => 'exceptions plus failed queries',
            'data-queries="4"' => 'min-queries filter and sort',
            'data-logs="2"' => 'sort',
            'data-duration="120.5"' => 'slow filter and sort',
            'data-memory="3.25"' => 'sort',
            'data-time="1791429329"' => 'time-range filter and sort',
        ] as $attr => $why) {
            self::assertStringContainsString($attr, $html, $why);
        }
    }

    public function testEverySortableColumnHasADataKeyTheScriptCanRead(): void
    {
        $html = self::render([self::row()]);

        preg_match_all('/<th[^>]*data-sort="(\w+)"/', $html, $m);
        self::assertSame(['time', 'method', 'status', 'path', 'queries', 'logs', 'duration', 'memory'], $m[1]);

        foreach (array_diff($m[1], ['method', 'path', 'status']) as $key) {
            self::assertStringContainsString('data-' . $key . '="', $html, "rows need data-$key to sort by it");
        }
    }

    public function testFilterBarIsOnlyShownWithRows(): void
    {
        self::assertStringContainsString('id="idx-filters"', self::render([self::row()]));
        self::assertStringNotContainsString('id="idx-filters"', self::render([]));
    }

    public function testNotesWhenTheListLimitWasReached(): void
    {
        self::assertStringContainsString('Newest 2 dumps loaded', self::render([self::row(), self::row(['id' => 'b'])], 2));
        self::assertStringNotContainsString('dumps loaded', self::render([self::row()], 100));
    }

    public function testServerTimeIsEmbeddedSoTimeRangesIgnoreBrowserClockSkew(): void
    {
        self::assertMatchesRegularExpression('/id="requests-table" data-now="\d{10}"/', self::render([self::row()]));
    }
}
