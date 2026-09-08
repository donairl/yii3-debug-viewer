<?php

declare(strict_types=1);

namespace Dxn\DebugViewer;

use Yiisoft\Aliases\Aliases;

use function count;
use function is_array;

/**
 * Reads the dumps yii-debug writes to @runtime/debug/<date>/<request-id>/.
 *
 * Listing only touches summary.json (a couple of KB), never data.json, so the
 * index stays fast with thousands of dumps on disk.
 */
final class DumpStorage
{
    public function __construct(
        private readonly Aliases $aliases,
        private readonly string $path = '@runtime/debug',
    ) {}

    /**
     * Newest requests first.
     *
     * @return list<array<string, mixed>>
     */
    public function list(int $limit = 100): array
    {
        $rows = [];

        foreach ($this->dumpDirectories() as $dir) {
            $summary = $this->readJson($dir . '/summary.json');
            if ($summary === null) {
                continue;
            }

            $rows[] = $this->row(basename($dir), $summary, filemtime($dir) ?: 0);

            if (count($rows) >= $limit) {
                break;
            }
        }

        return $rows;
    }

    /**
     * @return array{id: string, meta: array<string, mixed>, summary: array, data: array}|null
     */
    public function get(string $id): ?array
    {
        if (!self::isValidId($id)) {
            return null;
        }

        foreach ($this->dumpDirectories() as $dir) {
            if (basename($dir) !== $id) {
                continue;
            }

            $summary = $this->readJson($dir . '/summary.json');
            if ($summary === null) {
                return null;
            }

            return [
                'id' => $id,
                'meta' => $this->row($id, $summary, filemtime($dir) ?: 0),
                'summary' => is_array($summary['summary'] ?? null) ? $summary['summary'] : [],
                'data' => $this->readJson($dir . '/data.json') ?? [],
            ];
        }

        return null;
    }

    public static function isValidId(string $id): bool
    {
        return preg_match('/^[A-Za-z0-9]{1,64}$/', $id) === 1;
    }

    /**
     * Flattened list row: what the index table needs, nothing more.
     *
     * @return array<string, mixed>
     */
    private function row(string $id, array $summary, int $mtime): array
    {
        $s = is_array($summary['summary'] ?? null) ? $summary['summary'] : [];

        $request = $s['Yiisoft\\Yii\\Debug\\Collector\\Web\\RequestCollector'] ?? [];
        $appInfo = $s['Yiisoft\\Yii\\Debug\\Collector\\Web\\WebAppInfoCollector'] ?? [];
        $db = $s['Yiisoft\\Db\\Debug\\DatabaseCollector'] ?? [];
        $log = $s['Yiisoft\\Yii\\Debug\\Collector\\LogCollector'] ?? [];
        $exceptions = $s['Yiisoft\\Yii\\Debug\\Collector\\ExceptionCollector'] ?? [];
        $router = $s['Yiisoft\\Router\\Debug\\RouterCollector'] ?? [];

        return [
            'id' => $id,
            'time' => (float)($appInfo['request']['startTime'] ?? $mtime),
            'method' => (string)($request['request']['method'] ?? '-'),
            'path' => (string)($request['request']['path'] ?? ''),
            'url' => (string)($request['request']['url'] ?? ''),
            'status' => (int)($request['response']['statusCode'] ?? 0),
            'queries' => (int)($db['queries']['total'] ?? 0),
            'queryErrors' => (int)($db['queries']['error'] ?? 0),
            'logs' => (int)($log['total'] ?? 0),
            'exceptions' => is_array($exceptions) ? count($exceptions) : 0,
            'durationMs' => ((float)($appInfo['request']['processingTime'] ?? 0)) * 1000,
            'memoryMb' => ((float)($appInfo['memory']['peakUsage'] ?? 0)) / 1048576,
            'action' => (string)($router['action'] ?? ''),
            'routeName' => (string)($router['name'] ?? ''),
        ];
    }

    /**
     * Dump directories, newest first, walked date-descending.
     *
     * @return iterable<string>
     */
    private function dumpDirectories(): iterable
    {
        $base = $this->aliases->get($this->path);
        if (!is_dir($base)) {
            return;
        }

        $dates = glob($base . '/*', GLOB_ONLYDIR) ?: [];
        rsort($dates, SORT_STRING);

        foreach ($dates as $date) {
            $dumps = glob($date . '/*', GLOB_ONLYDIR) ?: [];
            usort($dumps, static fn(string $a, string $b) => (filemtime($b) ?: 0) <=> (filemtime($a) ?: 0));

            yield from $dumps;
        }
    }

    private function readJson(string $file): ?array
    {
        if (!is_file($file)) {
            return null;
        }

        $json = file_get_contents($file);
        if ($json === false) {
            return null;
        }

        $decoded = json_decode($json, true);

        return is_array($decoded) ? $decoded : null;
    }
}
