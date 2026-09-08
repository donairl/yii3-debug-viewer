<?php

declare(strict_types=1);

namespace Dxn\DebugViewer;

use function count;
use function is_array;
use function is_scalar;
use function max;
use function min;

/**
 * Typed access to one data.json dump.
 *
 * Collector payloads are defensive on purpose: yii-debug is installed from
 * dev-master, so a collector may change shape between updates. A section that
 * cannot be read renders as raw JSON instead of breaking the page.
 */
final class DumpView
{
    public const DB = 'Yiisoft\\Db\\Debug\\DatabaseCollector';
    public const LOG = 'Yiisoft\\Yii\\Debug\\Collector\\LogCollector';
    public const EXCEPTION = 'Yiisoft\\Yii\\Debug\\Collector\\ExceptionCollector';
    public const REQUEST = 'Yiisoft\\Yii\\Debug\\Collector\\Web\\RequestCollector';
    public const ROUTER = 'Yiisoft\\Router\\Debug\\RouterCollector';
    public const EVENT = 'Yiisoft\\Yii\\Debug\\Collector\\EventCollector';
    public const SERVICE = 'Yiisoft\\Yii\\Debug\\Collector\\ServiceCollector';
    public const TIMELINE = 'Yiisoft\\Yii\\Debug\\Collector\\TimelineCollector';
    public const WEB_APP_INFO = 'Yiisoft\\Yii\\Debug\\Collector\\Web\\WebAppInfoCollector';

    public function __construct(private readonly array $data) {}

    /**
     * Executed SQL, in execution order.
     *
     * @return list<array<string, mixed>>
     */
    public function queries(): array
    {
        $queries = $this->collector(self::DB)['queries'] ?? [];
        if (!is_array($queries)) {
            return [];
        }

        $rows = [];
        foreach ($queries as $query) {
            if (!is_array($query)) {
                continue;
            }

            $rows[] = [
                'position' => (int)($query['position'] ?? 0),
                'status' => (string)($query['status'] ?? 'unknown'),
                'sql' => (string)($query['rawSql'] ?? $query['sql'] ?? ''),
                'line' => (string)($query['line'] ?? ''),
                'rows' => $query['rowsNumber'] ?? null,
                'durationMs' => self::duration($query['actions'] ?? []),
                'params' => is_array($query['params'] ?? null) ? $query['params'] : [],
            ];
        }

        usort($rows, static fn(array $a, array $b) => $a['position'] <=> $b['position']);

        return $rows;
    }

    public function totalQueryDurationMs(): float
    {
        $total = 0.0;
        foreach ($this->queries() as $q) {
            if (isset($q['durationMs']) && $q['durationMs'] !== null) {
                $total += (float)$q['durationMs'];
            }
        }
        return $total;
    }

    /**
     * @return list<array{level: string, message: string, time: ?float}>
     */
    public function logs(): array
    {
        $entries = $this->collector(self::LOG);

        $rows = [];
        foreach ($entries as $entry) {
            if (!is_array($entry)) {
                continue;
            }

            $message = $entry['message'] ?? '';
            $rows[] = [
                'level' => (string)($entry['level'] ?? 'info'),
                'message' => is_scalar($message) ? (string)$message : self::json($message),
                'time' => isset($entry['time']) ? (float)$entry['time'] : null,
            ];
        }

        return $rows;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function exceptions(): array
    {
        return array_values(array_filter($this->collector(self::EXCEPTION), 'is_array'));
    }

    public function request(): array
    {
        return $this->collector(self::REQUEST);
    }

    /**
     * Parsed request HTTP headers and raw body.
     *
     * @return array{startLine: string, headers: array<string, list<string>>, body: string}
     */
    public function parsedRequest(): array
    {
        $raw = (string)($this->collector(self::REQUEST)['requestRaw'] ?? '');
        return self::parseHttpRaw($raw);
    }

    /**
     * Parsed response HTTP headers and raw body.
     *
     * @return array{startLine: string, headers: array<string, list<string>>, body: string}
     */
    public function parsedResponse(): array
    {
        $raw = (string)($this->collector(self::REQUEST)['responseRaw'] ?? '');
        return self::parseHttpRaw($raw);
    }

    public function route(): array
    {
        $route = $this->collector(self::ROUTER)['currentRoute'] ?? [];

        return is_array($route) ? $route : [];
    }

    /**
     * @return list<string>
     */
    public function routesTree(): array
    {
        $tree = $this->collector(self::ROUTER)['routesTree'] ?? [];

        return is_array($tree) ? array_values(array_filter($tree, 'is_string')) : [];
    }

    /**
     * Event details in dispatch order.
     *
     * @return list<array{name: string, event: string, file: string, line: string, time: ?float}>
     */
    public function events(): array
    {
        $rows = [];
        foreach ($this->collector(self::EVENT) as $event) {
            if (!is_array($event)) {
                continue;
            }
            $name = $event['name'] ?? $event['event'] ?? 'Unnamed Event';
            $rows[] = [
                'name' => is_scalar($name) ? (string)$name : self::json($name),
                'event' => (string)($event['event'] ?? ''),
                'file' => (string)($event['file'] ?? ''),
                'line' => (string)($event['line'] ?? ''),
                'time' => isset($event['time']) ? (float)$event['time'] : null,
            ];
        }

        return $rows;
    }

    /**
     * Container services resolved during the request.
     *
     * @return list<array<string, mixed>>
     */
    public function services(): array
    {
        $services = $this->collector(self::SERVICE);
        if (!is_array($services)) {
            return [];
        }

        $rows = [];
        foreach ($services as $s) {
            if (!is_array($s)) {
                continue;
            }
            $timeStart = isset($s['timeStart']) ? (float)$s['timeStart'] : null;
            $timeEnd = isset($s['timeEnd']) ? (float)$s['timeEnd'] : null;
            $durationMs = ($timeStart !== null && $timeEnd !== null) ? ($timeEnd - $timeStart) * 1000 : null;

            $rows[] = [
                'service' => (string)($s['service'] ?? ''),
                'class' => (string)($s['class'] ?? ''),
                'method' => (string)($s['method'] ?? ''),
                'arguments' => is_array($s['arguments'] ?? null) ? $s['arguments'] : [],
                'result' => $s['result'] ?? null,
                'status' => (string)($s['status'] ?? 'unknown'),
                'error' => isset($s['error']) && $s['error'] !== '' ? (string)$s['error'] : null,
                'timeStart' => $timeStart,
                'timeEnd' => $timeEnd,
                'durationMs' => $durationMs,
            ];
        }

        return $rows;
    }

    public function appInfo(): array
    {
        return $this->collector(self::WEB_APP_INFO);
    }

    /**
     * @return list<string>
     */
    public function collectorNames(): array
    {
        return array_keys($this->data);
    }

    public function raw(string $collector): string
    {
        return self::json($this->data[$collector] ?? null);
    }

    public static function json(mixed $value): string
    {
        return (string)json_encode($value, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
    }

    public static function statusText(int $status): string
    {
        return match ($status) {
            200 => 'OK',
            201 => 'Created',
            202 => 'Accepted',
            204 => 'No Content',
            301 => 'Moved Permanently',
            302 => 'Found',
            304 => 'Not Modified',
            307 => 'Temporary Redirect',
            308 => 'Permanent Redirect',
            400 => 'Bad Request',
            401 => 'Unauthorized',
            403 => 'Forbidden',
            404 => 'Not Found',
            405 => 'Method Not Allowed',
            408 => 'Request Timeout',
            409 => 'Conflict',
            422 => 'Unprocessable Entity',
            429 => 'Too Many Requests',
            500 => 'Internal Server Error',
            502 => 'Bad Gateway',
            503 => 'Service Unavailable',
            504 => 'Gateway Timeout',
            default => (string)$status,
        };
    }

    public static function highlightSql(string $sql): string
    {
        $escaped = htmlspecialchars($sql, ENT_QUOTES, 'UTF-8');
        
        // Highlight strings ('value')
        $escaped = (string)preg_replace('/(&#039;[^&#039;]*&#039;|\x27[^\x27]*\x27|&quot;[^&]*&quot;)/', '<span class="sql-str">$1</span>', $escaped);
        
        // Highlight parameter placeholders (:param)
        $escaped = (string)preg_replace('/(:[a-zA-Z0-9_]+)/', '<span class="sql-param">$1</span>', $escaped);
        
        // Highlight numeric literals
        $escaped = (string)preg_replace('/\b(\d+(?:\.\d+)?)\b/', '<span class="sql-num">$1</span>', $escaped);

        // Highlight SQL keywords
        $keywords = [
            'SELECT', 'FROM', 'WHERE', 'AND', 'OR', 'NOT', 'JOIN', 'LEFT JOIN', 'RIGHT JOIN',
            'INNER JOIN', 'OUTER JOIN', 'CROSS JOIN', 'ON', 'ORDER BY', 'GROUP BY', 'HAVING', 'LIMIT',
            'OFFSET', 'INSERT INTO', 'VALUES', 'UPDATE', 'SET', 'DELETE', 'AS', 'IN', 'NOT IN',
            'IS NULL', 'IS NOT NULL', 'LIKE', 'ILIKE', 'BETWEEN', 'CASE', 'WHEN',
            'THEN', 'ELSE', 'END', 'DISTINCT', 'COUNT', 'SUM', 'AVG', 'MIN', 'MAX',
            'ASC', 'DESC', 'UNION', 'ALL', 'EXISTS', 'CREATE', 'TABLE', 'DROP', 'ALTER', 'INDEX'
        ];
        $pattern = '/\b(' . implode('|', array_map('preg_quote', $keywords)) . ')\b/i';
        $escaped = (string)preg_replace_callback($pattern, static fn($m) => '<span class="sql-kw">' . strtoupper($m[0]) . '</span>', $escaped);

        return $escaped;
    }

    public static function formatBytes(int|float $bytes): string
    {
        if ($bytes <= 0) {
            return '0 B';
        }
        $units = ['B', 'KB', 'MB', 'GB'];
        $i = 0;
        $val = (float)$bytes;
        while ($val >= 1024 && $i < count($units) - 1) {
            $val /= 1024;
            $i++;
        }
        return number_format($val, $i === 0 ? 0 : 2) . ' ' . $units[$i];
    }

    private function collector(string $name): array
    {
        $value = $this->data[$name] ?? [];

        return is_array($value) ? $value : [];
    }

    /**
     * Milliseconds between the first and last recorded action of a query.
     */
    private static function duration(mixed $actions): ?float
    {
        if (!is_array($actions) || $actions === []) {
            return null;
        }

        $times = [];
        foreach ($actions as $action) {
            if (is_array($action) && isset($action['time'])) {
                $times[] = (float)$action['time'];
            }
        }

        if (count($times) < 2) {
            return null;
        }

        return (max($times) - min($times)) * 1000;
    }

    /**
     * @return array{startLine: string, headers: array<string, list<string>>, body: string}
     */
    private static function parseHttpRaw(string $raw): array
    {
        if (trim($raw) === '') {
            return ['startLine' => '', 'headers' => [], 'body' => ''];
        }

        $parts = explode("\r\n\r\n", $raw, 2);
        if (count($parts) < 2) {
            $parts = explode("\n\n", $raw, 2);
        }

        $headerLines = preg_split('/\r?\n/', $parts[0] ?? '') ?: [];
        $startLine = array_shift($headerLines) ?? '';
        $headers = [];

        foreach ($headerLines as $line) {
            if (str_contains($line, ':')) {
                [$k, $v] = explode(':', $line, 2);
                $k = trim($k);
                $v = trim($v);
                if ($k !== '') {
                    $headers[$k][] = $v;
                }
            }
        }

        return [
            'startLine' => $startLine,
            'headers' => $headers,
            'body' => $parts[1] ?? '',
        ];
    }
}
