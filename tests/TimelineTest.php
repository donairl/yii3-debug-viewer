<?php

declare(strict_types=1);

namespace Dxn\DebugViewer\Tests;

use Dxn\DebugViewer\DumpView;
use Dxn\DebugViewer\Timeline;
use PHPUnit\Framework\TestCase;

final class TimelineTest extends TestCase
{
    private const T0 = 1791429329.0;

    private static function data(): array
    {
        $t = self::T0;

        return [
            DumpView::WEB_APP_INFO => ['preloadTime' => $t, 'applicationProcessingTime' => $t + 0.100],
            DumpView::DB => ['queries' => [
                'h1' => ['position' => 1, 'sql' => 'SELECT 2', 'status' => 'success', 'line' => 'b.php:2', 'actions' => [['action' => 'query.start', 'time' => $t + 0.030], ['action' => 'query.end', 'time' => $t + 0.040]]],
                'h0' => ['position' => 0, 'sql' => 'SELECT 1', 'status' => 'error', 'line' => 'a.php:1', 'actions' => [['action' => 'query.start', 'time' => $t + 0.010], ['action' => 'query.error', 'time' => $t + 0.012]]],
                'h2' => ['position' => 2, 'sql' => 'SELECT 3', 'status' => 'success'],
            ]],
            DumpView::SERVICE => [
                ['service' => 'Fast', 'class' => 'App\\Fast', 'method' => 'get', 'status' => 'success', 'timeStart' => $t + 0.001, 'timeEnd' => $t + 0.0011],
                ['service' => 'Slow', 'class' => 'App\\Slow', 'method' => 'get', 'arguments' => ['App\\Contracts\\Mailer'], 'status' => 'success', 'timeStart' => $t + 0.050, 'timeEnd' => $t + 0.060],
                ['service' => 'Bad', 'class' => 'App\\Bad', 'method' => 'get', 'status' => 'error', 'error' => 'nope', 'timeStart' => $t + 0.070, 'timeEnd' => $t + 0.0701],
            ],
            DumpView::EVENT => [
                ['name' => 'Yiisoft\\Yii\\Http\\Event\\BeforeRequest', 'time' => $t + 0.002],
            ],
            DumpView::LOG => [
                ['level' => 'error', 'message' => "Failed\nbadly", 'time' => $t + 0.020, 'line' => 'c.php:3'],
                ['level' => 'info', 'message' => 'no time'],
            ],
            DumpView::TIMELINE => [
                [$t + 0.080, 1, DumpView::EXCEPTION, ['X']],
                [$t + 0.081, 2, DumpView::EVENT, []],
            ],
        ];
    }

    public function testBuildsChronologicalItemsRelativeToRequestStart(): void
    {
        $tl = Timeline::build(new DumpView(self::data()));

        self::assertEqualsWithDelta(100.0, $tl['totalMs'], 0.01);
        self::assertSame(
            ['event', 'query', 'log', 'query', 'service', 'service', 'exception'],
            array_column($tl['items'], 'type'),
        );
        $starts = array_column($tl['items'], 'startMs');
        $sorted = $starts;
        sort($sorted);
        self::assertSame($sorted, $starts);
        self::assertEqualsWithDelta(2.0, $starts[0], 0.01);
    }

    public function testQueriesAreSpansWithStatusAndSqlRef(): void
    {
        $items = Timeline::build(new DumpView(self::data()))['items'];
        $queries = array_values(array_filter($items, static fn(array $i): bool => $i['type'] === 'query'));

        self::assertCount(2, $queries, 'a query with no recorded actions has no place on the axis');
        self::assertSame(['SELECT 1', 'error', 0], [$queries[0]['label'], $queries[0]['status'], $queries[0]['ref']]);
        self::assertEqualsWithDelta(2.0, $queries[0]['durationMs'], 0.01);
        self::assertSame(1, $queries[1]['ref']);
    }

    public function testFastServicesAreCountedNotListedButFailuresStay(): void
    {
        $tl = Timeline::build(new DumpView(self::data()));
        $services = array_values(array_filter($tl['items'], static fn(array $i): bool => $i['type'] === 'service'));

        self::assertSame(1, $tl['hiddenServices']);
        self::assertSame(['Slow::get(Mailer)', 'Bad::get()'], array_column($services, 'label'));
        self::assertSame(['ok', 'error'], array_column($services, 'status'));
        self::assertSame('nope', $services[1]['detail']);
    }

    public function testPointsHaveNoDurationAndLogsCollapseWhitespace(): void
    {
        $items = Timeline::build(new DumpView(self::data()))['items'];
        $log = current(array_filter($items, static fn(array $i): bool => $i['type'] === 'log'));

        self::assertNull($log['durationMs']);
        self::assertSame('error: Failed badly', $log['label']);
        self::assertSame('error', $log['status']);
    }

    public function testCountsPerType(): void
    {
        $counts = Timeline::build(new DumpView(self::data()))['counts'];

        self::assertSame(['event' => 1, 'query' => 2, 'log' => 1, 'service' => 2, 'exception' => 1], $counts);
    }

    public function testFlagsQueriesFromTheAnalyzer(): void
    {
        $data = self::data();
        $t = self::T0;
        $queries = [];
        foreach ([1, 2, 3] as $n) {
            $queries["h$n"] = ['position' => $n, 'sql' => "SELECT * FROM i WHERE o = $n", 'status' => 'success', 'actions' => [['time' => $t + $n / 100], ['time' => $t + $n / 100 + 0.001]]];
        }
        $data[DumpView::DB] = ['queries' => $queries];

        $items = Timeline::build(new DumpView($data))['items'];
        $flags = array_column(array_filter($items, static fn(array $i): bool => $i['type'] === 'query'), 'flag');

        self::assertSame(['n+1', 'n+1', 'n+1'], $flags);
    }

    public function testWithoutRequestWindowRangeComesFromItems(): void
    {
        $data = self::data();
        unset($data[DumpView::WEB_APP_INFO]);

        $tl = Timeline::build(new DumpView($data));

        // earliest listed item is the event at +2ms, last is the exception at +80ms
        self::assertEqualsWithDelta(78.0, $tl['totalMs'], 0.01);
        self::assertEqualsWithDelta(0.0, $tl['items'][0]['startMs'], 0.01);
    }

    public function testEmptyDump(): void
    {
        self::assertSame(
            ['totalMs' => 0.0, 'items' => [], 'counts' => [], 'hiddenServices' => 0],
            Timeline::build(new DumpView([])),
        );
    }

    public function testIgnoresInvalidAppInfoWindow(): void
    {
        $view = new DumpView([DumpView::WEB_APP_INFO => ['preloadTime' => 5.0, 'applicationProcessingTime' => 1.0]]);

        self::assertNull($view->requestWindow());
    }
}
