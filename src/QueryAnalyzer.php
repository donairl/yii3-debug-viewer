<?php

declare(strict_types=1);

namespace Dxn\DebugViewer;

use function array_column;
use function array_count_values;
use function array_filter;
use function array_key_first;
use function array_sum;
use function arsort;
use function count;
use function max;
use function preg_replace;
use function strtolower;
use function trim;
use function usort;

/**
 * Finds the two query patterns that quietly cost the most in a request:
 *
 *  - N+1: one statement shape executed many times with different values,
 *    usually a query inside a loop.
 *  - duplicate: the exact same statement (same values) executed again.
 *
 * Works on the rows from DumpView::queries(), whose `sql` already has the
 * bound values substituted.
 */
final class QueryAnalyzer
{
    /** Executions of one statement shape before it counts as N+1. */
    public const N_PLUS_ONE_THRESHOLD = 3;

    public const N_PLUS_ONE = 'n+1';
    public const DUPLICATE = 'duplicate';

    /**
     * @param list<array<string, mixed>> $queries
     *
     * @return array{
     *     groups: list<array{
     *         kind: string,
     *         fingerprint: string,
     *         sql: string,
     *         count: int,
     *         totalMs: float,
     *         maxMs: float,
     *         wastedMs: float,
     *         caller: string,
     *         indexes: list<int>
     *     }>,
     *     flags: array<int, array{kind: string, count: int}>,
     *     wastedMs: float
     * }
     */
    public static function analyze(array $queries): array
    {
        /** @var array<string, list<int>> $byShape */
        $byShape = [];
        foreach ($queries as $i => $query) {
            $byShape[self::fingerprint((string)($query['sql'] ?? ''))][] = $i;
        }

        $groups = [];
        $flags = [];

        foreach ($byShape as $fingerprint => $indexes) {
            if (count($indexes) < 2) {
                continue;
            }

            $sqls = [];
            foreach ($indexes as $i) {
                $sqls[$i] = (string)($queries[$i]['sql'] ?? '');
            }
            $occurrences = array_count_values($sqls);

            $isNPlusOne = count($indexes) >= self::N_PLUS_ONE_THRESHOLD && count($occurrences) >= 2;
            $repeatedSql = array_filter($occurrences, static fn(int $n): bool => $n >= 2);

            if (!$isNPlusOne && $repeatedSql === []) {
                continue;
            }

            $kind = $isNPlusOne ? self::N_PLUS_ONE : self::DUPLICATE;
            $durations = [];
            foreach ($indexes as $i) {
                $durations[$i] = (float)($queries[$i]['durationMs'] ?? 0.0);
            }
            $totalMs = array_sum($durations);
            $maxMs = max($durations);

            if ($isNPlusOne) {
                $wastedMs = $totalMs - $maxMs;
                $flagged = $indexes;
            } else {
                $seen = [];
                $wastedMs = 0.0;
                $flagged = [];
                foreach ($indexes as $i) {
                    if (!isset($repeatedSql[$sqls[$i]])) {
                        continue;
                    }
                    $flagged[] = $i;
                    if (isset($seen[$sqls[$i]])) {
                        $wastedMs += $durations[$i];
                    }
                    $seen[$sqls[$i]] = true;
                }
            }

            foreach ($flagged as $i) {
                $flags[$i] = ['kind' => $kind, 'count' => count($indexes)];
            }

            $groups[] = [
                'kind' => $kind,
                'fingerprint' => $fingerprint,
                'sql' => $sqls[$indexes[0]],
                'count' => count($indexes),
                'totalMs' => $totalMs,
                'maxMs' => $maxMs,
                'wastedMs' => $wastedMs,
                'caller' => self::commonCaller($queries, $indexes),
                'indexes' => $flagged,
            ];
        }

        usort(
            $groups,
            static fn(array $a, array $b): int => [$b['wastedMs'], $b['count']] <=> [$a['wastedMs'], $a['count']],
        );

        return [
            'groups' => $groups,
            'flags' => $flags,
            'wastedMs' => array_sum(array_column($groups, 'wastedMs')),
        ];
    }

    /**
     * Statement shape: literals, bind placeholders and IN lists collapsed so
     * `WHERE id = 1` and `WHERE id = 2` compare equal.
     */
    public static function fingerprint(string $sql): string
    {
        $shape = strtolower($sql);
        $shape = (string)preg_replace("/'(?:[^'\\\\]|\\\\.|'')*'/", '?', $shape);
        $shape = (string)preg_replace('/"(?:[^"\\\\]|\\\\.)*"(?=\s*[,)]|\s*$)/', '?', $shape);
        // Placeholders before numbers, or `$1` would become `$?`. `(?<!:)` keeps `x::int` casts intact.
        $shape = (string)preg_replace('/(?<![:\w]):\w+|\$\d+/', '?', $shape);
        $shape = (string)preg_replace('/(?<![\w.])-?\d+(?:\.\d+)?(?![\w.])/', '?', $shape);
        $shape = (string)preg_replace('/\(\s*\?(?:\s*,\s*\?)+\s*\)/', '(?)', $shape);

        return trim((string)preg_replace('/\s+/', ' ', $shape));
    }

    /**
     * @param list<array<string, mixed>> $queries
     * @param list<int> $indexes
     */
    private static function commonCaller(array $queries, array $indexes): string
    {
        $callers = [];
        foreach ($indexes as $i) {
            $line = (string)($queries[$i]['line'] ?? '');
            if ($line !== '') {
                $callers[] = $line;
            }
        }
        if ($callers === []) {
            return '';
        }

        $counts = array_count_values($callers);
        arsort($counts);

        return (string)array_key_first($counts);
    }
}
