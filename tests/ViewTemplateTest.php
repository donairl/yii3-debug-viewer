<?php

declare(strict_types=1);

namespace Dxn\DebugViewer\Tests;

use Dxn\DebugViewer\DumpView;
use Dxn\DebugViewer\EditorLinker;
use Dxn\DebugViewer\Template;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

final class ViewTemplateTest extends TestCase
{
    private static function render(array $data, ?string $warning = null): string
    {
        $meta = ['id' => 'abc', 'method' => 'GET', 'path' => '/x', 'url' => '', 'status' => 200, 'durationMs' => 5.0, 'memoryMb' => 2.0, 'time' => 1791429329.0];
        $params = ['title' => 't', 'indexUrl' => '/debug', 'meta' => $meta, 'summary' => [], 'view' => new DumpView($data), 'editor' => new EditorLinker(), 'warning' => $warning];

        return (string)(new ReflectionMethod(Template::class, 'render'))
            ->invoke(null, dirname(__DIR__) . '/src/templates/view.php', $params);
    }

    private static function fullDump(): array
    {
        $t = 1791429329.0;

        return [
            DumpView::DB => ['queries' => [
                'a' => ['position' => 0, 'sql' => 'SELECT 1', 'status' => 'success', 'actions' => [['time' => $t], ['time' => $t + 0.05]]],
            ]],
            DumpView::LOG => [['level' => 'warning', 'message' => 'm', 'time' => $t, 'context' => ['k' => 'v']], ['level' => 'info', 'message' => 'n']],
            DumpView::EVENT => [['name' => 'App\\Event', 'file' => '/app/src/Event.php', 'line' => '/app/src/A.php:1', 'time' => $t]],
        ];
    }

    public function testEveryFilterBarHasItsScopeAndItems(): void
    {
        $html = self::render(self::fullDump());

        foreach (['sql', 'logs', 'events'] as $name) {
            self::assertStringContainsString('class="flt" data-flt="' . $name . '"', $html, "$name filter bar");
            self::assertStringContainsString('data-flt-scope="' . $name . '"', $html, "$name scope");
        }
        self::assertGreaterThanOrEqual(3, substr_count($html, 'data-flt-item'), 'one item per query, log and event rows');
    }

    public function testSqlFlagsSlowQueries(): void
    {
        $html = self::render(self::fullDump());

        self::assertStringContainsString('data-slow="1"', $html, '50 ms is above the slow threshold');
        self::assertStringContainsString('data-status="success"', $html);
    }

    public function testLogLevelChipsAreOrderedBySeverity(): void
    {
        $html = self::render(self::fullDump());

        preg_match_all('/data-flt-chip="level" data-flt-value="([^"]+)"/', $html, $m);
        self::assertSame(['warning', 'info'], $m[1]);
    }

    public function testShowsWhyADumpWasNotFullyLoaded(): void
    {
        $html = self::render([], 'data.json is too large to load (20 MB, limit 16 MB).');

        self::assertStringContainsString('Dump not fully loaded.', $html);
        self::assertStringContainsString('too large to load', $html);
        self::assertStringNotContainsString('Dump not fully loaded.', self::render([]));
    }

    private static function brokenDump(): array
    {
        $t = 1791429329.0;

        return [
            DumpView::DB => ['queries' => [
                'a' => ['position' => 0, 'sql' => 'SELECT 1', 'status' => 'success', 'actions' => [['time' => $t], ['time' => $t + 0.001]]],
                'b' => ['position' => 1, 'sql' => 'SELECT nope', 'status' => 'error', 'actions' => [['time' => $t], ['time' => $t + 0.001]]],
            ]],
            DumpView::LOG => [['level' => 'info', 'message' => 'fine'], ['level' => 'error', 'message' => 'bad']],
            DumpView::EXCEPTION => [['class' => 'RuntimeException', 'message' => 'boom', 'file' => '/app/A.php', 'line' => 3, 'trace' => []]],
        ];
    }

    public function testOverviewListsWhatWentWrong(): void
    {
        $html = self::render(self::brokenDump());

        self::assertStringContainsString('What went wrong', $html);
        self::assertStringContainsString('Exception: boom', $html);
        self::assertStringContainsString('1 failed query', $html);
        self::assertStringContainsString('1 error-level log entry', $html);
        self::assertStringNotContainsString('No problems detected', $html);
    }

    public function testOverviewSaysSoWhenNothingIsWrong(): void
    {
        $html = self::render([DumpView::LOG => [['level' => 'info', 'message' => 'fine']]]);

        self::assertStringContainsString('No problems detected', $html);
        self::assertStringNotContainsString('What went wrong', $html);
    }

    public function testEveryProblemLinkPointsAtAnElementThatExists(): void
    {
        $html = self::render(self::brokenDump());

        preg_match_all('/data-goto-tab="(\w+)"(?: data-goto-target="([^"]+)")?/', $html, $m, PREG_SET_ORDER);
        self::assertNotEmpty($m);
        foreach ($m as $link) {
            self::assertStringContainsString('id="pane-' . $link[1] . '"', $html, "tab {$link[1]}");
            if (($link[2] ?? '') !== '') {
                self::assertStringContainsString('id="' . $link[2] . '"', $html, "target {$link[2]}");
            }
        }
    }

    public function testNoFilterBarsWhenTabsAreEmpty(): void
    {
        $html = self::render([]);

        self::assertStringNotContainsString('class="flt" data-flt=', $html);
    }
}
