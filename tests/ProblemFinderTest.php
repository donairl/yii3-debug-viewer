<?php

declare(strict_types=1);

namespace Dxn\DebugViewer\Tests;

use Dxn\DebugViewer\DumpView;
use Dxn\DebugViewer\ProblemFinder;
use PHPUnit\Framework\TestCase;

final class ProblemFinderTest extends TestCase
{
    private const T = 1791429329.0;

    private static function query(int $pos, string $sql, string $status = 'success', float $ms = 1.0): array
    {
        return ['position' => $pos, 'sql' => $sql, 'status' => $status, 'line' => '/app/R.php:' . $pos, 'actions' => [['time' => self::T], ['time' => self::T + $ms / 1000]]];
    }

    /** @return list<array{severity: string, title: string, detail: string, tab: ?string, target: ?string}> */
    private static function find(array $data, array $meta = []): array
    {
        return ProblemFinder::find(new DumpView($data), $meta + ['status' => 200, 'durationMs' => 20.0, 'memoryMb' => 4.0]);
    }

    public function testHealthyRequestHasNoProblems(): void
    {
        $data = [
            DumpView::DB => ['queries' => ['a' => self::query(0, 'SELECT 1')]],
            DumpView::LOG => [['level' => 'info', 'message' => 'ok']],
        ];

        self::assertSame([], self::find($data));
        self::assertSame([], self::find([]));
    }

    public function testExceptionPointsAtTheStackTrace(): void
    {
        $p = self::find([DumpView::EXCEPTION => [
            ['class' => 'RuntimeException', 'message' => 'boom', 'file' => '/app/A.php', 'line' => 9, 'trace' => []],
            ['class' => 'PDOException', 'message' => 'inner', 'file' => '/app/B.php', 'line' => 1, 'trace' => []],
        ]])[0];

        self::assertSame('error', $p['severity']);
        self::assertSame('Exception: boom (+1 previous)', $p['title']);
        self::assertSame('RuntimeException at /app/A.php:9', $p['detail']);
        self::assertSame(['logs', null], [$p['tab'], $p['target']]);
    }

    public function testFailedQueriesLinkToTheFirstFailure(): void
    {
        $p = self::find([DumpView::DB => ['queries' => [
            'a' => self::query(0, 'SELECT ok'),
            'b' => self::query(1, "SELECT   *\nFROM nope", 'error'),
            'c' => self::query(2, 'SELECT bad', 'error'),
        ]]])[0];

        self::assertSame('2 failed queries', $p['title']);
        self::assertSame('SELECT * FROM nope  (/app/R.php:1)', $p['detail']);
        self::assertSame(['sql', 'q-1'], [$p['tab'], $p['target']]);
    }

    public function testSingularWording(): void
    {
        $p = self::find([DumpView::DB => ['queries' => ['a' => self::query(0, 'x', 'error')]]]);

        self::assertSame('1 failed query', $p[0]['title']);
    }

    public function testErrorLogsLinkToTheEntryAndWarningsAreSeparate(): void
    {
        $problems = self::find([DumpView::LOG => [
            ['level' => 'info', 'message' => 'fine'],
            ['level' => 'WARNING', 'message' => 'careful'],
            ['level' => 'critical', 'message' => 'on fire'],
            ['level' => 'error', 'message' => 'also bad'],
        ]]);

        self::assertSame(['2 error-level log entries', '1 warning log entry'], array_column($problems, 'title'));
        self::assertSame(['error', 'warning'], array_column($problems, 'severity'));
        self::assertSame('log-2', $problems[0]['target']);
        self::assertSame('on fire', $problems[0]['detail']);
        self::assertSame('log-1', $problems[1]['target']);
    }

    public function testStatusCodes(): void
    {
        self::assertSame('HTTP 500 Internal Server Error', self::find([], ['status' => 500])[0]['title']);
        $notFound = self::find([], ['status' => 404])[0];
        self::assertSame(['warning', 'HTTP 404 Not Found'], [$notFound['severity'], $notFound['title']]);
        self::assertSame([], self::find([], ['status' => 302]));
        self::assertSame([], self::find([], ['status' => 0]));
    }

    public function testRepeatedQueriesAreOneProblemLinkingToTheTopPattern(): void
    {
        $queries = [];
        foreach ([1, 2, 3] as $n) {
            $queries["h$n"] = self::query($n, "SELECT * FROM items WHERE o = $n", ms: 2.0);
        }

        $p = self::find([DumpView::DB => ['queries' => $queries]])[0];

        self::assertSame('warning', $p['severity']);
        self::assertSame('1 repeated query pattern (N+1 or duplicate)', $p['title']);
        self::assertStringContainsString('×3', $p['detail']);
        self::assertStringContainsString('avoidable', $p['detail']);
        self::assertSame(['sql', 'q-0'], [$p['tab'], $p['target']]);
    }

    public function testSlowQueriesLinkToTheSlowest(): void
    {
        $p = self::find([DumpView::DB => ['queries' => [
            'a' => self::query(0, 'SELECT fast', ms: 5.0),
            'b' => self::query(1, 'SELECT slow one', ms: 150.0),
            'c' => self::query(2, 'SELECT slower', ms: 400.0),
        ]]])[0];

        self::assertSame('2 queries slower than 100.0 ms', $p['title']);
        self::assertStringContainsString('SELECT slower', $p['detail']);
        self::assertSame('q-2', $p['target']);
    }

    public function testSlowRequestSeverityFollowsTheThresholds(): void
    {
        self::assertSame([], self::find([], ['durationMs' => 150.0]));
        self::assertSame('warning', self::find([], ['durationMs' => 300.0])[0]['severity']);
        $error = self::find([], ['durationMs' => 900.0])[0];
        self::assertSame(['error', 'Slow request: 900.0 ms', 'timeline'], [$error['severity'], $error['title'], $error['tab']]);
        self::assertSame('No database time recorded.', $error['detail']);
    }

    public function testSlowRequestSaysHowMuchWasSql(): void
    {
        $p = self::find([DumpView::DB => ['queries' => ['a' => self::query(0, 'SELECT 1', ms: 300.0)]]], ['durationMs' => 600.0])[0];

        self::assertSame('SQL accounts for 300.0 ms of it (50%).', $p['detail']);
    }

    public function testHighMemoryAndFailedServices(): void
    {
        $p = self::find([DumpView::SERVICE => [
            ['service' => 'X', 'class' => 'App\\X', 'method' => 'get', 'status' => 'failed', 'error' => 'object@RuntimeException#12', 'timeStart' => self::T, 'timeEnd' => self::T],
        ]], ['memoryMb' => 70.04]);

        self::assertSame(['1 container service failed', 'High memory use: 70.0 MB peak'], array_column($p, 'title'));
        self::assertSame('services', $p[0]['tab']);
        self::assertNull($p[1]['tab']);
    }

    public function testContainerNotFoundIsRoutineAndNotReported(): void
    {
        $notFound = ['service' => 'X', 'class' => 'Yiisoft\\Di\\Container', 'method' => 'get', 'status' => 'failed', 'error' => 'object@Yiisoft\\Di\\NotFoundException#604', 'timeStart' => self::T, 'timeEnd' => self::T];

        self::assertSame([], self::find([DumpView::SERVICE => [$notFound, $notFound]]));
    }

    public function testErrorsComeBeforeWarningsKeepingTheirOrder(): void
    {
        $p = self::find([
            DumpView::LOG => [['level' => 'warning', 'message' => 'w'], ['level' => 'error', 'message' => 'e']],
            DumpView::DB => ['queries' => ['a' => self::query(0, 'SELECT slow', ms: 200.0)]],
        ], ['status' => 500, 'durationMs' => 700.0]);

        self::assertSame(['error', 'error', 'error', 'warning', 'warning'], array_column($p, 'severity'));
        self::assertSame(
            ['1 error-level log entry', 'HTTP 500 Internal Server Error', 'Slow request: 700.0 ms', '1 warning log entry', '1 query slower than 100.0 ms'],
            array_column($p, 'title'),
        );
    }
}
