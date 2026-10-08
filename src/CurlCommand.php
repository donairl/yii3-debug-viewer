<?php

declare(strict_types=1);

namespace Dxn\DebugViewer;

use function array_push;
use function explode;
use function implode;
use function in_array;
use function is_array;
use function mb_check_encoding;
use function parse_url;
use function preg_match;
use function str_contains;
use function str_replace;
use function sprintf;
use function str_starts_with;
use function strlen;
use function strtoupper;
use function strtolower;

/**
 * Rebuilds a recorded request as a `curl` command, to replay it from a shell.
 *
 * By default credentials are masked by the Redactor: Authorization and similar
 * headers, cookie values, and password/token-like fields in the URL, form and
 * JSON body. The names stay, so the command shows what was sent and where to
 * put a real value.
 */
final class CurlCommand
{
    /** Sent by curl itself, or stale once the command is replayed. */
    private const DROPPED_HEADERS = ['host', 'content-length', 'connection', 'transfer-encoding', 'expect', 'accept-encoding'];

    /**
     * @return array{command: string, notes: list<string>}|null Null when the dump holds no request to rebuild.
     */
    public static function fromView(DumpView $view, bool $redact = true, ?Redactor $redactor = null): ?array
    {
        $request = $view->request();
        $parsed = $view->parsedRequest();
        $method = (string)($request['requestMethod'] ?? '');
        if ($method === '' && $parsed['startLine'] !== '') {
            $method = explode(' ', $parsed['startLine'], 2)[0];
        }
        if ($method === '') {
            return null;
        }

        return self::build(
            $method,
            self::url($request, $parsed),
            $parsed['headers'],
            $parsed['body'],
            $redact,
            $redactor,
        );
    }

    /**
     * @param array<string, list<string>> $headers
     *
     * @return array{command: string, notes: list<string>}
     */
    public static function build(string $method, ?string $url, array $headers, string $body = '', bool $redact = true, ?Redactor $redactor = null): array
    {
        $redactor ??= new Redactor();
        $notes = [];
        $method = strtoupper($method);

        if ($url === null) {
            $url = 'http://localhost/';
            $notes[] = 'The dump has no host, so the URL falls back to http://localhost/.';
        }

        $contentType = '';
        $compressed = false;
        $args = [];
        foreach ($headers as $name => $values) {
            $lower = strtolower($name);
            if ($lower === 'accept-encoding') {
                $compressed = true;
            }
            if (in_array($lower, self::DROPPED_HEADERS, true) && !($lower === 'host' && self::hostDiffers($values, $url))) {
                continue;
            }
            if ($lower === 'content-type') {
                $contentType = $values[0] ?? '';
            }
            foreach ($values as $value) {
                $args[] = '-H ' . self::quote($name . ': ' . ($redact ? $redactor->header($name, $value) : $value));
            }
        }

        if ($redact) {
            $url = $redactor->url($url);
        }

        $line = [self::quote($url)];

        $implied = $body !== '' ? 'POST' : 'GET';
        if ($method === 'HEAD') {
            $line[] = '--head';
        } elseif ($method !== $implied) {
            $line[] = '-X ' . self::quote($method);
        }
        if ($compressed) {
            $line[] = '--compressed';
        }
        array_push($line, ...$args);

        if ($body !== '') {
            if (!mb_check_encoding($body, 'UTF-8') || str_contains($body, "\0")) {
                $notes[] = sprintf('The request body is binary (%d bytes) and is not included.', strlen($body));
            } else {
                if ($redact && str_contains(strtolower($contentType), 'multipart/')) {
                    $notes[] = 'Multipart bodies are not masked: check the fields before sharing this command.';
                }
                $line[] = '--data-raw ' . self::quote($redact ? $redactor->body($contentType, $body) : $body);
            }
        }

        return [
            'command' => 'curl ' . implode(" \\\n  ", $line),
            'notes' => $notes,
        ];
    }

    /**
     * @param array<string, mixed> $request
     * @param array{startLine: string, headers: array<string, list<string>>, body: string} $parsed
     */
    private static function url(array $request, array $parsed): ?string
    {
        $recorded = (string)($request['requestUrl'] ?? '');
        if (preg_match('#^https?://#i', $recorded) === 1) {
            return $recorded;
        }

        $host = '';
        foreach ($parsed['headers'] as $name => $values) {
            if (strtolower($name) === 'host') {
                $host = $values[0] ?? '';
            }
        }
        if ($host === '') {
            return null;
        }

        $target = explode(' ', $parsed['startLine'])[1] ?? (string)($request['requestPath'] ?? '/');

        return 'http://' . $host . (str_starts_with($target, '/') ? $target : '/' . $target);
    }

    /**
     * @param list<string> $hostHeader
     */
    private static function hostDiffers(array $hostHeader, string $url): bool
    {
        $parts = parse_url($url);
        $urlHost = strtolower(($parts['host'] ?? '') . (isset($parts['port']) ? ':' . $parts['port'] : ''));

        return $urlHost !== '' && strtolower($hostHeader[0] ?? '') !== $urlHost;
    }

    /**
     * POSIX single-quoting: safe for any content, including newlines and `$`.
     */
    private static function quote(string $value): string
    {
        return "'" . str_replace("'", "'\\''", $value) . "'";
    }
}
