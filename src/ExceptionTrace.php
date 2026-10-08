<?php

declare(strict_types=1);

namespace Dxn\DebugViewer;

use function array_values;
use function count;
use function is_array;
use function is_scalar;
use function preg_match;
use function preg_split;
use function str_contains;
use function trim;

/**
 * Turns one serialized exception from ExceptionCollector into frames a human
 * can scan: where it was thrown, the call chain, and which frames are vendor
 * code to skip over.
 *
 * Input is defensive on purpose. The collector stores Throwable::getTrace()
 * as-is, but `args` may be stripped (zend.exception_ignore_args) or the trace
 * missing entirely, in which case traceAsString is parsed instead.
 */
final class ExceptionTrace
{
    public const THROWN_HERE = 'thrown here';

    /**
     * @param array<string, mixed> $exception
     *
     * @return array{
     *     class: string,
     *     message: string,
     *     code: string,
     *     file: string,
     *     line: string,
     *     frames: list<array{index: int, file: string, line: string, call: string, vendor: bool}>,
     *     traceAsString: string
     * }
     */
    public static function describe(array $exception): array
    {
        $file = self::str($exception['file'] ?? '');
        $line = self::str($exception['line'] ?? '');
        $traceAsString = self::str($exception['traceAsString'] ?? '');

        $frames = [];
        if ($file !== '') {
            $frames[] = ['index' => 0, 'file' => $file, 'line' => $line, 'call' => self::THROWN_HERE, 'vendor' => self::isVendor($file)];
        }

        $trace = $exception['trace'] ?? null;
        $parsed = is_array($trace) && $trace !== []
            ? self::fromTrace($trace)
            : self::fromString($traceAsString);

        foreach ($parsed as $frame) {
            $frames[] = ['index' => count($frames)] + $frame;
        }

        return [
            'class' => self::str($exception['class'] ?? $exception['type'] ?? 'Exception'),
            'message' => self::str($exception['message'] ?? ''),
            'code' => self::str($exception['code'] ?? ''),
            'file' => $file,
            'line' => $line,
            'frames' => $frames,
            'traceAsString' => $traceAsString,
        ];
    }

    /**
     * Groups consecutive frames by vendor/application so a long framework
     * stack can be folded away.
     *
     * @param list<array{index: int, file: string, line: string, call: string, vendor: bool}> $frames
     *
     * @return list<array{vendor: bool, frames: list<array{index: int, file: string, line: string, call: string, vendor: bool}>}>
     */
    public static function segments(array $frames): array
    {
        $segments = [];
        foreach ($frames as $frame) {
            // The throw site is where to start reading, even inside vendor code,
            // so it never folds away with the vendor frames that follow it.
            $vendor = $frame['vendor'] && !self::isThrowSite($frame);
            $last = count($segments) - 1;
            if ($last >= 0 && $segments[$last]['vendor'] === $vendor) {
                $segments[$last]['frames'][] = $frame;
                continue;
            }
            $segments[] = ['vendor' => $vendor, 'frames' => [$frame]];
        }

        return $segments;
    }

    /**
     * @param array{index: int, call: string, ...<string, mixed>} $frame
     */
    public static function isThrowSite(array $frame): bool
    {
        return $frame['index'] === 0 && $frame['call'] === self::THROWN_HERE;
    }

    public static function isVendor(string $file): bool
    {
        return str_contains($file, '/vendor/') || str_contains($file, '\\vendor\\');
    }

    /**
     * @param array<mixed> $trace
     *
     * @return list<array{file: string, line: string, call: string, vendor: bool}>
     */
    private static function fromTrace(array $trace): array
    {
        $frames = [];
        foreach ($trace as $item) {
            if (!is_array($item)) {
                continue;
            }

            $file = self::str($item['file'] ?? '');
            $function = self::str($item['function'] ?? '');
            $call = self::str($item['class'] ?? '') . self::str($item['type'] ?? '') . $function;

            $frames[] = [
                'file' => $file,
                'line' => self::str($item['line'] ?? ''),
                'call' => $function === '' ? $call : $call . '()',
                'vendor' => $file === '' || self::isVendor($file),
            ];
        }

        return $frames;
    }

    /**
     * Parses Throwable::getTraceAsString() lines:
     * `#0 /app/src/Foo.php(12): App\Foo->bar()`, `#1 [internal function]: ...`, `#2 {main}`.
     *
     * @return list<array{file: string, line: string, call: string, vendor: bool}>
     */
    private static function fromString(string $traceAsString): array
    {
        if (trim($traceAsString) === '') {
            return [];
        }

        $frames = [];
        foreach (preg_split('/\r?\n/', $traceAsString) ?: [] as $row) {
            if (preg_match('/^#\d+ (.+?)\((\d+)\): (.*)$/', $row, $m) === 1) {
                $frames[] = ['file' => $m[1], 'line' => $m[2], 'call' => $m[3], 'vendor' => self::isVendor($m[1])];
            } elseif (preg_match('/^#\d+ \[internal function\]: (.*)$/', $row, $m) === 1) {
                $frames[] = ['file' => '', 'line' => '', 'call' => $m[1], 'vendor' => true];
            } elseif (preg_match('/^#\d+ \{main\}$/', $row) === 1) {
                $frames[] = ['file' => '', 'line' => '', 'call' => '{main}', 'vendor' => true];
            }
        }

        return $frames;
    }

    private static function str(mixed $value): string
    {
        return is_scalar($value) ? (string)$value : '';
    }
}
