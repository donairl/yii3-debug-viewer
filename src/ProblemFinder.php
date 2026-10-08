<?php

declare(strict_types=1);

namespace Dxn\DebugViewer;

use function array_filter;
use function array_values;
use function count;
use function in_array;
use function mb_strimwidth;
use function number_format;
use function preg_replace;
use function round;
use function sprintf;
use function strtolower;
use function usort;

/**
 * Answers "what is wrong with this request?" in one list: everything the
 * other tabs show that deserves attention, worst first, each pointing at the
 * place to look.
 *
 * Thresholds are deliberately plain constants. They are about what a developer
 * would want flagged during debugging, not an SLO.
 */
final class ProblemFinder
{
    public const ERROR = 'error';
    public const WARNING = 'warning';

    public const SLOW_QUERY_MS = 100.0;
    public const SLOW_REQUEST_WARN_MS = 200.0;
    public const SLOW_REQUEST_ERROR_MS = 500.0;
    public const MEMORY_WARN_MB = 64.0;

    private const ERROR_LEVELS = ['error', 'critical', 'alert', 'emergency'];

    /**
     * @param array<string, mixed> $meta The list row for this request (status, durationMs, memoryMb).
     *
     * @return list<array{
     *     severity: string,
     *     title: string,
     *     detail: string,
     *     tab: ?string,
     *     target: ?string
     * }> Errors first, then warnings, each group in a fixed order.
     */
    public static function find(DumpView $view, array $meta): array
    {
        $problems = [];
        $queries = $view->queries();

        $exceptions = $view->exceptionDetails();
        if ($exceptions !== []) {
            $first = $exceptions[0];
            $where = $first['file'] !== '' ? ' at ' . $first['file'] . ($first['line'] !== '' ? ':' . $first['line'] : '') : '';
            $previous = count($exceptions) > 1 ? sprintf(' (+%d previous)', count($exceptions) - 1) : '';
            $problems[] = self::problem(
                self::ERROR,
                'Exception: ' . self::short($first['message'] !== '' ? $first['message'] : $first['class'], 160) . $previous,
                $first['class'] . $where,
                'logs',
                null,
            );
        }

        $failed = [];
        foreach ($queries as $i => $q) {
            if ($q['status'] !== 'success') {
                $failed[] = $i;
            }
        }
        if ($failed !== []) {
            $q = $queries[$failed[0]];
            $problems[] = self::problem(
                self::ERROR,
                sprintf('%d failed %s', count($failed), count($failed) === 1 ? 'query' : 'queries'),
                self::short((string)$q['sql']) . ($q['line'] !== '' ? '  (' . $q['line'] . ')' : ''),
                'sql',
                'q-' . $failed[0],
            );
        }

        $logs = $view->logs();
        $errorLogs = [];
        $warningLogs = [];
        foreach ($logs as $i => $log) {
            $level = strtolower($log['level']);
            if (in_array($level, self::ERROR_LEVELS, true)) {
                $errorLogs[] = $i;
            } elseif ($level === 'warning') {
                $warningLogs[] = $i;
            }
        }
        if ($errorLogs !== []) {
            $problems[] = self::problem(
                self::ERROR,
                sprintf('%d error-level log %s', count($errorLogs), count($errorLogs) === 1 ? 'entry' : 'entries'),
                self::short($logs[$errorLogs[0]]['message']),
                'logs',
                'log-' . $errorLogs[0],
            );
        }

        $status = (int)($meta['status'] ?? 0);
        if ($status >= 500) {
            $problems[] = self::problem(self::ERROR, sprintf('HTTP %d %s', $status, DumpView::statusText($status)), 'The response was a server error.', 'request', null);
        }

        $duration = (float)($meta['durationMs'] ?? 0);
        if ($duration > self::SLOW_REQUEST_ERROR_MS) {
            $problems[] = self::slowRequest(self::ERROR, $duration, $view);
        }

        $services = array_values(array_filter($view->services(), DumpView::serviceFailed(...)));

        if ($services !== []) {
            $problems[] = self::problem(
                self::WARNING,
                sprintf('%d container %s failed', count($services), count($services) === 1 ? 'service' : 'services'),
                self::short($services[0]['class'] . '::' . $services[0]['method'] . '(): ' . $services[0]['error']),
                'services',
                null,
            );
        }

        if ($warningLogs !== []) {
            $problems[] = self::problem(
                self::WARNING,
                sprintf('%d warning log %s', count($warningLogs), count($warningLogs) === 1 ? 'entry' : 'entries'),
                self::short($logs[$warningLogs[0]]['message']),
                'logs',
                'log-' . $warningLogs[0],
            );
        }

        if ($status >= 400 && $status < 500) {
            $problems[] = self::problem(self::WARNING, sprintf('HTTP %d %s', $status, DumpView::statusText($status)), 'The response was a client error.', 'request', null);
        }

        $insights = QueryAnalyzer::analyze($queries);
        if ($insights['groups'] !== []) {
            $top = $insights['groups'][0];
            $problems[] = self::problem(
                self::WARNING,
                sprintf('%d repeated query %s (N+1 or duplicate)', count($insights['groups']), count($insights['groups']) === 1 ? 'pattern' : 'patterns'),
                ($insights['wastedMs'] > 0 ? '~' . Template::formatMs($insights['wastedMs']) . ' avoidable. ' : '') . '×' . $top['count'] . ' ' . self::short($top['sql'], 100),
                'sql',
                'q-' . $top['indexes'][0],
            );
        }

        $slowest = null;
        $slowCount = 0;
        foreach ($queries as $i => $q) {
            if ($q['durationMs'] !== null && (float)$q['durationMs'] > self::SLOW_QUERY_MS) {
                $slowCount++;
                if ($slowest === null || $q['durationMs'] > $queries[$slowest]['durationMs']) {
                    $slowest = $i;
                }
            }
        }
        if ($slowest !== null) {
            $problems[] = self::problem(
                self::WARNING,
                sprintf('%d %s slower than %s', $slowCount, $slowCount === 1 ? 'query' : 'queries', Template::formatMs(self::SLOW_QUERY_MS)),
                'Slowest ' . Template::formatMs((float)$queries[$slowest]['durationMs']) . ': ' . self::short((string)$queries[$slowest]['sql'], 110),
                'sql',
                'q-' . $slowest,
            );
        }

        if ($duration > self::SLOW_REQUEST_WARN_MS && $duration <= self::SLOW_REQUEST_ERROR_MS) {
            $problems[] = self::slowRequest(self::WARNING, $duration, $view);
        }

        $memory = (float)($meta['memoryMb'] ?? 0);
        if ($memory >= self::MEMORY_WARN_MB) {
            $problems[] = self::problem(self::WARNING, sprintf('High memory use: %s MB peak', number_format($memory, 1)), 'Close to a typical 128 MB `memory_limit`.', null, null);
        }

        // errors first; usort is stable, so each group keeps the order above
        usort($problems, static fn(array $a, array $b): int => ($a['severity'] === self::ERROR ? 0 : 1) <=> ($b['severity'] === self::ERROR ? 0 : 1));

        return $problems;
    }

    private static function slowRequest(string $severity, float $durationMs, DumpView $view): array
    {
        $sql = $view->totalQueryDurationMs();

        return self::problem(
            $severity,
            'Slow request: ' . Template::formatMs($durationMs),
            $sql > 0 ? sprintf('SQL accounts for %s of it (%d%%).', Template::formatMs($sql), (int)round($sql / $durationMs * 100)) : 'No database time recorded.',
            'timeline',
            null,
        );
    }

    /**
     * @return array{severity: string, title: string, detail: string, tab: ?string, target: ?string}
     */
    private static function problem(string $severity, string $title, string $detail, ?string $tab, ?string $target): array
    {
        return ['severity' => $severity, 'title' => $title, 'detail' => $detail, 'tab' => $tab, 'target' => $target];
    }

    private static function short(string $text, int $width = 140): string
    {
        return mb_strimwidth((string)preg_replace('/\s+/', ' ', $text), 0, $width, '…');
    }
}
