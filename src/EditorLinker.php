<?php

declare(strict_types=1);

namespace Dxn\DebugViewer;

use function array_map;
use function explode;
use function implode;
use function is_string;
use function ltrim;
use function preg_match;
use function rawurlencode;
use function rtrim;
use function str_contains;
use function str_replace;
use function str_starts_with;
use function strlen;
use function substr;
use function strtolower;
use function uksort;

/**
 * Builds "open in editor" links for the file:line locations in a dump.
 *
 * A dump records paths as the application saw them. When the app runs in a
 * container or VM those are not paths on the machine the editor runs on, so
 * `pathMap` rewrites a prefix (`/app` => `/home/me/project`) before linking.
 *
 * Disabled (no links, plain text) until an editor is configured.
 */
final readonly class EditorLinker
{
    /** Editor URL handlers. `{file}` and `{line}` are filled in. */
    public const PRESETS = [
        'phpstorm' => 'phpstorm://open?file={file}&line={line}',
        'idea' => 'idea://open?file={file}&line={line}',
        'vscode' => 'vscode://file/{file}:{line}',
        'cursor' => 'cursor://file/{file}:{line}',
        'sublime' => 'subl://open?url=file://{file}&line={line}',
        'textmate' => 'txmt://open?url=file://{file}&line={line}',
    ];

    private string $template;

    /** @var array<string, string> */
    private array $pathMap;

    /**
     * @param string $editor A preset name from PRESETS, or a custom URL containing `{file}`
     *                       (and optionally `{line}`). Anything else disables links.
     * @param array<string, string> $pathMap Application path prefix => local path prefix.
     */
    public function __construct(string $editor = '', array $pathMap = [])
    {
        $this->template = self::PRESETS[strtolower($editor)]
            ?? (str_contains($editor, '{file}') ? $editor : '');

        $map = [];
        foreach ($pathMap as $from => $to) {
            if (is_string($to) && rtrim((string)$from, '/\\') !== '') {
                $map[rtrim((string)$from, '/\\')] = rtrim($to, '/\\');
            }
        }
        // Longest prefix first, so `/app/vendor` wins over `/app`.
        uksort($map, static fn(string $a, string $b): int => strlen($b) <=> strlen($a));
        $this->pathMap = $map;
    }

    public function enabled(): bool
    {
        return $this->template !== '';
    }

    /**
     * @param string|int|null $line Missing or malformed lines open at line 1.
     */
    public function url(string $file, string|int|null $line = null): ?string
    {
        if ($this->template === '' || !self::isAbsolute($file)) {
            return null;
        }

        $path = str_replace('\\', '/', $this->map($file));
        $encoded = implode('/', array_map(rawurlencode(...), explode('/', $path)));
        $lineNumber = preg_match('/^\d+$/', (string)$line) === 1 && (int)$line > 0 ? (string)(int)$line : '1';

        return str_replace(['{file}', '{line}'], [$encoded, $lineNumber], $this->template);
    }

    /**
     * For `path:line` strings such as the caller recorded for a query.
     */
    public function urlForLocation(string $location): ?string
    {
        [$file, $line] = self::splitLocation($location);

        return $this->url($file, $line);
    }

    /**
     * @return array{0: string, 1: ?string}
     */
    public static function splitLocation(string $location): array
    {
        if (preg_match('/^(.+?):(\d+)$/', $location, $m) === 1) {
            return [$m[1], $m[2]];
        }

        return [$location, null];
    }

    private function map(string $file): string
    {
        foreach ($this->pathMap as $from => $to) {
            if ($file === $from) {
                return $to;
            }
            if (str_starts_with($file, $from . '/') || str_starts_with($file, $from . '\\')) {
                return $to . '/' . ltrim(str_replace('\\', '/', substr($file, strlen($from))), '/');
            }
        }

        return $file;
    }

    private static function isAbsolute(string $file): bool
    {
        return str_starts_with($file, '/') || preg_match('/^[A-Za-z]:[\\\\\/]/', $file) === 1;
    }
}
