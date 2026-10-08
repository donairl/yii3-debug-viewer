<?php

declare(strict_types=1);

namespace Dxn\DebugViewer\Tests;

use Dxn\DebugViewer\QueryAnalyzer;
use PHPUnit\Framework\TestCase;

final class QueryAnalyzerTest extends TestCase
{
    private static function q(string $sql, float $ms = 1.0, string $line = '/app/src/Repo.php:10'): array
    {
        return ['sql' => $sql, 'durationMs' => $ms, 'line' => $line];
    }

    public function testFingerprintIgnoresValuesAndFormatting(): void
    {
        self::assertSame(
            QueryAnalyzer::fingerprint("SELECT * FROM t WHERE id = 1 AND name = 'a''b'"),
            QueryAnalyzer::fingerprint("select *   from t\nwhere id = 22 and name = 'zzz'"),
        );
        self::assertSame(
            QueryAnalyzer::fingerprint('SELECT * FROM t WHERE id IN (1, 2, 3)'),
            QueryAnalyzer::fingerprint('SELECT * FROM t WHERE id IN (9)'),
        );
        self::assertSame(
            QueryAnalyzer::fingerprint('SELECT * FROM t WHERE id = :qp0'),
            QueryAnalyzer::fingerprint('SELECT * FROM t WHERE id = $1'),
        );
        self::assertNotSame(
            QueryAnalyzer::fingerprint('SELECT * FROM a WHERE id = 1'),
            QueryAnalyzer::fingerprint('SELECT * FROM b WHERE id = 1'),
        );
    }

    public function testPostgresCastsAreNotTreatedAsPlaceholders(): void
    {
        self::assertSame(
            'select a::int from t where b = ?',
            QueryAnalyzer::fingerprint('SELECT a::int FROM t WHERE b = :qp0'),
        );
    }

    public function testDigitsInsideIdentifiersAreKept(): void
    {
        self::assertNotSame(
            QueryAnalyzer::fingerprint('SELECT * FROM t1 WHERE a = 1'),
            QueryAnalyzer::fingerprint('SELECT * FROM t2 WHERE a = 1'),
        );
    }

    public function testDetectsNPlusOne(): void
    {
        $r = QueryAnalyzer::analyze([
            self::q('SELECT * FROM orders'),
            self::q('SELECT * FROM items WHERE order_id = 1', 2.0),
            self::q('SELECT * FROM items WHERE order_id = 2', 3.0),
            self::q('SELECT * FROM items WHERE order_id = 3', 4.0),
        ]);

        self::assertCount(1, $r['groups']);
        $g = $r['groups'][0];
        self::assertSame(QueryAnalyzer::N_PLUS_ONE, $g['kind']);
        self::assertSame(3, $g['count']);
        self::assertSame([1, 2, 3], $g['indexes']);
        self::assertEqualsWithDelta(9.0, $g['totalMs'], 0.001);
        self::assertEqualsWithDelta(5.0, $g['wastedMs'], 0.001);
        self::assertSame('/app/src/Repo.php:10', $g['caller']);
        self::assertSame([1, 2, 3], array_keys($r['flags']));
        self::assertSame(['kind' => 'n+1', 'count' => 3], $r['flags'][2]);
    }

    public function testTwoDifferentValuesIsNotYetNPlusOne(): void
    {
        $r = QueryAnalyzer::analyze([
            self::q('SELECT * FROM t WHERE id = 1'),
            self::q('SELECT * FROM t WHERE id = 2'),
        ]);

        self::assertSame([], $r['groups']);
        self::assertSame([], $r['flags']);
    }

    public function testDetectsExactDuplicates(): void
    {
        $r = QueryAnalyzer::analyze([
            self::q('SELECT * FROM t WHERE id = 1', 4.0),
            self::q('SELECT 1'),
            self::q('SELECT * FROM t WHERE id = 1', 6.0),
        ]);

        self::assertCount(1, $r['groups']);
        $g = $r['groups'][0];
        self::assertSame(QueryAnalyzer::DUPLICATE, $g['kind']);
        self::assertSame([0, 2], $g['indexes']);
        self::assertEqualsWithDelta(6.0, $g['wastedMs'], 0.001, 'only the repeat run is waste');
        self::assertSame([0, 2], array_keys($r['flags']));
        self::assertEqualsWithDelta(6.0, $r['wastedMs'], 0.001);
    }

    public function testGroupsAreSortedByWastedTime(): void
    {
        $r = QueryAnalyzer::analyze([
            self::q('SELECT a FROM x WHERE i = 1', 1.0), self::q('SELECT a FROM x WHERE i = 2', 1.0), self::q('SELECT a FROM x WHERE i = 3', 1.0),
            self::q('SELECT b FROM y WHERE i = 1', 50.0), self::q('SELECT b FROM y WHERE i = 2', 50.0), self::q('SELECT b FROM y WHERE i = 3', 50.0),
        ]);

        self::assertStringContainsString('from y', $r['groups'][0]['fingerprint']);
    }

    public function testMissingDurationsCountAsZero(): void
    {
        $r = QueryAnalyzer::analyze([
            ['sql' => 'SELECT 1 WHERE a = 1'], ['sql' => 'SELECT 1 WHERE a = 2'], ['sql' => 'SELECT 1 WHERE a = 3'],
        ]);

        self::assertSame(0.0, $r['groups'][0]['wastedMs']);
        self::assertSame('', $r['groups'][0]['caller']);
    }

    public function testEmptyInput(): void
    {
        self::assertSame(['groups' => [], 'flags' => [], 'wastedMs' => 0], QueryAnalyzer::analyze([]));
    }
}
