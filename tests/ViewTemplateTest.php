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

    private static function requestDump(string $cookie): array
    {
        $raw = "GET /a HTTP/1.1\r\nHost: app.test\r\n" . ($cookie !== '' ? "Cookie: $cookie\r\n" : '') . "\r\n";

        return [DumpView::REQUEST => ['requestUrl' => 'http://app.test/a', 'requestMethod' => 'GET', 'requestRaw' => $raw]];
    }

    public function testRequestTabShowsGetPostAndSessionPanels(): void
    {
        $raw = "POST /login?next=%2Fhome&token=GETSECRET HTTP/1.1\r\nHost: a.test\r\nContent-Type: application/x-www-form-urlencoded\r\n\r\nuser=bob&password=POSTSECRET";
        $html = self::render([
            DumpView::REQUEST => ['requestUrl' => 'http://a.test/login?next=%2Fhome', 'requestMethod' => 'POST', 'requestRaw' => $raw],
            'App\\SessionCollector' => ['data' => ['user_id' => 7]],
        ]);

        foreach (['vars-get', 'vars-post', 'vars-session'] as $id) {
            self::assertStringContainsString('id="' . $id . '"', $html);
        }
        self::assertStringContainsString('>next<', $html);
        self::assertStringContainsString('>user<', $html);
        self::assertStringContainsString('class="jt-num">7', $html);
    }

    public function testSessionPanelSaysWhyItIsEmptyWhenNothingRecordsTheSession(): void
    {
        $html = self::render(self::requestDump(''));

        self::assertStringContainsString('The session is not recorded', $html);
        self::assertStringContainsString('No query string parameters.', $html);
        self::assertStringContainsString('No form fields in the request body.', $html);
    }

    public function testRequestTabOffersAMaskedCurlCommandWithAnOptInForCredentials(): void
    {
        $html = self::render(self::requestDump('SESSID=topsecret'));

        self::assertStringContainsString('Replay as cURL', $html);
        self::assertStringContainsString('id="curl-secrets"', $html);
        self::assertSame(1, substr_count($html, 'Cookie: SESSID=[REDACTED]'), 'the shown command is masked');
        self::assertSame(1, preg_match('/<pre class="[^"]*\bcurl-cmd\b[^"]*" id="curl-full" hidden[^>]*>[^<]*topsecret/', $html), 'the real one is there but hidden');
    }

    public function testNoCredentialToggleWhenThereAreNoCredentials(): void
    {
        $html = self::render(self::requestDump(''));

        self::assertStringContainsString('Replay as cURL', $html);
        self::assertStringNotContainsString('id="curl-secrets"', $html);
        self::assertStringNotContainsString('id="curl-full"', $html);
        self::assertStringContainsString('No credentials were found', $html);
    }

    public function testNoCurlPanelWithoutARequest(): void
    {
        self::assertStringNotContainsString('Replay as cURL', self::render([]));
    }

    public function testNoFilterBarsWhenTabsAreEmpty(): void
    {
        $html = self::render([]);

        self::assertStringNotContainsString('class="flt" data-flt=', $html);
    }
}
