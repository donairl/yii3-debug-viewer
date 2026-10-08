<?php

declare(strict_types=1);

namespace Dxn\DebugViewer;

use function array_key_first;
use function array_pop;
use function array_replace_recursive;
use function count;
use function explode;
use function in_array;
use function is_array;
use function json_decode;
use function parse_str;
use function preg_match;
use function preg_quote;
use function preg_split;
use function str_contains;
use function strcasecmp;
use function strlen;
use function strpos;
use function strtolower;
use function substr;
use function trim;
use function urlencode;

/**
 * The GET, POST and SESSION variables of a recorded request, as PHP would
 * have seen them.
 *
 * GET and POST are rebuilt from the recorded raw request, which the reader
 * has already masked, so credentials stay masked here. SESSION is only
 * available when the host application records it in its own collector:
 * yiisoft/yii-debug does not.
 */
final class RequestParams
{
    private const MAX_SESSION_DEPTH = 8;

    /**
     * Query string of the request target; falls back to the recorded query.
     *
     * @return array<array-key, mixed>
     */
    public static function get(string $startLine, string $recordedQuery = ''): array
    {
        $query = $recordedQuery;
        $target = explode(' ', $startLine, 3)[1] ?? '';
        if (str_contains($target, '?')) {
            $query = explode('#', substr($target, (int)strpos($target, '?') + 1), 2)[0];
        }

        return self::parse($query);
    }

    /**
     * Fields of a form body (urlencoded or multipart) or the top level of a JSON body.
     * Uploaded files are listed by name and never read.
     *
     * @param array<string, list<string>> $headers
     *
     * @return array{data: array<array-key, mixed>, source: string}|null null when the body carries no fields
     */
    public static function post(array $headers, string $body): ?array
    {
        if (trim($body) === '') {
            return null;
        }

        $type = '';
        $boundary = '';
        foreach ($headers as $name => $values) {
            if (strcasecmp($name, 'Content-Type') === 0) {
                $type = strtolower(trim(explode(';', $values[0] ?? '')[0]));
                if (preg_match('/boundary=(?:"([^"]+)"|([^;\s]+))/i', $values[0] ?? '', $m) === 1) {
                    $boundary = $m[1] !== '' ? $m[1] : $m[2];
                }
            }
        }

        if ($type === 'application/x-www-form-urlencoded') {
            return ['data' => self::parse($body), 'source' => 'form'];
        }

        if ($type === 'multipart/form-data' && $boundary !== '') {
            return ['data' => self::multipart($body, $boundary), 'source' => 'multipart'];
        }

        if (preg_match('#(?:^|[/+])json$#', $type) === 1) {
            $value = json_decode($body, true, 512);
            if (is_array($value)) {
                return ['data' => $value, 'source' => 'json'];
            }
        }

        return null;
    }

    /**
     * The first collector that looks like a session collector, if the host recorded one.
     *
     * @param array<string, mixed> $collectors
     *
     * @return array<array-key, mixed>|null
     */
    public static function session(array $collectors): ?array
    {
        foreach ($collectors as $name => $data) {
            if (is_array($data) && preg_match('/session/i', (string)$name) === 1) {
                return self::unwrapSession($data);
            }
        }

        return null;
    }

    /**
     * Collectors tend to wrap the variables ({"data": {...}} or {"session": {...}}).
     *
     * @param array<array-key, mixed> $data
     *
     * @return array<array-key, mixed>
     */
    private static function unwrapSession(array $data): array
    {
        for ($depth = 0; $depth < self::MAX_SESSION_DEPTH; $depth++) {
            if (count($data) !== 1) {
                break;
            }
            $only = $data[array_key_first($data)];
            if (!is_array($only) || !in_array(strtolower((string)array_key_first($data)), ['data', 'session', 'values'], true)) {
                break;
            }
            $data = $only;
        }

        return $data;
    }

    /**
     * @return array<array-key, mixed>
     */
    private static function parse(string $query): array
    {
        if ($query === '') {
            return [];
        }
        parse_str($query, $out);

        return $out;
    }

    /**
     * @return array<array-key, mixed>
     */
    private static function multipart(string $body, string $boundary): array
    {
        $out = [];
        $parts = preg_split('/\r?\n?--' . preg_quote($boundary, '/') . '(?:--)?\r?\n?/', $body) ?: [];
        array_pop($parts);

        foreach ($parts as $part) {
            $pieces = preg_split('/\r?\n\r?\n/', $part, 2);
            if ($pieces === false || count($pieces) < 2) {
                continue;
            }
            if (preg_match('/\bname="([^"]*)"/i', $pieces[0], $name) !== 1) {
                continue;
            }

            $value = $pieces[1];
            if (preg_match('/\bfilename="([^"]*)"/i', $pieces[0], $file) === 1) {
                $value = '[file] ' . $file[1] . ' (' . DumpView::formatBytes(strlen($value)) . ')';
            }

            // `a[b]=c` style names nest, like they do in $_POST
            parse_str(urlencode($name[1]) . '=' . urlencode($value), $field);
            $out = array_replace_recursive($out, $field);
        }

        return $out;
    }
}
