<?php

declare(strict_types=1);

namespace Dxn\DebugViewer;

use Yiisoft\Aliases\Aliases;

use function count;
use function file_get_contents;
use function filemtime;
use function filesize;
use function glob;
use function is_array;
use function is_dir;
use function is_file;
use function json_decode;
use function json_last_error;
use function json_last_error_msg;
use function preg_match;
use function rsort;
use function scandir;
use function sprintf;

use const GLOB_ONLYDIR;
use const JSON_ERROR_NONE;
use const SCANDIR_SORT_DESCENDING;
use const SORT_STRING;

/**
 * Reads the dumps yii-debug writes to @runtime/debug/<date>/<request-id>/.
 *
 * Listing only touches summary.json (a couple of KB), never data.json, so the
 * index stays fast with thousands of dumps on disk.
 *
 * Request ids come from `uniqid('', true)`: the first 13 hex digits are the
 * time in seconds and microseconds, so a bigger id is a newer dump. That lets
 * "newest first" come from the directory names alone, without a stat() per
 * dump. A lookup by id goes straight to `<date>/<id>` without listing anything.
 */
final class DumpStorage
{
    /** Largest summary.json worth decoding. Real ones are a couple of KB. */
    private const MAX_SUMMARY_BYTES = 4 * 1048576;

    /**
     * @param int $maxDumpBytes data.json bigger than this is not decoded, since decoding
     *                          needs several times the file size in memory. 0 means no limit.
     */
    public function __construct(
        private readonly Aliases $aliases,
        private readonly string $path = '@runtime/debug',
        private readonly int $maxDumpBytes = 16 * 1048576,
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
            [$summary] = $this->readJson($dir . '/summary.json', self::MAX_SUMMARY_BYTES);
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
     * `warning` says why `data` is empty when the dump could not be loaded in
     * full (too large, missing or corrupt data.json). The summary still shows.
     *
     * @return array{id: string, meta: array<string, mixed>, summary: array, data: array, warning: ?string}|null
     */
    public function get(string $id): ?array
    {
        $dir = $this->findDirectory($id);
        if ($dir === null) {
            return null;
        }

        [$summary] = $this->readJson($dir . '/summary.json', self::MAX_SUMMARY_BYTES);
        if ($summary === null) {
            return null;
        }

        [$data, $problem] = $this->readJson($dir . '/data.json', $this->maxDumpBytes, 'data.json');

        return [
            'id' => $id,
            'meta' => $this->row($id, $summary, filemtime($dir) ?: 0),
            'summary' => is_array($summary['summary'] ?? null) ? $summary['summary'] : [],
            'data' => $data ?? [],
            'warning' => $problem,
        ];
    }

    public static function isValidId(string $id): bool
    {
        return preg_match('/^[A-Za-z0-9]{1,64}$/D', $id) === 1;
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
     * Dump directories, newest first: dates descending, then ids descending.
     * Names only, no stat() per dump, and lazily: a list(100) over thousands
     * of dumps reads one directory listing, not every dump.
     *
     * @return iterable<string>
     */
    private function dumpDirectories(): iterable
    {
        foreach ($this->dateDirectories() as $date) {
            // `.` and `..` sort last when descending, and are skipped below
            foreach (scandir($date, SCANDIR_SORT_DESCENDING) ?: [] as $name) {
                if ($name === '.' || $name === '..' || !is_file($date . '/' . $name . '/summary.json')) {
                    continue;
                }

                yield $date . '/' . $name;
            }
        }
    }

    /**
     * Where one dump lives, without listing the dumps themselves.
     */
    private function findDirectory(string $id): ?string
    {
        // The id becomes part of a path: only plain tokens get through.
        if (!self::isValidId($id)) {
            return null;
        }

        foreach ($this->dateDirectories() as $date) {
            if (is_dir($date . '/' . $id)) {
                return $date . '/' . $id;
            }
        }

        return null;
    }

    /**
     * Date folders, newest first.
     *
     * @return list<string>
     */
    private function dateDirectories(): array
    {
        $base = $this->aliases->get($this->path);
        if (!is_dir($base)) {
            return [];
        }

        $dates = glob($base . '/*', GLOB_ONLYDIR) ?: [];
        rsort($dates, SORT_STRING);

        return $dates;
    }

    /**
     * @param string $label Names the file in the returned problem; null skips reporting one.
     *
     * @return array{0: ?array, 1: ?string} The decoded file and, when it could not be loaded, why.
     */
    private function readJson(string $file, int $maxBytes = 0, ?string $label = null): array
    {
        if (!is_file($file)) {
            return [null, $label === null ? null : sprintf('%s is missing, so request details are not available.', $label)];
        }

        $size = filesize($file);
        if ($maxBytes > 0 && $size !== false && $size > $maxBytes) {
            return [null, $label === null ? null : sprintf(
                '%s is too large to load (%s, limit %s). Raise `maxDumpSize` in the viewer params to open it.',
                $label,
                DumpView::formatBytes($size),
                DumpView::formatBytes($maxBytes),
            )];
        }

        $json = file_get_contents($file);
        if ($json === false) {
            return [null, $label === null ? null : sprintf('%s could not be read.', $label)];
        }

        $decoded = json_decode($json, true);
        if (!is_array($decoded)) {
            $why = json_last_error() !== JSON_ERROR_NONE ? json_last_error_msg() : 'not a JSON object';

            return [null, $label === null ? null : sprintf('%s could not be decoded (%s).', $label, $why)];
        }

        return [$decoded, null];
    }
}
