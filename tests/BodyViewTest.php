<?php

declare(strict_types=1);

namespace Dxn\DebugViewer\Tests;

use Dxn\DebugViewer\BodyView;
use PHPUnit\Framework\TestCase;

final class BodyViewTest extends TestCase
{
    private static function ct(string $type): array
    {
        return ['Content-Type' => [$type]];
    }

    public function testEmptyBodiesRenderNothing(): void
    {
        self::assertSame('', BodyView::render([], ''));
        self::assertSame('', BodyView::render(self::ct('application/json'), " \r\n"));
    }

    public function testJsonBecomesATreeWithTextAndRawAlongside(): void
    {
        $html = BodyView::render(self::ct('application/json; charset=utf-8'), '{"id":3,"name":"Ann","tags":["a","b"],"ok":true,"none":null,"pi":1.50,"empty":{},"list":[]}');

        self::assertStringContainsString('<span class="dash-badge">JSON</span>', $html);
        foreach (['tree', 'pretty', 'raw'] as $mode) {
            self::assertStringContainsString('data-body-mode="' . $mode . '"', $html);
        }
        self::assertStringContainsString('<div class="body-pane" data-pane="tree">', $html, 'the tree is the one shown first');
        self::assertStringContainsString('<div class="body-pane" data-pane="pretty" hidden>', $html);
        self::assertStringContainsString('<span class="jt-num">3</span>', $html);
        self::assertStringContainsString('<span class="jt-str">"Ann"</span>', $html);
        self::assertStringContainsString('<span class="jt-bool">true</span>', $html);
        self::assertStringContainsString('<span class="jt-null">null</span>', $html);
        self::assertStringContainsString('<span class="jt-num">1.5</span>', $html);
        self::assertStringContainsString('{ 8 keys }', $html);
        self::assertStringContainsString('[ 2 items ]', $html);
        self::assertStringContainsString('<span class="jt-meta">{}</span>', $html, 'empty object, not a node');
        self::assertStringContainsString('<span class="jt-meta">[]</span>', $html);
        self::assertStringContainsString('data-body-fold="open"', $html);
        self::assertStringContainsString('data-body-copy', $html);
    }

    public function testNothingFromTheBodyReachesThePageUnescaped(): void
    {
        $evil = '<script>alert(1)</script>';
        $json = BodyView::render(self::ct('application/json'), json_encode([$evil => $evil, 'k' => ['"><img src=x onerror=1>' => 1]]));
        $text = BodyView::render(self::ct('text/html'), "<html><script>alert(1)</script><img src=x onerror=1>");
        $form = BodyView::render(self::ct('application/x-www-form-urlencoded'), 'a=' . urlencode($evil) . '&' . urlencode($evil) . '=1');

        foreach ([$json, $text, $form] as $html) {
            self::assertStringNotContainsString('<script>', $html);
            self::assertStringNotContainsString('<img', $html);
            self::assertStringContainsString('&lt;', $html);
        }
        self::assertStringContainsString('&quot;&gt;&lt;img src=x onerror=1&gt;', $json);
    }

    public function testJsonIsRecognisedByStructuredSuffixAndWithoutAContentType(): void
    {
        self::assertStringContainsString('>JSON<', BodyView::render(self::ct('application/problem+json'), '{"a":1}'));
        self::assertStringContainsString('>JSON<', BodyView::render([], '[1,2]'), 'no content type, but it is JSON');
        self::assertStringContainsString('>JSON<', BodyView::render(self::ct('text/plain'), '{"a":1}'));
        self::assertStringContainsString('>TEXT<', BodyView::render(self::ct('text/html'), '{"a":1}'), 'declared HTML stays text');
        self::assertStringContainsString('>TEXT<', BodyView::render([], 'just words'));
        self::assertStringContainsString('>TEXT<', BodyView::render(self::ct('application/json'), '"a scalar"'), 'a bare scalar is not worth a tree');
    }

    public function testInvalidJsonFallsBackToTextAndSaysWhy(): void
    {
        $html = BodyView::render(self::ct('application/json'), '{"a":');

        self::assertStringContainsString('>TEXT<', $html);
        self::assertStringContainsString('Declared as JSON but invalid:', $html);
        self::assertStringContainsString('{&quot;a&quot;:', $html);
        self::assertStringNotContainsString('invalid:', BodyView::render([], '{not json'), 'a guess that fails says nothing');
    }

    public function testObjectKeysThatLookNumericAndUnicodeSurvive(): void
    {
        $html = BodyView::render(self::ct('application/json'), '{"0":"zero","1":"one","naïve":"ünï","emoji":"🙂"}');

        self::assertStringContainsString('<span class="jt-key">0</span>', $html);
        self::assertStringContainsString('"ünï"', $html);
        self::assertStringContainsString('"🙂"', $html);
        self::assertStringContainsString('{ 4 keys }', $html);
    }

    public function testBigIntegersAreNotRoundedToFloats(): void
    {
        $html = BodyView::render(self::ct('application/json'), '{"id":12345678901234567890123}');

        self::assertStringContainsString('12345678901234567890123', $html);
        self::assertStringNotContainsString('1.2345678901235E+22', $html);
    }

    public function testDeepContainersStartClosedWhenThereAreManyNodes(): void
    {
        $small = BodyView::render(self::ct('application/json'), '{"a":{"b":{"c":1}}}');
        self::assertSame(2, substr_count($small, '<details class="jt-node" open>'), 'depth 0 and 1 are open, 2 is not');

        $many = BodyView::render(self::ct('application/json'), json_encode(['items' => array_fill(0, 400, ['x' => 1])]));
        self::assertSame(1, substr_count($many, '<details class="jt-node" open>'), 'only the top level opens');
    }

    public function testLongStringsAreCutInTheTreeButWholeInPretty(): void
    {
        $long = str_repeat('x', BodyView::MAX_TREE_STRING + 50);
        $html = BodyView::render(self::ct('application/json'), json_encode(['s' => $long]));

        self::assertStringContainsString('… +50 chars (see Pretty)', $html);
        self::assertStringNotContainsString('"' . $long . '"</span>', $html);
        self::assertStringContainsString($long, $html, 'the whole string is in Pretty and Raw');
    }

    public function testTooManyNodesSkipTheTreeButKeepTheText(): void
    {
        $html = BodyView::render(self::ct('application/json'), json_encode(array_fill(0, BodyView::MAX_TREE_NODES + 5, 1)));

        self::assertStringNotContainsString('data-pane="tree"', $html);
        self::assertStringNotContainsString('data-body-fold', $html);
        self::assertStringContainsString('<div class="body-pane" data-pane="pretty">', $html, 'pretty is first now');
        self::assertStringContainsString('too many for a tree', $html);
    }

    public function testFormBodiesBecomeATable(): void
    {
        $html = BodyView::render(self::ct('application/x-www-form-urlencoded'), 'user=ann&note=a+b%26c&empty=&flag&a.b=1&arr%5B%5D=x');

        self::assertStringContainsString('>FORM<', $html);
        self::assertStringContainsString('<td class="body-form-name">user</td><td class="body-form-value">ann</td>', $html);
        self::assertStringContainsString('<td class="body-form-value">a b&amp;c</td>', $html, 'decoded, then escaped');
        self::assertStringContainsString('<td class="body-form-name">flag</td><td class="body-form-value"></td>', $html);
        self::assertStringContainsString('<td class="body-form-name">a.b</td>', $html, 'dots are not turned into underscores');
        self::assertStringContainsString('<td class="body-form-name">arr[]</td>', $html);
        self::assertStringContainsString('data-body-mode="raw"', $html);
    }

    public function testBinaryBodiesAreNotShown(): void
    {
        foreach ([
            [self::ct('image/png'), "\x89PNG\r\n\x1a\n"],
            [self::ct('application/pdf'), '%PDF-1.4 plain looking'],
            [self::ct('application/octet-stream'), 'abc'],
            [[], "\xff\xfe\x00\x01"],
            [self::ct('text/plain'), "has a nul\0byte"],
        ] as [$headers, $body]) {
            $html = BodyView::render($headers, $body);

            self::assertStringContainsString('Binary body (', $html);
            self::assertStringNotContainsString('<pre', $html);
        }
        self::assertStringContainsString('(3 B, application/octet-stream)', BodyView::render(self::ct('application/octet-stream'), 'abc'));
    }

    public function testHugeBodiesAreCutAndNeverOfferedForCopy(): void
    {
        $body = str_repeat("line of text\n", (int)(BodyView::MAX_SHOWN_BYTES / 10));
        $html = BodyView::render(self::ct('text/plain'), $body);

        self::assertStringContainsString('… cut after', $html);
        self::assertStringNotContainsString('data-body-copy', $html);
        self::assertLessThan(strlen($body), strlen($html));
    }

    public function testJsonOverTheDecodeLimitIsShownAsText(): void
    {
        $body = '{"a":"' . str_repeat('x', BodyView::MAX_JSON_BYTES) . '"}';
        $html = BodyView::render(self::ct('application/json'), $body);

        self::assertStringContainsString('>TEXT<', $html);
        self::assertStringContainsString('shown as text', $html);
    }

    public function testCutNeverSplitsAMultibyteCharacter(): void
    {
        $body = str_repeat('é', BodyView::MAX_SHOWN_BYTES);
        $html = BodyView::render(self::ct('text/plain'), $body);

        self::assertTrue(mb_check_encoding($html, 'UTF-8'));
    }

    public function testHeaderNameIsMatchedCaseInsensitively(): void
    {
        self::assertStringContainsString('>FORM<', BodyView::render(['content-type' => ['application/x-www-form-urlencoded; charset=UTF-8']], 'a=1'));
    }
}
