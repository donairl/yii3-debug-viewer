<?php

declare(strict_types=1);

namespace Dxn\DebugViewer;

use function array_pad;
use function count;
use function explode;
use function htmlspecialchars;
use function is_array;
use function is_bool;
use function is_float;
use function is_int;
use function is_object;
use function is_string;
use function json_decode;
use function json_encode;
use function json_last_error;
use function json_last_error_msg;
use function mb_check_encoding;
use function mb_strcut;
use function mb_strlen;
use function mb_substr;
use function number_format;
use function preg_match;
use function sprintf;
use function str_contains;
use function strlen;
use function strtolower;
use function trim;
use function urldecode;
use function var_export;

use const ENT_QUOTES;
use const ENT_SUBSTITUTE;
use const JSON_BIGINT_AS_STRING;
use const JSON_ERROR_NONE;
use const JSON_PRESERVE_ZERO_FRACTION;
use const JSON_PRETTY_PRINT;
use const JSON_UNESCAPED_SLASHES;
use const JSON_UNESCAPED_UNICODE;

/**
 * Renders an HTTP body for reading: JSON as a collapsible tree, forms as a
 * table, anything else as text, binary as a one-line note. Chosen by
 * Content-Type, with a sniff for JSON sent without one.
 *
 * Everything is built on the server from escaped text and native `<details>`,
 * so the tree works without JavaScript. Nothing is ever executed or embedded:
 * HTML bodies are shown as source, not rendered.
 */
final class BodyView
{
    /** Most text shown. The rest is counted, and Copy is not offered for a cut body. */
    public const MAX_SHOWN_BYTES = 200_000;

    /** Largest body decoded as JSON. */
    public const MAX_JSON_BYTES = 1_000_000;

    /** More nodes than this and the tree is skipped: it would be a wall of elements. */
    public const MAX_TREE_NODES = 5_000;

    /** Strings in the tree are cut here; Pretty and Raw show them whole. */
    public const MAX_TREE_STRING = 400;

    private const MAX_FORM_FIELDS = 500;

    /** Past this many nodes only the top level starts open. */
    private const OPEN_ALL_UP_TO = 300;

    private const BINARY_TYPES = '#^(?:image|audio|video|font)/|^application/(?:octet-stream|pdf|zip|gzip|x-gzip|x-tar|vnd\.|x-7z)#';

    /**
     * @param array<string, list<string>> $headers
     */
    public static function render(array $headers, string $body): string
    {
        if (trim($body) === '') {
            return '';
        }

        $type = self::contentType($headers);
        $size = strlen($body);

        if (preg_match(self::BINARY_TYPES, $type) === 1 || !mb_check_encoding($body, 'UTF-8') || str_contains($body, "\0")) {
            return self::note(sprintf('Binary body (%s%s), not shown.', self::bytes($size), $type !== '' ? ', ' . $type : ''));
        }

        $cut = $size > self::MAX_SHOWN_BYTES;
        $raw = $cut ? mb_strcut($body, 0, self::MAX_SHOWN_BYTES, 'UTF-8') : $body;

        $isJsonType = preg_match('#(?:^|[/+])json$#', $type) === 1;
        $mightBeJson = $isJsonType || (($type === '' || $type === 'text/plain') && preg_match('/^\s*[\[{]/', $body) === 1);
        $note = null;

        if ($mightBeJson && $size <= self::MAX_JSON_BYTES) {
            $value = json_decode($body, false, 512, JSON_BIGINT_AS_STRING);
            if (json_last_error() === JSON_ERROR_NONE && (is_array($value) || is_object($value))) {
                return self::json($value, $raw, $size, $cut);
            }
            $note = $isJsonType ? 'Declared as JSON but invalid: ' . json_last_error_msg() . '.' : null;
        } elseif ($isJsonType) {
            $note = sprintf('JSON over %s is shown as text.', self::bytes(self::MAX_JSON_BYTES));
        }

        if ($type === 'application/x-www-form-urlencoded') {
            return self::form($body, $raw, $size, $cut);
        }

        return self::wrap('TEXT', $size, [['raw', 'Raw', self::pre($raw, $cut, $size)]], 'raw', $cut, $note);
    }

    /**
     * @param array<string, list<string>> $headers
     */
    private static function contentType(array $headers): string
    {
        foreach ($headers as $name => $values) {
            if (strtolower($name) === 'content-type') {
                return strtolower(trim(explode(';', $values[0] ?? '')[0]));
            }
        }

        return '';
    }

    private static function json(array|object $value, string $raw, int $size, bool $cut): string
    {
        $nodes = self::countNodes($value);
        $pretty = (string)json_encode($value, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION);
        $prettyCut = strlen($pretty) > self::MAX_SHOWN_BYTES;
        if ($prettyCut) {
            $pretty = mb_strcut($pretty, 0, self::MAX_SHOWN_BYTES, 'UTF-8');
        }

        $panes = [];
        $first = 'pretty';
        $note = null;
        if ($nodes <= self::MAX_TREE_NODES) {
            $panes[] = ['tree', 'Tree', self::tree($value, $nodes)];
            $first = 'tree';
        } else {
            $note = sprintf('%s nodes: too many for a tree, shown as text.', number_format($nodes));
        }
        $panes[] = ['pretty', 'Pretty', self::pre($pretty, $prettyCut, strlen($pretty))];
        $panes[] = ['raw', 'Raw', self::pre($raw, $cut, $size)];

        return self::wrap('JSON', $size, $panes, $first, $cut, $note, $first === 'tree');
    }

    private static function form(string $body, string $raw, int $size, bool $cut): string
    {
        $rows = '';
        $fields = 0;
        foreach (explode('&', $body) as $pair) {
            if ($pair === '') {
                continue;
            }
            if (++$fields > self::MAX_FORM_FIELDS) {
                $rows .= '<tr><td colspan="2" class="text-muted">… more fields in Raw</td></tr>';
                break;
            }
            [$name, $value] = array_pad(explode('=', $pair, 2), 2, '');
            $rows .= '<tr><td class="body-form-name">' . self::e(urldecode($name)) . '</td><td class="body-form-value">' . self::e(urldecode($value)) . '</td></tr>';
        }

        $table = '<table class="dash-table body-form"><thead><tr><th style="width: 220px;">Field</th><th>Value</th></tr></thead><tbody>' . $rows . '</tbody></table>';

        return self::wrap('FORM', $size, [['table', 'Table', $table], ['raw', 'Raw', self::pre($raw, $cut, $size)]], 'table', $cut);
    }

    /**
     * @param list<array{0: string, 1: string, 2: string}> $panes mode, label, html
     */
    private static function wrap(string $kind, int $size, array $panes, string $first, bool $cut, ?string $note = null, bool $tree = false): string
    {
        $buttons = '';
        $html = '';
        foreach ($panes as [$mode, $label, $content]) {
            $buttons .= '<button type="button" class="body-mode' . ($mode === $first ? ' active' : '') . '" data-body-mode="' . $mode . '">' . $label . '</button>';
            $html .= '<div class="body-pane" data-pane="' . $mode . '"' . ($mode === $first ? '' : ' hidden') . '>' . $content . '</div>';
        }

        $tools = $tree
            ? '<button type="button" class="dash-btn body-tool" data-body-fold="open">Expand all</button><button type="button" class="dash-btn body-tool" data-body-fold="close">Collapse all</button>'
            : '';
        // a cut body would copy as a different body
        $copy = $cut ? '' : '<button type="button" class="dash-btn body-tool" data-body-copy>Copy</button>';

        return '<div class="body-view">'
            . '<div class="body-bar"><span class="dash-badge">' . $kind . '</span><span class="text-muted body-size">' . self::bytes($size) . '</span>'
            . (count($panes) > 1 ? '<span class="body-modes">' . $buttons . '</span>' : '')
            . '<span class="body-tools">' . $tools . $copy . '</span></div>'
            . ($note !== null ? '<div class="text-muted body-note">' . self::e($note) . '</div>' : '')
            . $html
            . '</div>';
    }

    private static function pre(string $text, bool $cut, int $fullSize): string
    {
        return '<pre class="body-text">' . self::e($text) . '</pre>'
            . ($cut ? '<div class="text-muted body-note">… cut after ' . self::bytes(self::MAX_SHOWN_BYTES) . ' of ' . self::bytes($fullSize) . '.</div>' : '');
    }

    private static function note(string $text): string
    {
        return '<div class="body-view"><div class="text-muted body-note">' . self::e($text) . '</div></div>';
    }

    private static function tree(array|object $value, int $nodes): string
    {
        return '<div class="jt">' . self::node($value, null, 0, $nodes > self::OPEN_ALL_UP_TO ? 1 : 2) . '</div>';
    }

    /**
     * @param int $openDepth Containers at a depth below this start open.
     */
    private static function node(mixed $value, ?string $key, int $depth, int $openDepth): string
    {
        $label = $key === null ? '' : '<span class="jt-key">' . self::e($key) . '</span><span class="jt-sep">: </span>';

        if (is_array($value) || is_object($value)) {
            $items = (array)$value;
            $isList = is_array($value);
            $open = $isList ? '[' : '{';
            $close = $isList ? ']' : '}';

            if ($items === []) {
                return '<div class="jt-row">' . $label . '<span class="jt-meta">' . $open . $close . '</span></div>';
            }

            $children = '';
            foreach ($items as $k => $child) {
                $children .= self::node($child, (string)$k, $depth + 1, $openDepth);
            }

            return '<details class="jt-node"' . ($depth < $openDepth ? ' open' : '') . '><summary>' . $label
                . '<span class="jt-meta">' . $open . ' ' . count($items) . ' ' . ($isList ? (count($items) === 1 ? 'item' : 'items') : (count($items) === 1 ? 'key' : 'keys')) . ' ' . $close . '</span></summary>'
                . '<div class="jt-children">' . $children . '</div></details>';
        }

        return '<div class="jt-row">' . $label . self::scalar($value) . '</div>';
    }

    private static function scalar(mixed $value): string
    {
        if ($value === null) {
            return '<span class="jt-null">null</span>';
        }
        if (is_bool($value)) {
            return '<span class="jt-bool">' . ($value ? 'true' : 'false') . '</span>';
        }
        if (is_int($value) || is_float($value)) {
            return '<span class="jt-num">' . self::e(var_export($value, true)) . '</span>';
        }

        $text = (string)$value;
        $length = mb_strlen($text);
        $shown = $length > self::MAX_TREE_STRING ? mb_substr($text, 0, self::MAX_TREE_STRING) : $text;

        return '<span class="jt-str">"' . self::e($shown) . '"</span>'
            . ($length > self::MAX_TREE_STRING ? '<span class="jt-more"> … +' . number_format($length - self::MAX_TREE_STRING) . ' chars (see Pretty)</span>' : '');
    }

    private static function countNodes(mixed $value): int
    {
        if (!is_array($value) && !is_object($value)) {
            return 1;
        }

        $n = 1;
        foreach ((array)$value as $child) {
            $n += self::countNodes($child);
            if ($n > self::MAX_TREE_NODES) {
                return $n; // enough to know
            }
        }

        return $n;
    }

    private static function bytes(int $bytes): string
    {
        return DumpView::formatBytes($bytes);
    }

    private static function e(string $text): string
    {
        return htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
