<?php

declare(strict_types=1);

namespace Dxn\DebugViewer\Tests;

use Dxn\DebugViewer\DumpView;
use Dxn\DebugViewer\SqlHighlighter;
use PHPUnit\Framework\TestCase;

final class SqlHighlighterTest extends TestCase
{
    /** What a reader sees: the markup removed, entities decoded. */
    private static function text(string $html): string
    {
        return html_entity_decode(strip_tags($html), ENT_QUOTES, 'UTF-8');
    }

    public function testColoursEachKindOfToken(): void
    {
        $html = SqlHighlighter::html("SELECT u.id, 'a' FROM \"users\" u WHERE u.age > 18.5 AND u.n = :name /* c */ -- tail");

        self::assertStringContainsString('<span class="sql-kw">SELECT</span>', $html);
        self::assertStringContainsString('<span class="sql-str">\'a\'</span>'  , str_replace('&#039;', "'", $html));
        self::assertStringContainsString('<span class="sql-id">&quot;users&quot;</span>', $html);
        self::assertStringContainsString('<span class="sql-num">18.5</span>', $html);
        self::assertStringContainsString('<span class="sql-param">:name</span>', $html);
        self::assertStringContainsString('<span class="sql-com">/* c */</span>', $html);
        self::assertStringContainsString('<span class="sql-com">-- tail</span>', $html);
    }

    public function testWhatIsShownIsExactlyWhatRan(): void
    {
        foreach ([
            "select * from t where a = 'x' and b in (1, 2)",
            "SELECT\n\t*\r\nFROM   t",
            "SELECT 'it''s', 'a\\'b', \"q\"\"x\", `t`.`c`, 'ünï 🙂'",
            "SELECT a::int, b :: text, $1, ?, :p1, 1.50, .5, 1e3 FROM t1 JOIN t2_x ON t1.id=t2_x.id",
            "SELECT '<script>alert(1)</script>' AS \"<b>\" /* <i> */ -- <u>",
            "SELECT 'unterminated string",
            "SELECT \"unterminated ident",
            "/* unterminated comment SELECT 1",
            '',
            '   ',
        ] as $sql) {
            self::assertSame($sql, self::text(SqlHighlighter::html($sql)), $sql);
        }
    }

    public function testKeywordKeepsItsCase(): void
    {
        self::assertStringContainsString('<span class="sql-kw">select</span>', SqlHighlighter::html('select 1'));
        self::assertStringContainsString('<span class="sql-kw">Select</span>', SqlHighlighter::html('Select 1'));
    }

    public function testNothingInsideAStringOrCommentIsColoured(): void
    {
        $html = SqlHighlighter::html("SELECT 'select from where 123 :p' -- and or 5\n, 1");

        self::assertSame(1, substr_count($html, 'class="sql-str"'));
        self::assertSame(1, substr_count($html, 'class="sql-com"'));
        self::assertSame(1, substr_count($html, 'class="sql-kw"'), 'only the real SELECT');
        self::assertSame(1, substr_count($html, 'class="sql-num"'), 'only the real 1');
        self::assertStringNotContainsString('<span class="sql-kw">from</span>', $html);
    }

    public function testQuotesInsideStringsDoNotEndThem(): void
    {
        $doubled = SqlHighlighter::html("SELECT 'it''s' , 7");
        self::assertSame(1, substr_count($doubled, 'class="sql-str"'));
        self::assertStringContainsString('<span class="sql-num">7</span>', $doubled);

        $escaped = SqlHighlighter::html("SELECT 'a\\'b' , 8");
        self::assertSame(1, substr_count($escaped, 'class="sql-str"'));
        self::assertStringContainsString('<span class="sql-num">8</span>', $escaped);
    }

    public function testIdentifiersWithDigitsAreNotNumbersAndCastsAreNotParams(): void
    {
        $html = SqlHighlighter::html('SELECT t1.c2, x::int, y::text FROM t1');

        self::assertStringNotContainsString('class="sql-num"', $html);
        self::assertStringNotContainsString('class="sql-param"', $html);
    }

    public function testPlaceholderStyles(): void
    {
        $html = SqlHighlighter::html('a = :qp0 AND b = $2 AND c = ?');

        self::assertSame(3, substr_count($html, 'class="sql-param"'));
        self::assertStringContainsString('<span class="sql-param">:qp0</span>', $html);
        self::assertStringContainsString('<span class="sql-param">$2</span>', $html);
        self::assertStringContainsString('<span class="sql-param">?</span>', $html);
    }

    public function testNothingFromTheSqlIsLeftUnescaped(): void
    {
        foreach (["SELECT '<script>alert(1)</script>'", 'SELECT "<img src=x onerror=1>"', "/* <script> */ SELECT 1", "SELECT a<b AND c>d AND e&f"] as $sql) {
            $html = SqlHighlighter::html($sql);

            self::assertStringNotContainsString('<script', $html, $sql);
            self::assertStringNotContainsString('<img', $html, $sql);
            self::assertSame(0, preg_match('/<(?!\/?span\b)/', $html), "only our own spans are markup: $sql");
        }
    }

    public function testInvalidUtf8IsShownNotDropped(): void
    {
        $html = SqlHighlighter::html("SELECT '\xff\xfe' FROM t");

        self::assertNotSame('', $html);
        self::assertStringContainsString('SELECT', $html);
        self::assertTrue(mb_check_encoding($html, 'UTF-8'));
    }

    public function testDumpViewStillOffersIt(): void
    {
        self::assertSame(SqlHighlighter::html('SELECT 1'), DumpView::highlightSql('SELECT 1'));
    }

    public function testRandomStatementsAlwaysRoundTrip(): void
    {
        mt_srand(7);
        $pieces = ["'", "''", '"', '`', '\\', '--', '/*', '*/', ':', '::', '$1', '?', ' ', "\n", 'SELECT', 'from', 'a', 't1', '12', '1.5', '<', '>', '&', '(', ')', ',', '=', 'é', '🙂'];
        for ($i = 0; $i < 400; $i++) {
            $sql = '';
            for ($n = mt_rand(0, 25); $n > 0; $n--) {
                $sql .= $pieces[mt_rand(0, count($pieces) - 1)];
            }
            $html = SqlHighlighter::html($sql);

            self::assertSame($sql, self::text($html), 'round trip: ' . json_encode($sql));
            self::assertSame(0, preg_match('/<(?!\/?span\b)/', $html), 'stray markup for ' . json_encode($sql));
        }
    }

    public function testRealStatementsFromDumpsRoundTrip(): void
    {
        $statements = [];
        foreach (glob('/home/donairl/Proyek/mob-self-checkout/runtime/debug/*/*/data.json') ?: [] as $file) {
            $data = json_decode((string)file_get_contents($file), true);
            foreach ($data[DumpView::DB]['queries'] ?? [] as $q) {
                $statements[] = (string)($q['rawSql'] ?? $q['sql'] ?? '');
            }
        }
        if ($statements === []) {
            self::markTestSkipped('no local dumps to read');
        }

        foreach ($statements as $sql) {
            self::assertSame($sql, self::text(SqlHighlighter::html($sql)));
        }
        self::assertGreaterThan(0, count($statements));
    }
}
