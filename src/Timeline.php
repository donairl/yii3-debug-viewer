<?php

declare(strict_types=1);

namespace Dxn\DebugViewer;

use function array_column;
use function array_map;
use function count;
use function in_array;
use function is_string;
use function mb_strimwidth;
use function min;
use function max;
use function preg_replace;
use function strrpos;
use function strtolower;
use function substr;
use function usort;

/**
 * Lays everything that happened during a request on one time axis: SQL and
 * container services as spans, events, log entries and exceptions as points.
 *
 * Offsets are milliseconds from the start of the request, so a template only
 * needs to turn them into percentages of `totalMs`.
 */
final class Timeline
{
    public const QUERY = 'query';
    public const SERVICE = 'service';
    public const EVENT = 'event';
    public const LOG = 'log';
    public const EXCEPTION = 'exception';

    /**
     * @param float $minServiceMs Services faster than this are counted in `hiddenServices`
     *                            instead of listed: a request resolves dozens of them in microseconds.
     *
     * @return array{
     *     totalMs: float,
     *     items: list<array{
     *         type: string,
     *         label: string,
     *         detail: string,
     *         startMs: float,
     *         durationMs: ?float,
     *         status: string,
     *         ref: ?int,
     *         flag: ?string
     *     }>,
     *     counts: array<string, int>,
     *     hiddenServices: int
     * }
     */
    public static function build(DumpView $view, float $minServiceMs = 0.5): array
    {
        $queries = $view->queries();
        $flags = QueryAnalyzer::analyze($queries)['flags'];

        /** @var list<array{type: string, label: string, detail: string, start: float, end: ?float, status: string, ref: ?int, flag: ?string}> $raw */
        $raw = [];

        foreach ($queries as $i => $query) {
            if ($query['start'] === null || $query['end'] === null) {
                continue;
            }
            $raw[] = [
                'type' => self::QUERY,
                'label' => mb_strimwidth((string)preg_replace('/\s+/', ' ', (string)$query['sql']), 0, 120, '…'),
                'detail' => (string)$query['line'],
                'start' => $query['start'],
                'end' => $query['end'],
                'status' => $query['status'] === 'success' ? 'ok' : 'error',
                'ref' => $i,
                'flag' => $flags[$i]['kind'] ?? null,
            ];
        }

        $hiddenServices = 0;
        foreach ($view->services() as $service) {
            if ($service['timeStart'] === null) {
                continue;
            }
            $durationMs = $service['durationMs'] ?? 0.0;
            $failed = $service['status'] !== 'success' && $service['status'] !== 'unknown';
            if ($durationMs < $minServiceMs && !$failed) {
                $hiddenServices++;
                continue;
            }
            $raw[] = [
                'type' => self::SERVICE,
                'label' => self::shortName($service['class']) . '::' . $service['method'] . '(' . self::firstArgument($service['arguments']) . ')',
                'detail' => $service['error'] ?? $service['service'],
                'start' => $service['timeStart'],
                'end' => $service['timeEnd'],
                'status' => $failed ? 'error' : 'ok',
                'ref' => null,
                'flag' => null,
            ];
        }

        foreach ($view->events() as $event) {
            if ($event['time'] === null) {
                continue;
            }
            $raw[] = [
                'type' => self::EVENT,
                'label' => self::shortName($event['name']),
                'detail' => $event['name'],
                'start' => $event['time'],
                'end' => null,
                'status' => 'ok',
                'ref' => null,
                'flag' => null,
            ];
        }

        foreach ($view->logs() as $log) {
            if ($log['time'] === null) {
                continue;
            }
            $level = strtolower($log['level']);
            $raw[] = [
                'type' => self::LOG,
                'label' => mb_strimwidth($level . ': ' . preg_replace('/\s+/', ' ', $log['message']), 0, 120, '…'),
                'detail' => (string)$log['line'],
                'start' => $log['time'],
                'end' => null,
                'status' => in_array($level, ['error', 'critical', 'alert', 'emergency'], true)
                    ? 'error'
                    : ($level === 'warning' ? 'warn' : 'ok'),
                'ref' => null,
                'flag' => null,
            ];
        }

        foreach ($view->exceptionTimes() as $time) {
            $raw[] = [
                'type' => self::EXCEPTION,
                'label' => 'exception',
                'detail' => '',
                'start' => $time,
                'end' => null,
                'status' => 'error',
                'ref' => null,
                'flag' => null,
            ];
        }

        return self::normalize($raw, $view->requestWindow(), $hiddenServices);
    }

    /**
     * @param list<array{type: string, label: string, detail: string, start: float, end: ?float, status: string, ref: ?int, flag: ?string}> $raw
     * @param array{0: float, 1: float}|null $window
     */
    private static function normalize(array $raw, ?array $window, int $hiddenServices): array
    {
        if ($raw === [] && $window === null) {
            return ['totalMs' => 0.0, 'items' => [], 'counts' => [], 'hiddenServices' => $hiddenServices];
        }

        $starts = array_column($raw, 'start');
        $ends = array_map(static fn(array $r): float => $r['end'] ?? $r['start'], $raw);
        if ($window !== null) {
            $starts[] = $window[0];
            $ends[] = $window[1];
        }

        $origin = min($starts);
        $totalMs = (max($ends) - $origin) * 1000;

        usort($raw, static fn(array $a, array $b): int => $a['start'] <=> $b['start']);

        $items = [];
        $counts = [];
        foreach ($raw as $r) {
            $counts[$r['type']] = ($counts[$r['type']] ?? 0) + 1;
            $items[] = [
                'type' => $r['type'],
                'label' => $r['label'],
                'detail' => $r['detail'],
                'startMs' => ($r['start'] - $origin) * 1000,
                'durationMs' => $r['end'] === null ? null : max(0.0, ($r['end'] - $r['start']) * 1000),
                'status' => $r['status'],
                'ref' => $r['ref'],
                'flag' => $r['flag'],
            ];
        }

        return ['totalMs' => $totalMs, 'items' => $items, 'counts' => $counts, 'hiddenServices' => $hiddenServices];
    }

    /**
     * The first scalar argument, shortened: `Container::get(ResponseFactoryInterface)`
     * says far more than `Container::get()`.
     *
     * @param array<mixed> $arguments
     */
    private static function firstArgument(array $arguments): string
    {
        $first = $arguments[0] ?? null;

        return is_string($first) ? self::shortName($first) : '';
    }

    private static function shortName(string $class): string
    {
        $pos = strrpos($class, '\\');

        return $pos === false ? $class : substr($class, $pos + 1);
    }
}
