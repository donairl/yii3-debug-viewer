<?php

declare(strict_types=1);

namespace Dxn\DebugViewer;

use function array_fill_keys;
use function htmlspecialchars;
use function preg_match_all;
use function strlen;
use function strtoupper;
use function substr;

use const ENT_QUOTES;
use const ENT_SUBSTITUTE;
use const PREG_OFFSET_CAPTURE;
use const PREG_SET_ORDER;

/**
 * Colours a SQL statement as HTML.
 *
 * It tokenizes the text once and escapes each piece itself, rather than
 * running regexes over already-escaped HTML. So a keyword inside a string or a
 * comment stays plain, `''` inside a string does not end it, and what is
 * shown is exactly the statement that ran (case included), only wrapped in
 * spans.
 */
final class SqlHighlighter
{
    /** Words worth colouring. Not a grammar: just the ones that make a query readable. */
    private const KEYWORDS = [
        'ALL', 'ALTER', 'AND', 'AS', 'ASC', 'BETWEEN', 'BY', 'CASE', 'CAST', 'COUNT', 'CREATE', 'CROSS', 'DELETE',
        'DESC', 'DISTINCT', 'DROP', 'ELSE', 'END', 'EXISTS', 'FALSE', 'FIRST', 'FROM', 'FULL', 'GROUP', 'HAVING',
        'ILIKE', 'IN', 'INDEX', 'INNER', 'INSERT', 'INTERVAL', 'INTO', 'IS', 'JOIN', 'LAST', 'LEFT', 'LIKE', 'LIMIT',
        'MAX', 'MIN', 'NOT', 'NULL', 'NULLS', 'OFFSET', 'ON', 'OR', 'ORDER', 'OUTER', 'OVER', 'PARTITION', 'RETURNING',
        'RIGHT', 'SELECT', 'SET', 'SUM', 'AVG', 'TABLE', 'THEN', 'TRUE', 'UNION', 'UPDATE', 'USING', 'VALUES', 'WHEN',
        'WHERE', 'WITH',
    ];

    private const TOKEN = '~
        (?<comment> --[^\n]* | /\*.*?(?:\*/|$) )
      | (?<string>  \'(?:[^\'\\\\]|\\\\.|\'\')*(?:\'|$) )
      | (?<ident>   "(?:[^"]|"")*(?:"|$) | `[^`]*(?:`|$) )
      | (?<param>   (?<![:\w]):[A-Za-z_]\w* | \$\d+ | \? )
      | (?<number>  \b\d+(?:\.\d+)?\b )
      | (?<word>    [A-Za-z_][\w$]* )
    ~xsu';

    /** @var array<string, true>|null */
    private static ?array $keywordSet = null;

    public static function html(string $sql): string
    {
        self::$keywordSet ??= array_fill_keys(self::KEYWORDS, true);

        if (preg_match_all(self::TOKEN, $sql, $matches, PREG_SET_ORDER | PREG_OFFSET_CAPTURE) === false) {
            return self::e($sql); // not valid UTF-8: show it plain rather than not at all
        }

        $out = '';
        $pos = 0;
        foreach ($matches as $m) {
            [$text, $offset] = $m[0];
            $out .= self::e(substr($sql, $pos, $offset - $pos));
            $pos = $offset + strlen($text);

            $class = null;
            foreach (['comment' => 'sql-com', 'string' => 'sql-str', 'ident' => 'sql-id', 'param' => 'sql-param', 'number' => 'sql-num'] as $name => $css) {
                if (isset($m[$name]) && $m[$name][1] !== -1 && $m[$name][0] !== '') {
                    $class = $css;
                    break;
                }
            }
            if ($class === null && isset(self::$keywordSet[strtoupper($text)])) {
                $class = 'sql-kw';
            }

            $out .= $class === null ? self::e($text) : '<span class="' . $class . '">' . self::e($text) . '</span>';
        }

        return $out . self::e(substr($sql, $pos));
    }

    private static function e(string $text): string
    {
        return htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
