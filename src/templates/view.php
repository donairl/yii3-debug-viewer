<?php

declare(strict_types=1);

use Dxn\DebugViewer\DumpView;
use Dxn\DebugViewer\EditorLinker;
use Dxn\DebugViewer\ExceptionTrace;
use Dxn\DebugViewer\Template;

/**
 * @var array<string, mixed> $meta
 * @var array<string, mixed> $summary
 * @var DumpView $view
 * @var EditorLinker $editor
 * @var string $indexUrl
 */

$status = (int)$meta['status'];
$method = (string)$meta['method'];
$path = (string)($meta['path'] !== '' ? $meta['path'] : '/');
$url = (string)($meta['url'] !== '' ? $meta['url'] : $path);
$id = (string)$meta['id'];
$durationMs = (float)$meta['durationMs'];
$memoryMb = (float)$meta['memoryMb'];
$time = (float)$meta['time'];

$queries = $view->queries();
$totalQueryDuration = $view->totalQueryDurationMs();
$logs = $view->logs();
$exceptions = $view->exceptions();
$request = $view->request();
$parsedReq = $view->parsedRequest();
$parsedRes = $view->parsedResponse();
$route = $view->route();
$routesTree = $view->routesTree();
$events = $view->events();
$services = $view->services();
$appInfo = $view->appInfo();
$collectorNames = $view->collectorNames();

$queryErrors = 0;
foreach ($queries as $q) {
    if ($q['status'] !== 'success') {
        $queryErrors++;
    }
}
$totalProblems = count($exceptions) + $queryErrors;

$exceptionDetails = $view->exceptionDetails();
$insights = $view->queryInsights();
$queryFlags = $insights['flags'];
$timeline = $view->timeline();

/** Short, single-line form of a statement for headings. */
$shorten = static fn(string $text, int $max = 140): string => mb_strimwidth((string)preg_replace('/\s+/', ' ', $text), 0, $max, '…');

/** Queries slower than this get the "slow" filter flag (and the amber timing badge). */
$slowQueryMs = 20.0;

/** Search box shared by the per-tab filter bars. Wired up by the script at the bottom. */
$filterSearch = static function (string $placeholder): void {
    ?>
    <div class="flt-search">
        <input type="text" class="flt-input" placeholder="<?= Template::e($placeholder) ?>" autocomplete="off" spellcheck="false">
        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="var(--text-muted)" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
            <circle cx="11" cy="11" r="8"></circle>
            <line x1="21" y1="21" x2="16.65" y2="16.65"></line>
        </svg>
    </div>
    <?php
};

/** A location as text, wrapped in an open-in-editor link when the editor is configured and the path is absolute. */
$linkTo = static function (string $text, ?string $url): string {
    if ($url === null) {
        return Template::e($text);
    }

    return '<a class="loc-link" href="' . Template::e($url) . '" title="Open in editor">' . Template::e($text) . '</a>';
};
/** For a `path:line` string. */
$locationHtml = static fn(string $location): string => $linkTo($location, $editor->urlForLocation($location));
/** For a file and line kept apart. */
$fileLineHtml = static fn(string $file, string $line): string
    => $linkTo($file . ($line !== '' ? ':' . $line : ''), $editor->url($file, $line));

/** Query insight cards: shared by the Overview and Database tabs. */
$renderInsights = static function () use ($insights, $shorten, $locationHtml): void {
    foreach ($insights['groups'] as $g) {
        $isNPlusOne = $g['kind'] === 'n+1';
        ?>
        <div class="insight">
            <div class="insight-head">
                <span class="dash-badge badge-warning"><?= $isNPlusOne ? 'N+1 suspected' : 'Duplicate query' ?></span>
                <span class="font-mono" style="font-size: 12px;">&times;<?= (int)$g['count'] ?></span>
                <span class="text-muted font-mono" style="font-size: 12px;">
                    <?= Template::e(Template::formatMs($g['totalMs'])) ?> total
                    <?php if ($g['wastedMs'] > 0) { ?>
                        &middot; ~<?= Template::e(Template::formatMs($g['wastedMs'])) ?> avoidable
                    <?php } ?>
                </span>
                <button class="dash-btn" style="margin-left: auto;" data-goto-query="<?= (int)$g['indexes'][0] ?>">Show query &rarr;</button>
            </div>
            <div class="font-mono insight-sql"><?= Template::e($shorten($g['sql'])) ?></div>
            <div class="text-muted" style="font-size: 12px;">
                <?= $isNPlusOne
                    ? 'Same statement run ' . (int)$g['count'] . ' times with different values. Load it once with a JOIN or <code>IN (...)</code>.'
                    : 'Identical statement and values run ' . (int)$g['count'] . ' times. Reuse the first result.' ?>
                <?php if ($g['caller'] !== '') { ?>
                    <span class="font-mono">Caller: <?= $locationHtml($g['caller']) ?></span>
                <?php } ?>
            </div>
        </div>
        <?php
    }
};
?>

<!-- View Top Info Bar -->
<div style="margin-bottom: 1.5rem; display: flex; flex-direction: column; gap: 0.85rem;">
    <!-- Breadcrumb & ID -->
    <div style="display: flex; align-items: center; justify-content: space-between; gap: 1rem; flex-wrap: wrap;">
        <div style="display: flex; align-items: center; gap: 0.5rem; font-size: 12.5px; color: var(--text-muted);">
            <a href="<?= Template::e($indexUrl) ?>" style="color: var(--text-muted); display: inline-flex; align-items: center; gap: 0.3rem;">
                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                    <line x1="19" y1="12" x2="5" y2="12"></line>
                    <polyline points="12 19 5 12 12 5"></polyline>
                </svg>
                <span>Requests</span>
            </a>
            <span>/</span>
            <span style="color: var(--text-secondary); font-family: var(--font-mono); font-weight: 500;">
                <?= Template::e($id) ?>
            </span>
            <button class="copy-btn" onclick="copyToClipboard('<?= Template::e($id) ?>', 'Request ID')" title="Copy Dump ID">
                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <rect width="14" height="14" x="8" y="8" rx="2" ry="2"></rect>
                    <path d="M4 16c-1.1 0-2-.9-2-2V4c0-1.1.9-2 2-2h10c1.1 0 2 .9 2 2"></path>
                </svg>
            </button>
        </div>

        <div style="display: flex; align-items: center; gap: 0.75rem; font-size: 12px; color: var(--text-muted);">
            <span style="font-family: var(--font-mono);">
                <?= Template::e(date('Y-m-d H:i:s', (int)$time)) ?>
            </span>
            <span>&middot;</span>
            <span><?= Template::e(Template::timeAgo($time)) ?></span>
        </div>
    </div>

    <!-- Main Title Card with Status & Action -->
    <div class="dash-panel" style="padding: 1.25rem 1.5rem; display: flex; align-items: center; justify-content: space-between; gap: 1.25rem; flex-wrap: wrap;">
        <div style="display: flex; align-items: center; gap: 0.85rem; min-width: 260px;">
            <span class="dash-pill-method <?= Template::methodClass($method) ?>" style="font-size: 13px; padding: 4px 10px;">
                <?= Template::e($method) ?>
            </span>
            <div style="display: flex; flex-direction: column;">
                <div style="font-size: 1.15rem; font-weight: 700; font-family: var(--font-mono); color: var(--text-primary); word-break: break-all;">
                    <?= Template::e($path) ?>
                </div>
                <?php if (isset($route['action']) && $route['action'] !== '') { ?>
                    <div style="font-size: 12px; font-family: var(--font-mono); color: var(--text-muted);">
                        <?= Template::e($route['action']) ?>
                    </div>
                <?php } ?>
            </div>
        </div>

        <div style="display: flex; align-items: center; gap: 1rem; flex-wrap: wrap;">
            <span class="dash-pill-status <?= Template::statusClass($status) ?>" style="font-size: 13px; padding: 4px 12px;">
                <span class="status-dot"></span>
                <span><?= Template::e($status) ?></span>
                <span style="font-size: 11px; opacity: 0.9;"><?= Template::e(Template::statusText($status)) ?></span>
            </span>

            <div style="display: flex; align-items: center; gap: 0.5rem; background: var(--bg-surface); padding: 0.35rem 0.75rem; border-radius: var(--radius-sm); border: 1px solid var(--border-subtle); font-family: var(--font-mono); font-size: 12px;">
                <span class="text-muted">Duration:</span>
                <b style="color: <?= $durationMs > 500 ? 'var(--c-err)' : ($durationMs > 200 ? 'var(--c-warn)' : 'var(--text-primary)') ?>;">
                    <?= Template::e(Template::formatMs($durationMs)) ?>
                </b>
            </div>

            <div style="display: flex; align-items: center; gap: 0.5rem; background: var(--bg-surface); padding: 0.35rem 0.75rem; border-radius: var(--radius-sm); border: 1px solid var(--border-subtle); font-family: var(--font-mono); font-size: 12px;">
                <span class="text-muted">Memory:</span>
                <b style="color: var(--text-primary);">
                    <?= Template::e(number_format($memoryMb, 2)) ?> MB
                </b>
            </div>
        </div>
    </div>
</div>

<!-- Tabs Navigation -->
<div style="display: flex; align-items: center; gap: 0.35rem; border-bottom: 1px solid var(--border-subtle); margin-bottom: 1.5rem; overflow-x: auto; padding-bottom: 2px;">
    <button class="dash-tab active" data-tab="overview">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <rect width="7" height="9" x="3" y="3" rx="1"></rect>
            <rect width="7" height="5" x="14" y="3" rx="1"></rect>
            <rect width="7" height="9" x="14" y="12" rx="1"></rect>
            <rect width="7" height="5" x="3" y="16" rx="1"></rect>
        </svg>
        <span>Overview</span>
    </button>

    <button class="dash-tab" data-tab="timeline">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <line x1="3" y1="6" x2="14" y2="6"></line>
            <line x1="7" y1="12" x2="19" y2="12"></line>
            <line x1="11" y1="18" x2="21" y2="18"></line>
        </svg>
        <span>Timeline</span>
        <span class="tab-badge"><?= count($timeline['items']) ?></span>
    </button>

    <button class="dash-tab" data-tab="sql">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <ellipse cx="12" cy="5" rx="9" ry="3"></ellipse>
            <path d="M3 5V19A9 3 0 0 0 21 19V5"></path>
            <path d="M3 12A9 3 0 0 0 21 12"></path>
        </svg>
        <span>Database</span>
        <span class="tab-badge <?= $queryErrors > 0 ? 'badge-err' : '' ?>"><?= count($queries) ?></span>
        <?php if ($insights['groups'] !== []) { ?>
            <span class="tab-badge badge-warn" title="N+1 or duplicate queries"><?= count($insights['groups']) ?>&#9888;</span>
        <?php } ?>
    </button>

    <button class="dash-tab" data-tab="logs">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
            <polyline points="14 2 14 8 20 8"></polyline>
            <line x1="16" y1="13" x2="8" y2="13"></line>
            <line x1="16" y1="17" x2="8" y2="17"></line>
        </svg>
        <span>Logs & Errors</span>
        <?php if ($totalProblems > 0) { ?>
            <span class="tab-badge badge-err"><?= $totalProblems ?></span>
        <?php } elseif (count($logs) > 0) { ?>
            <span class="tab-badge"><?= count($logs) ?></span>
        <?php } ?>
    </button>

    <button class="dash-tab" data-tab="request">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <circle cx="12" cy="12" r="10"></circle>
            <line x1="2" y1="12" x2="22" y2="12"></line>
            <path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"></path>
        </svg>
        <span>Request / Response</span>
    </button>

    <button class="dash-tab" data-tab="routing">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <polygon points="3 11 22 2 13 21 11 13 3 11"></polygon>
        </svg>
        <span>Routing</span>
    </button>

    <button class="dash-tab" data-tab="services">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <rect width="18" height="18" x="3" y="3" rx="2"></rect>
            <path d="M9 3v18"></path>
            <path d="m14 9 3 3-3 3"></path>
        </svg>
        <span>Services</span>
        <span class="tab-badge"><?= count($services) ?></span>
    </button>

    <button class="dash-tab" data-tab="events">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"></polygon>
        </svg>
        <span>Events</span>
        <span class="tab-badge"><?= count($events) ?></span>
    </button>

    <button class="dash-tab" data-tab="raw">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <polyline points="16 18 22 12 16 6"></polyline>
            <polyline points="8 6 2 12 8 18"></polyline>
        </svg>
        <span>Raw Collectors</span>
    </button>
</div>

<!-- TAB STYLES -->
<style>
    .dash-tab {
        background: transparent;
        border: none;
        color: var(--text-secondary);
        font-family: var(--font-sans);
        font-size: 13px;
        font-weight: 500;
        padding: 0.6rem 0.9rem;
        cursor: pointer;
        display: inline-flex;
        align-items: center;
        gap: 0.45rem;
        border-bottom: 2px solid transparent;
        margin-bottom: -1px;
        transition: var(--transition);
        white-space: nowrap;
        border-radius: var(--radius-sm) var(--radius-sm) 0 0;
    }
    .dash-tab:hover {
        color: var(--text-primary);
        background: var(--bg-surface-subtle);
    }
    .dash-tab.active {
        color: var(--accent);
        border-bottom-color: var(--accent);
        font-weight: 600;
    }
    .tab-badge {
        font-size: 11px;
        font-family: var(--font-mono);
        padding: 1px 6px;
        border-radius: 9999px;
        background: var(--bg-surface);
        color: var(--text-secondary);
        border: 1px solid var(--border-subtle);
    }
    .tab-badge.badge-err {
        background: var(--c-err-bg);
        color: var(--c-err);
        border-color: var(--c-err-border);
    }
    .tab-badge.badge-warn {
        background: var(--c-warn-bg);
        color: var(--c-warn);
        border-color: var(--c-warn-border);
    }

    .loc-link { color: inherit; text-decoration: none; border-bottom: 1px dotted var(--text-muted); }
    .loc-link:hover { color: var(--accent); border-bottom-color: var(--accent); }

    /* Query insights */
    .insight {
        padding: 0.85rem 1rem;
        border-bottom: 1px solid var(--border-subtle);
        display: flex;
        flex-direction: column;
        gap: 0.4rem;
    }
    .insight:last-child { border-bottom: none; }
    .insight-head { display: flex; align-items: center; gap: 0.6rem; flex-wrap: wrap; }
    .insight-sql {
        font-size: 12px;
        color: var(--text-primary);
        background: var(--code-bg);
        border: 1px solid var(--code-border);
        border-radius: var(--radius-sm);
        padding: 0.4rem 0.6rem;
        overflow-x: auto;
        white-space: nowrap;
    }
    .query-flagged { border-color: var(--c-warn-border) !important; }
    .query-flash { animation: queryFlash 1.4s ease; }
    @keyframes queryFlash {
        0%, 40% { box-shadow: 0 0 0 3px var(--accent-glow); }
        100% { box-shadow: none; }
    }

    /* Stack traces */
    .trace-frame {
        display: flex;
        gap: 0.75rem;
        padding: 0.4rem 1rem;
        border-top: 1px solid var(--border-subtle);
        font-family: var(--font-mono);
        font-size: 12px;
    }
    .trace-frame.is-app { background: var(--c-info-bg); }
    .trace-frame.is-vendor { color: var(--text-muted); }
    .trace-idx { width: 2.2rem; flex: none; color: var(--text-muted); text-align: right; }
    .trace-call { color: var(--text-primary); word-break: break-all; }
    .trace-frame.is-vendor .trace-call { color: var(--text-secondary); }
    .trace-loc { color: var(--text-muted); word-break: break-all; }
    .trace-frame.is-throw .trace-call { color: var(--c-err); font-weight: 700; }
    .trace-fold > summary {
        cursor: pointer;
        padding: 0.4rem 1rem;
        border-top: 1px solid var(--border-subtle);
        font-size: 11.5px;
        color: var(--text-muted);
        background: var(--bg-surface-subtle);
    }

    /* Timeline waterfall */
    .tl-chips { display: flex; gap: 0.4rem; flex-wrap: wrap; }
    .tl-chip {
        font-family: var(--font-sans);
        font-size: 12px;
        padding: 3px 10px;
        border-radius: 9999px;
        border: 1px solid var(--border-subtle);
        background: var(--bg-surface);
        color: var(--text-secondary);
        cursor: pointer;
    }
    .tl-chip.off { opacity: 0.45; text-decoration: line-through; }
    .tl-row {
        display: grid;
        grid-template-columns: minmax(180px, 34%) 1fr 130px;
        align-items: center;
        gap: 0.75rem;
        padding: 3px 1rem;
        border-top: 1px solid var(--border-subtle);
        min-height: 26px;
    }
    .tl-row[data-goto-query] { cursor: pointer; }
    .tl-row[data-goto-query]:hover { background: var(--bg-surface-subtle); }
    .tl-label {
        display: flex; align-items: center; gap: 0.5rem;
        font-family: var(--font-mono); font-size: 11.5px;
        overflow: hidden; white-space: nowrap; text-overflow: ellipsis;
        min-width: 0;
    }
    .tl-label > span:last-child { overflow: hidden; text-overflow: ellipsis; }
    .tl-type {
        flex: none; width: 3.4rem; text-align: center;
        font-family: var(--font-sans); font-size: 10px; font-weight: 700; letter-spacing: 0.04em;
        text-transform: uppercase; border-radius: 4px; padding: 1px 0;
        background: var(--bg-surface); color: var(--text-muted); border: 1px solid var(--border-subtle);
    }
    .tl-track {
        position: relative; height: 14px;
        background: linear-gradient(to right, var(--border-subtle) 1px, transparent 1px) 0 0 / 25% 100%;
        border-right: 1px solid var(--border-subtle);
    }
    .tl-bar, .tl-dot { position: absolute; top: 3px; background: var(--accent); }
    .tl-bar { height: 8px; border-radius: 2px; min-width: 2px; }
    .tl-dot { width: 8px; height: 8px; margin-left: -4px; transform: rotate(45deg); }
    .tl-q .tl-bar { background: var(--c-info); }
    .tl-s .tl-bar { background: var(--text-muted); }
    .tl-e .tl-dot { background: var(--text-muted); }
    .tl-l .tl-dot { background: var(--c-ok); }
    .tl-l.tl-warn .tl-dot { background: var(--c-warn); }
    .tl-x .tl-dot, .tl-err .tl-bar, .tl-err .tl-dot { background: var(--c-err); }
    .tl-flag .tl-bar { background: var(--c-warn); }
    .tl-time { font-family: var(--font-mono); font-size: 11px; color: var(--text-muted); text-align: right; white-space: nowrap; }
    .tl-ruler .tl-track { background: none; border: none; height: 18px; }
    .tl-ruler .tl-tick { position: absolute; white-space: nowrap; top: 0; font-family: var(--font-mono); font-size: 10.5px; color: var(--text-muted); transform: translateX(-50%); }
    .tl-ruler .tl-tick:first-child { transform: none; }
    .tl-ruler .tl-tick:last-child { transform: translateX(-100%); }
    .tl-ruler { border-top: none; }
    @media (max-width: 720px) {
        .tl-row { grid-template-columns: 1fr; gap: 2px; }
        .tl-time { text-align: left; }
    }

    .dash-tab-pane {
        display: none;
    }
    .dash-tab-pane.active {
        display: block;
        animation: fadeIn 0.15s ease;
    }
    @keyframes fadeIn {
        from { opacity: 0; transform: translateY(2px); }
        to { opacity: 1; transform: translateY(0); }
    }
</style>

<!-- 1. TAB: OVERVIEW -->
<div class="dash-tab-pane active" id="pane-overview">
    <?php if ($exceptionDetails !== []) { ?>
        <?php $first = $exceptionDetails[0]; ?>
        <div class="dash-panel" style="border-color: var(--c-err-border); margin-bottom: 1.25rem;">
            <div class="dash-panel-header" style="background: var(--c-err-bg); border-color: var(--c-err-border);">
                <div class="dash-panel-title" style="color: var(--c-err);">
                    <span><?= Template::e($first['class']) ?></span>
                    <?php if (count($exceptionDetails) > 1) { ?>
                        <span class="dash-badge badge-danger">+<?= count($exceptionDetails) - 1 ?> previous</span>
                    <?php } ?>
                </div>
                <button class="dash-btn" onclick="document.querySelector('.dash-tab[data-tab=logs]').click();">View stack trace &rarr;</button>
            </div>
            <div style="padding: 0.85rem 1rem;">
                <div style="font-weight: 600; color: var(--text-primary);"><?= Template::e($first['message']) ?></div>
                <?php if ($first['file'] !== '') { ?>
                    <div class="font-mono text-muted" style="font-size: 12px; margin-top: 0.3rem;"><?= $fileLineHtml((string)$first['file'], (string)$first['line']) ?></div>
                <?php } ?>
            </div>
        </div>
    <?php } ?>

    <?php if ($insights['groups'] !== []) { ?>
        <div class="dash-panel" style="border-color: var(--c-warn-border); margin-bottom: 1.25rem;">
            <div class="dash-panel-header">
                <div class="dash-panel-title">
                    <span>Query insights</span>
                    <span class="dash-badge badge-warning"><?= count($insights['groups']) ?></span>
                    <?php if ($insights['wastedMs'] > 0) { ?>
                        <span class="text-muted" style="font-size: 12px; font-weight: 400;">~<?= Template::e(Template::formatMs($insights['wastedMs'])) ?> avoidable</span>
                    <?php } ?>
                </div>
            </div>
            <?php $renderInsights(); ?>
        </div>
    <?php } ?>

    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(320px, 1fr)); gap: 1.25rem;">
        <!-- Request & Client Card -->
        <div class="dash-panel">
            <div class="dash-panel-header">
                <div class="dash-panel-title">
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <circle cx="12" cy="12" r="10"></circle>
                        <line x1="2" y1="12" x2="22" y2="12"></line>
                        <path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"></path>
                    </svg>
                    <span>Request Context</span>
                </div>
            </div>
            <table class="dash-table">
                <tbody>
                    <tr>
                        <td style="width: 120px; font-weight: 600; color: var(--text-muted);">Method</td>
                        <td><span class="dash-pill-method <?= Template::methodClass($method) ?>"><?= Template::e($method) ?></span></td>
                    </tr>
                    <tr>
                        <td style="font-weight: 600; color: var(--text-muted);">Path</td>
                        <td><code style="font-weight: 600;"><?= Template::e($path) ?></code></td>
                    </tr>
                    <tr>
                        <td style="font-weight: 600; color: var(--text-muted);">Full URL</td>
                        <td style="word-break: break-all; font-family: var(--font-mono); font-size: 12px;"><?= Template::e($url) ?></td>
                    </tr>
                    <tr>
                        <td style="font-weight: 600; color: var(--text-muted);">Client IP</td>
                        <td><code><?= Template::e($request['userIp'] ?? '127.0.0.1') ?></code></td>
                    </tr>
                    <tr>
                        <td style="font-weight: 600; color: var(--text-muted);">Ajax / Fetch</td>
                        <td><?= !empty($request['requestIsAjax']) ? '<span class="dash-badge badge-success">YES</span>' : '<span class="dash-badge">NO</span>' ?></td>
                    </tr>
                    <tr>
                        <td style="font-weight: 600; color: var(--text-muted);">Matched Route</td>
                        <td>
                            <span style="font-weight: 600; color: var(--text-primary);"><?= Template::e($route['name'] ?? 'none') ?></span>
                            <?php if (isset($route['pattern'])) { ?>
                                <span class="text-muted" style="font-size: 11.5px; font-family: var(--font-mono);">(<?= Template::e($route['pattern']) ?>)</span>
                            <?php } ?>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- Performance & Timing Card -->
        <div class="dash-panel">
            <div class="dash-panel-header">
                <div class="dash-panel-title">
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <circle cx="12" cy="12" r="10"></circle>
                        <polyline points="12 6 12 12 16 14"></polyline>
                    </svg>
                    <span>Performance Metrics</span>
                </div>
            </div>
            <table class="dash-table">
                <tbody>
                    <tr>
                        <td style="width: 140px; font-weight: 600; color: var(--text-muted);">Total Duration</td>
                        <td style="font-family: var(--font-mono); font-weight: 600; color: var(--text-primary);">
                            <?= Template::e(Template::formatMs($durationMs)) ?>
                        </td>
                    </tr>
                    <tr>
                        <td style="font-weight: 600; color: var(--text-muted);">SQL Query Time</td>
                        <td style="font-family: var(--font-mono);">
                            <?= Template::e(Template::formatMs($totalQueryDuration)) ?>
                            <span class="text-muted" style="font-size: 11px;">(<?= count($queries) ?> queries)</span>
                        </td>
                    </tr>
                    <tr>
                        <td style="font-weight: 600; color: var(--text-muted);">Route Matching</td>
                        <td style="font-family: var(--font-mono);">
                            <?= Template::e(Template::formatMs(((float)($route['matchTime'] ?? 0)) * 1000)) ?>
                        </td>
                    </tr>
                    <tr>
                        <td style="font-weight: 600; color: var(--text-muted);">Peak Memory</td>
                        <td style="font-family: var(--font-mono); color: var(--text-primary); font-weight: 600;">
                            <?= Template::e(number_format($memoryMb, 2)) ?> MB
                        </td>
                    </tr>
                    <tr>
                        <td style="font-weight: 600; color: var(--text-muted);">PHP Version</td>
                        <td style="font-family: var(--font-mono);">
                            PHP <?= Template::e($summary['Yiisoft\Yii\Debug\Collector\Web\WebAppInfoCollector']['php']['version'] ?? PHP_VERSION) ?>
                        </td>
                    </tr>
                    <tr>
                        <td style="font-weight: 600; color: var(--text-muted);">Container Services</td>
                        <td>
                            <span class="dash-badge"><?= count($services) ?> resolved</span>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Quick SQL Queries Peek (if any) -->
    <?php if ($queries !== []) { ?>
        <div class="dash-panel" style="margin-top: 1.5rem;">
            <div class="dash-panel-header">
                <div class="dash-panel-title">
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <ellipse cx="12" cy="5" rx="9" ry="3"></ellipse>
                        <path d="M3 5V19A9 3 0 0 0 21 19V5"></path>
                        <path d="M3 12A9 3 0 0 0 21 12"></path>
                    </svg>
                    <span>Executed SQL Queries (<?= count($queries) ?>)</span>
                </div>
                <button class="dash-btn" onclick="document.querySelector('.dash-tab[data-tab=sql]').click();">
                    View Full Database Tab &rarr;
                </button>
            </div>
            <div class="dash-table-container">
                <table class="dash-table">
                    <thead>
                        <tr>
                            <th style="width: 45px;">#</th>
                            <th style="width: 80px;">Status</th>
                            <th style="width: 90px; text-align: right;">Time</th>
                            <th>Query</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach (array_slice($queries, 0, 3) as $i => $q) { ?>
                            <tr>
                                <td class="text-muted font-mono"><?= $i + 1 ?></td>
                                <td>
                                    <span class="dash-badge <?= $q['status'] === 'success' ? 'badge-success' : 'badge-danger' ?>">
                                        <?= Template::e($q['status']) ?>
                                    </span>
                                </td>
                                <td style="text-align: right; font-family: var(--font-mono); font-size: 12px;">
                                    <?= $q['durationMs'] === null ? '-' : Template::e(number_format((float)$q['durationMs'], 2) . ' ms') ?>
                                </td>
                                <td>
                                    <div style="font-family: var(--font-mono); font-size: 12px; color: var(--text-primary); max-width: 800px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">
                                        <?= DumpView::highlightSql((string)$q['sql']) ?>
                                    </div>
                                    <?php if ($q['line'] !== '') { ?>
                                        <div style="font-size: 11px; color: var(--text-muted); font-family: var(--font-mono); margin-top: 2px;">
                                            <?= $locationHtml((string)$q['line']) ?>
                                        </div>
                                    <?php } ?>
                                </td>
                            </tr>
                        <?php } ?>
                    </tbody>
                </table>
            </div>
        </div>
    <?php } ?>
</div>

<!-- 1b. TAB: TIMELINE -->
<div class="dash-tab-pane" id="pane-timeline">
    <?php if ($timeline['items'] === []) { ?>
        <div class="dash-panel" style="padding: 3rem; text-align: center;">
            <p style="color: var(--text-muted);">No timed activity was recorded for this request.</p>
        </div>
    <?php } else { ?>
        <?php
        $totalMs = (float)$timeline['totalMs'];
        $typeNames = ['query' => 'SQL', 'service' => 'Service', 'event' => 'Event', 'log' => 'Log', 'exception' => 'Error'];
        $typeClass = ['query' => 'tl-q', 'service' => 'tl-s', 'event' => 'tl-e', 'log' => 'tl-l', 'exception' => 'tl-x'];
        $pct = static fn(float $ms): float => $totalMs > 0 ? min(100.0, max(0.0, $ms / $totalMs * 100)) : 0.0;
        ?>
        <div style="display: flex; align-items: center; justify-content: space-between; gap: 0.75rem; flex-wrap: wrap; margin-bottom: 1rem;">
            <div style="font-size: 14px; font-weight: 600; color: var(--text-primary);">
                Request timeline
                <span class="text-muted" style="font-size: 12px; font-weight: 400;">
                    (<?= Template::e(Template::formatMs($totalMs)) ?><?= $timeline['hiddenServices'] > 0 ? ', ' . (int)$timeline['hiddenServices'] . ' sub-ms services hidden' : '' ?>)
                </span>
            </div>
            <div class="tl-chips">
                <?php foreach ($timeline['counts'] as $type => $n) { ?>
                    <button type="button" class="tl-chip" data-tl-type="<?= Template::e($type) ?>">
                        <?= Template::e($typeNames[$type] ?? $type) ?> <span class="font-mono"><?= (int)$n ?></span>
                    </button>
                <?php } ?>
            </div>
        </div>

        <div class="dash-panel" style="overflow: hidden;">
            <div class="tl-row tl-ruler">
                <div></div>
                <div class="tl-track">
                    <?php foreach ([0, 25, 50, 75, 100] as $tick) { ?>
                        <span class="tl-tick" style="left: <?= $tick ?>%;"><?= Template::e(Template::formatMs($totalMs * $tick / 100)) ?></span>
                    <?php } ?>
                </div>
                <div></div>
            </div>
            <?php foreach ($timeline['items'] as $item) { ?>
                <?php
                $left = $pct((float)$item['startMs']);
                $isSpan = $item['durationMs'] !== null;
                $width = $isSpan ? min(100.0 - $left, max($pct((float)$item['durationMs']), 0.3)) : 0.0;
                $classes = [$typeClass[$item['type']] ?? ''];
                if ($item['status'] === 'error') {
                    $classes[] = 'tl-err';
                } elseif ($item['status'] === 'warn') {
                    $classes[] = 'tl-warn';
                }
                if ($item['flag'] !== null) {
                    $classes[] = 'tl-flag';
                }
                $title = $item['label'] . ($item['detail'] !== '' ? "\n" . $item['detail'] : '')
                    . ($item['flag'] !== null ? "\n" . strtoupper($item['flag']) . ' suspected' : '');
                ?>
                <div class="tl-row <?= Template::e(implode(' ', $classes)) ?>"
                     data-type="<?= Template::e($item['type']) ?>"
                     title="<?= Template::e($title) ?>"
                     <?= $item['ref'] !== null ? 'data-goto-query="' . (int)$item['ref'] . '"' : '' ?>>
                    <div class="tl-label">
                        <span class="tl-type"><?= Template::e($typeNames[$item['type']] ?? $item['type']) ?></span>
                        <span><?= Template::e($item['label']) ?></span>
                    </div>
                    <div class="tl-track">
                        <?php if ($isSpan) { ?>
                            <span class="tl-bar" style="left: <?= number_format($left, 3, '.', '') ?>%; width: <?= number_format($width, 3, '.', '') ?>%;"></span>
                        <?php } else { ?>
                            <span class="tl-dot" style="left: <?= number_format($left, 3, '.', '') ?>%;"></span>
                        <?php } ?>
                    </div>
                    <div class="tl-time">
                        +<?= Template::e(Template::formatMs((float)$item['startMs'])) ?>
                        <?= $isSpan ? '&middot; ' . Template::e(Template::formatMs((float)$item['durationMs'])) : '' ?>
                    </div>
                </div>
            <?php } ?>
        </div>
    <?php } ?>
</div>

<!-- 2. TAB: DATABASE (SQL) -->
<div class="dash-tab-pane" id="pane-sql">
    <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 1rem; flex-wrap: wrap; gap: 0.5rem;">
        <div style="font-size: 14px; font-weight: 600; color: var(--text-primary); display: flex; align-items: center; gap: 0.5rem;">
            <span>Executed Queries</span>
            <span class="dash-badge"><?= count($queries) ?></span>
            <span class="text-muted" style="font-size: 12px; font-weight: 400;">
                (Total execution time: <?= Template::e(Template::formatMs($totalQueryDuration)) ?>)
            </span>
        </div>

        <?php if ($queries !== []) { ?>
            <div class="flt" data-flt="sql">
                <?php $filterSearch('Filter SQL, params, caller… ( / )'); ?>
                <select class="flt-select" data-flt-key="status" aria-label="Query status">
                    <option value="">All status</option>
                    <option value="success">Success</option>
                    <option value="error">Failed</option>
                </select>
                <button type="button" class="flt-toggle" data-flt-key="slow" title="Queries slower than <?= (int)$slowQueryMs ?> ms">Slow &gt;<?= (int)$slowQueryMs ?> ms</button>
                <?php if ($queryFlags !== []) { ?>
                    <button type="button" class="flt-toggle" data-flt-key="flagged" title="N+1 and duplicate queries">N+1 / duplicate</button>
                <?php } ?>
                <span class="flt-count"></span>
                <button type="button" class="flt-clear" hidden>Clear</button>
            </div>
        <?php } ?>
    </div>

    <?php if ($insights['groups'] !== []) { ?>
        <div class="dash-panel" style="border-color: var(--c-warn-border); margin-bottom: 1rem;">
            <?php $renderInsights(); ?>
        </div>
    <?php } ?>

    <?php if ($queries === []) { ?>
        <div class="dash-panel" style="padding: 3rem; text-align: center;">
            <p style="color: var(--text-muted);">No database queries were executed during this request.</p>
        </div>
    <?php } else { ?>
        <div style="display: flex; flex-direction: column; gap: 1rem;" data-flt-scope="sql">
            <?php foreach ($queries as $i => $query) { ?>
                <?php
                $qDur = $query['durationMs'];
                $qSql = (string)$query['sql'];
                $qFlag = $queryFlags[$i] ?? null;
                ?>
                <div class="dash-panel<?= $qFlag !== null ? ' query-flagged' : '' ?>" id="q-<?= $i ?>"
                     data-flt-item
                     data-status="<?= $query['status'] === 'success' ? 'success' : 'error' ?>"
                     data-slow="<?= $qDur !== null && $qDur > $slowQueryMs ? '1' : '0' ?>"
                     data-flagged="<?= $qFlag !== null ? '1' : '0' ?>">
                    <div class="dash-panel-header" style="padding: 0.65rem 1rem;">
                        <div style="display: flex; align-items: center; gap: 0.6rem;">
                            <span style="font-family: var(--font-mono); font-weight: 700; font-size: 12px; color: var(--text-muted);">
                                #<?= $i + 1 ?>
                            </span>
                            <span class="dash-badge <?= $query['status'] === 'success' ? 'badge-success' : 'badge-danger' ?>">
                                <?= Template::e($query['status']) ?>
                            </span>
                            <?php if ($qFlag !== null) { ?>
                                <span class="dash-badge badge-warning" title="<?= $qFlag['kind'] === 'n+1' ? 'Same statement shape run with different values' : 'Identical statement run more than once' ?>">
                                    <?= $qFlag['kind'] === 'n+1' ? 'N+1' : 'duplicate' ?> &times;<?= (int)$qFlag['count'] ?>
                                </span>
                            <?php } ?>
                            <?php if ($qDur !== null) { ?>
                                <span class="dash-badge" style="color: <?= $qDur > 100 ? 'var(--c-err)' : ($qDur > 20 ? 'var(--c-warn)' : 'var(--text-secondary)') ?>;">
                                    <?= Template::e(number_format((float)$qDur, 2)) ?> ms
                                </span>
                            <?php } ?>
                            <?php if (isset($query['rows']) && $query['rows'] !== null) { ?>
                                <span class="dash-badge">
                                    <?= Template::e($query['rows']) ?> rows
                                </span>
                            <?php } ?>
                        </div>

                        <div style="display: flex; align-items: center; gap: 0.5rem;">
                            <button class="dash-btn" onclick="copyToClipboard(<?= Template::e(json_encode($qSql)) ?>, 'SQL Query')" title="Copy SQL statement">
                                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <rect width="14" height="14" x="8" y="8" rx="2" ry="2"></rect>
                                    <path d="M4 16c-1.1 0-2-.9-2-2V4c0-1.1.9-2 2-2h10c1.1 0 2 .9 2 2"></path>
                                </svg>
                                <span>Copy SQL</span>
                            </button>
                        </div>
                    </div>

                    <div style="padding: 1rem;" data-flt-text>
                        <pre style="margin: 0; background: var(--code-bg);"><?= DumpView::highlightSql($qSql) ?></pre>

                        <?php if (!empty($query['params'])) { ?>
                            <div style="margin-top: 0.75rem; border-top: 1px solid var(--border-subtle); padding-top: 0.75rem;">
                                <div style="font-size: 11px; font-weight: 600; text-transform: uppercase; color: var(--text-muted); margin-bottom: 0.4rem;">
                                    Bound Parameters
                                </div>
                                <div style="display: flex; flex-wrap: wrap; gap: 0.5rem;">
                                    <?php foreach ($query['params'] as $pKey => $pVal) { ?>
                                        <div style="background: var(--bg-surface); border: 1px solid var(--border-subtle); border-radius: var(--radius-sm); padding: 3px 8px; font-family: var(--font-mono); font-size: 11.5px;">
                                            <span style="color: var(--c-info); font-weight: 600;"><?= Template::e($pKey) ?>:</span>
                                            <span style="color: var(--c-ok);"><?= Template::e(is_scalar($pVal) ? (string)$pVal : json_encode($pVal)) ?></span>
                                        </div>
                                    <?php } ?>
                                </div>
                            </div>
                        <?php } ?>

                        <?php if ($query['line'] !== '') { ?>
                            <div style="margin-top: 0.65rem; font-size: 11.5px; color: var(--text-muted); font-family: var(--font-mono); display: flex; align-items: center; gap: 0.35rem;">
                                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M14.5 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7.5L14.5 2z"></path>
                                    <polyline points="14 2 14 8 20 8"></polyline>
                                </svg>
                                <span>Caller:</span>
                                <span style="color: var(--text-secondary);"><?= $locationHtml((string)$query['line']) ?></span>
                            </div>
                        <?php } ?>
                    </div>
                </div>
            <?php } ?>
            <div class="flt-empty" hidden>No queries match the current filters.</div>
        </div>
    <?php } ?>
</div>

<!-- 3. TAB: LOGS & EXCEPTIONS -->
<div class="dash-tab-pane" id="pane-logs">
    <!-- Exceptions Section -->
    <?php if ($exceptionDetails !== []) { ?>
        <div style="margin-bottom: 1.5rem;">
            <div style="display: flex; align-items: center; gap: 0.5rem; margin-bottom: 0.75rem;">
                <span class="dash-badge badge-danger" style="font-size: 13px; padding: 4px 9px;">
                    <?= count($exceptionDetails) ?> Exception<?= count($exceptionDetails) > 1 ? 's' : '' ?>
                </span>
            </div>
            <?php foreach ($exceptionDetails as $n => $ex) { ?>
                <?php
                $copyText = $ex['class'] . ': ' . $ex['message'] . "\n";
                foreach ($ex['frames'] as $f) {
                    $copyText .= '#' . $f['index'] . ' ' . ($f['file'] !== '' ? $f['file'] . ($f['line'] !== '' ? '(' . $f['line'] . ')' : '') . ': ' : '') . $f['call'] . "\n";
                }
                $segments = ExceptionTrace::segments($ex['frames']);
                $renderFrame = static function (array $f) use ($ex, $fileLineHtml): void {
                    $isThrow = ExceptionTrace::isThrowSite($f);
                    $cls = $isThrow ? 'is-throw' : ($f['vendor'] ? 'is-vendor' : 'is-app');
                    ?>
                    <div class="trace-frame <?= $cls ?>">
                        <span class="trace-idx">#<?= (int)$f['index'] ?></span>
                        <div>
                            <div class="trace-call"><?= Template::e($isThrow ? $ex['class'] . ' ' . $f['call'] : $f['call']) ?></div>
                            <?php if ($f['file'] !== '') { ?>
                                <div class="trace-loc"><?= $fileLineHtml($f['file'], $f['line']) ?></div>
                            <?php } ?>
                        </div>
                    </div>
                    <?php
                };
                ?>
                <div class="dash-panel" style="border-color: var(--c-err-border); margin-bottom: 1rem; background: var(--bg-card);">
                    <div class="dash-panel-header" style="background: var(--c-err-bg); border-color: var(--c-err-border);">
                        <div style="font-weight: 700; color: var(--c-err); font-family: var(--font-mono); display: flex; align-items: center; gap: 0.5rem;">
                            <span><?= Template::e($ex['class']) ?></span>
                            <?php if ($n > 0) { ?>
                                <span class="dash-badge">previous</span>
                            <?php } ?>
                            <?php if ($ex['code'] !== '' && $ex['code'] !== '0') { ?>
                                <span class="dash-badge">code <?= Template::e($ex['code']) ?></span>
                            <?php } ?>
                        </div>
                        <button class="dash-btn" onclick="copyToClipboard(<?= Template::e(json_encode($copyText)) ?>, 'Stack trace')" title="Copy message and stack trace">
                            <span>Copy trace</span>
                        </button>
                    </div>
                    <div style="padding: 1rem;">
                        <div style="font-size: 14px; font-weight: 600; color: var(--text-primary); white-space: pre-wrap; word-break: break-word;"><?= Template::e($ex['message'] !== '' ? $ex['message'] : 'No message') ?></div>
                    </div>
                    <?php foreach ($segments as $seg) { ?>
                        <?php if ($seg['vendor'] && count($seg['frames']) > 1) { ?>
                            <details class="trace-fold">
                                <summary><?= count($seg['frames']) ?> vendor frames (#<?= (int)$seg['frames'][0]['index'] ?>&ndash;#<?= (int)$seg['frames'][count($seg['frames']) - 1]['index'] ?>)</summary>
                                <?php foreach ($seg['frames'] as $f) { $renderFrame($f); } ?>
                            </details>
                        <?php } else { ?>
                            <?php foreach ($seg['frames'] as $f) { $renderFrame($f); } ?>
                        <?php } ?>
                    <?php } ?>
                    <details class="trace-fold">
                        <summary>Raw exception data</summary>
                        <pre style="max-height: 400px; margin: 0;"><?= Template::e(DumpView::json($exceptions[$n] ?? [])) ?></pre>
                    </details>
                </div>
            <?php } ?>
        </div>
    <?php } ?>

    <!-- Application Logs Section -->
    <div class="dash-panel">
        <div class="dash-panel-header">
            <div class="dash-panel-title">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <line x1="8" y1="6" x2="21" y2="6"></line>
                    <line x1="8" y1="12" x2="21" y2="12"></line>
                    <line x1="8" y1="18" x2="21" y2="18"></line>
                    <line x1="3" y1="6" x2="3.01" y2="6"></line>
                    <line x1="3" y1="12" x2="3.01" y2="12"></line>
                    <line x1="3" y1="18" x2="3.01" y2="18"></line>
                </svg>
                <span>Application Log Entries</span>
                <span class="dash-badge"><?= count($logs) ?></span>
            </div>

            <?php if ($logs !== []) { ?>
                <?php
                $levelCounts = [];
                foreach ($logs as $l) {
                    $levelCounts[strtolower($l['level'])] = ($levelCounts[strtolower($l['level'])] ?? 0) + 1;
                }
                $severity = array_flip(['emergency', 'alert', 'critical', 'error', 'warning', 'notice', 'info', 'debug']);
                uksort($levelCounts, static fn(string $a, string $b): int => ($severity[$a] ?? 99) <=> ($severity[$b] ?? 99) ?: strcmp($a, $b));
                $withContext = count(array_filter($logs, static fn(array $l): bool => $l['context'] !== null));
                ?>
                <div class="flt" data-flt="logs">
                    <?php $filterSearch('Filter message, context, caller… ( / )'); ?>
                    <?php foreach ($levelCounts as $lvlName => $n) { ?>
                        <button type="button" class="tl-chip" data-flt-chip="level" data-flt-value="<?= Template::e($lvlName) ?>" title="Show or hide <?= Template::e($lvlName) ?> entries">
                            <?= Template::e($lvlName) ?> <span class="font-mono"><?= (int)$n ?></span>
                        </button>
                    <?php } ?>
                    <?php if ($withContext > 0 && $withContext < count($logs)) { ?>
                        <button type="button" class="flt-toggle" data-flt-key="context">Has context</button>
                    <?php } ?>
                    <span class="flt-count"></span>
                    <button type="button" class="flt-clear" hidden>Clear</button>
                </div>
            <?php } ?>
        </div>

        <?php if ($logs === []) { ?>
            <div style="padding: 3rem; text-align: center; color: var(--text-muted);">
                No application messages were logged for this request.
            </div>
        <?php } else { ?>
            <div class="dash-table-container" data-flt-scope="logs">
                <table class="dash-table">
                    <thead>
                        <tr>
                            <th style="width: 100px;">Level</th>
                            <th style="width: 110px;">Time</th>
                            <th>Message</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($logs as $log) { ?>
                            <?php
                            $lvl = strtolower($log['level']);
                            $isErr = in_array($lvl, ['error', 'critical', 'alert', 'emergency'], true);
                            $isWarn = ($lvl === 'warning');
                            ?>
                            <tr data-flt-item data-level="<?= Template::e($lvl) ?>" data-context="<?= $log['context'] !== null ? '1' : '0' ?>">
                                <td>
                                    <span class="dash-badge <?= $isErr ? 'badge-danger' : ($isWarn ? 'badge-warning' : '') ?>" style="text-transform: uppercase;">
                                        <?= Template::e($lvl) ?>
                                    </span>
                                </td>
                                <td class="text-muted font-mono" style="font-size: 11.5px;">
                                    <?= $log['time'] !== null ? Template::e(date('H:i:s.', (int)$log['time']) . sprintf('%03d', (int)(($log['time'] - (int)$log['time']) * 1000))) : '-' ?>
                                </td>
                                <td>
                                    <pre style="margin: 0; padding: 0.5rem 0.75rem; font-size: 12px; white-space: pre-wrap;"><?= Template::e($log['message']) ?></pre>
                                    <?php if ($log['line'] !== null) { ?>
                                        <div class="text-muted font-mono" style="padding: 0 0.75rem; font-size: 11px;"><?= $locationHtml($log['line']) ?></div>
                                    <?php } ?>
                                    <?php if ($log['context'] !== null) { ?>
                                        <details style="padding: 0.25rem 0.75rem 0.5rem;"<?= strlen($log['context']) <= 800 ? ' open' : '' ?>>
                                            <summary class="text-muted" style="cursor: pointer; font-size: 11.5px;">context (<?= number_format(strlen($log['context'])) ?> bytes)</summary>
                                            <pre style="margin: 0.25rem 0 0; font-size: 11.5px; white-space: pre-wrap; word-break: break-all; max-height: 480px; overflow: auto;"><?= Template::e($log['context']) ?></pre>
                                        </details>
                                    <?php } ?>
                                </td>
                            </tr>
                        <?php } ?>
                    </tbody>
                </table>
            </div>
        <?php } ?>
    </div>
</div>

<!-- 4. TAB: REQUEST / RESPONSE -->
<div class="dash-tab-pane" id="pane-request">
    <div style="display: flex; flex-direction: column; gap: 1.5rem;">
        <!-- Request Section -->
        <div class="dash-panel">
            <div class="dash-panel-header">
                <div class="dash-panel-title">
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <line x1="7" y1="17" x2="17" y2="7"></line>
                        <polyline points="7 7 17 7 17 17"></polyline>
                    </svg>
                    <span>HTTP Request Details</span>
                </div>
                <?php if ($parsedReq['startLine'] !== '') { ?>
                    <code style="font-weight: 600;"><?= Template::e($parsedReq['startLine']) ?></code>
                <?php } ?>
            </div>

            <div style="padding: 1rem 1.25rem;">
                <h4 style="font-size: 12px; font-weight: 700; text-transform: uppercase; color: var(--text-muted); margin-bottom: 0.6rem;">Request Headers</h4>
                <?php if ($parsedReq['headers'] !== []) { ?>
                    <table class="dash-table" style="border: 1px solid var(--border-subtle); border-radius: var(--radius-sm); margin-bottom: 1rem;">
                        <thead>
                            <tr>
                                <th style="width: 200px;">Header</th>
                                <th>Value</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($parsedReq['headers'] as $hName => $hVals) { ?>
                                <tr>
                                    <td style="font-family: var(--font-mono); font-weight: 600; color: var(--text-primary);"><?= Template::e($hName) ?></td>
                                    <td style="font-family: var(--font-mono); font-size: 12px; word-break: break-all;">
                                        <?= Template::e(implode(', ', $hVals)) ?>
                                    </td>
                                </tr>
                            <?php } ?>
                        </tbody>
                    </table>
                <?php } else { ?>
                    <p class="text-muted" style="margin-bottom: 1rem;">No custom request headers recorded.</p>
                <?php } ?>

                <?php if (trim($parsedReq['body']) !== '') { ?>
                    <h4 style="font-size: 12px; font-weight: 700; text-transform: uppercase; color: var(--text-muted); margin-bottom: 0.6rem;">Request Body</h4>
                    <pre><?= Template::e($parsedReq['body']) ?></pre>
                <?php } ?>
            </div>
        </div>

        <!-- Response Section -->
        <div class="dash-panel">
            <div class="dash-panel-header">
                <div class="dash-panel-title">
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <line x1="17" y1="7" x2="7" y2="17"></line>
                        <polyline points="17 17 7 17 7 7"></polyline>
                    </svg>
                    <span>HTTP Response Details</span>
                </div>
                <?php if ($parsedRes['startLine'] !== '') { ?>
                    <code style="font-weight: 600;"><?= Template::e($parsedRes['startLine']) ?></code>
                <?php } ?>
            </div>

            <div style="padding: 1rem 1.25rem;">
                <h4 style="font-size: 12px; font-weight: 700; text-transform: uppercase; color: var(--text-muted); margin-bottom: 0.6rem;">Response Headers</h4>
                <?php if ($parsedRes['headers'] !== []) { ?>
                    <table class="dash-table" style="border: 1px solid var(--border-subtle); border-radius: var(--radius-sm); margin-bottom: 1rem;">
                        <thead>
                            <tr>
                                <th style="width: 200px;">Header</th>
                                <th>Value</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($parsedRes['headers'] as $hName => $hVals) { ?>
                                <tr>
                                    <td style="font-family: var(--font-mono); font-weight: 600; color: var(--text-primary);"><?= Template::e($hName) ?></td>
                                    <td style="font-family: var(--font-mono); font-size: 12px; word-break: break-all;">
                                        <?= Template::e(implode(', ', $hVals)) ?>
                                    </td>
                                </tr>
                            <?php } ?>
                        </tbody>
                    </table>
                <?php } ?>

                <?php if (trim($parsedRes['body']) !== '') { ?>
                    <details>
                        <summary style="font-size: 12px; font-weight: 600; color: var(--text-secondary); cursor: pointer; padding: 0.4rem 0;">
                            View Response Body Preview (<?= strlen($parsedRes['body']) ?> bytes)
                        </summary>
                        <pre style="max-height: 400px; margin-top: 0.5rem;"><?= Template::e(substr($parsedRes['body'], 0, 10000)) ?><?= strlen($parsedRes['body']) > 10000 ? "

[... truncated ...]" : '' ?></pre>
                    </details>
                <?php } ?>
            </div>
        </div>
    </div>
</div>

<!-- 5. TAB: ROUTING -->
<div class="dash-tab-pane" id="pane-routing">
    <div style="display: flex; flex-direction: column; gap: 1.5rem;">
        <!-- Matched Route Card -->
        <div class="dash-panel">
            <div class="dash-panel-header">
                <div class="dash-panel-title">
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <polygon points="3 11 22 2 13 21 11 13 3 11"></polygon>
                    </svg>
                    <span>Current Matched Route</span>
                </div>
                <?php if (isset($route['matchTime'])) { ?>
                    <span class="dash-badge">Matched in <?= Template::e(Template::formatMs(((float)$route['matchTime']) * 1000)) ?></span>
                <?php } ?>
            </div>
            <?php if ($route === []) { ?>
                <div style="padding: 2rem; text-align: center; color: var(--text-muted);">
                    No route matched this request.
                </div>
            <?php } else { ?>
                <table class="dash-table">
                    <tbody>
                        <tr>
                            <td style="width: 140px; font-weight: 600; color: var(--text-muted);">Route Name</td>
                            <td><b style="color: var(--text-primary); font-size: 13.5px;"><?= Template::e($route['name'] ?? '-') ?></b></td>
                        </tr>
                        <tr>
                            <td style="font-weight: 600; color: var(--text-muted);">Pattern</td>
                            <td><code><?= Template::e($route['pattern'] ?? '-') ?></code></td>
                        </tr>
                        <tr>
                            <td style="font-weight: 600; color: var(--text-muted);">Action Handler</td>
                            <td><code style="color: var(--accent); font-weight: 600;"><?= Template::e($route['action'] ?? '-') ?></code></td>
                        </tr>
                        <tr>
                            <td style="font-weight: 600; color: var(--text-muted);">Arguments</td>
                            <td><pre style="margin: 0; padding: 0.4rem 0.6rem;"><?= Template::e(DumpView::json($route['arguments'] ?? [])) ?></pre></td>
                        </tr>
                    </tbody>
                </table>
            <?php } ?>
        </div>

        <!-- All Application Routes List -->
        <?php if ($routesTree !== []) { ?>
            <div class="dash-panel">
                <div class="dash-panel-header">
                    <div class="dash-panel-title">
                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <line x1="8" y1="6" x2="21" y2="6"></line>
                            <line x1="8" y1="12" x2="21" y2="12"></line>
                            <line x1="8" y1="18" x2="21" y2="18"></line>
                        </svg>
                        <span>All Registered Routes</span>
                        <span class="dash-badge"><?= count($routesTree) ?></span>
                    </div>
                </div>
                <div class="dash-table-container">
                    <table class="dash-table">
                        <thead>
                            <tr>
                                <th style="width: 50px;">#</th>
                                <th>Definition</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($routesTree as $idx => $rItem) { ?>
                                <tr>
                                    <td class="text-muted font-mono"><?= $idx + 1 ?></td>
                                    <td style="font-family: var(--font-mono); font-size: 12.5px; color: var(--text-primary);">
                                        <?= Template::e($rItem) ?>
                                    </td>
                                </tr>
                            <?php } ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php } ?>
    </div>
</div>

<!-- 6. TAB: SERVICES -->
<div class="dash-tab-pane" id="pane-services">
    <div class="dash-panel">
        <div class="dash-panel-header">
            <div class="dash-panel-title">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <rect width="18" height="18" x="3" y="3" rx="2"></rect>
                    <path d="M9 3v18"></path>
                    <path d="m14 9 3 3-3 3"></path>
                </svg>
                <span>Container Service Resolutions</span>
                <span class="dash-badge"><?= count($services) ?></span>
            </div>
        </div>

        <?php if ($services === []) { ?>
            <div style="padding: 3rem; text-align: center; color: var(--text-muted);">
                No service resolutions recorded.
            </div>
        <?php } else { ?>
            <div class="dash-table-container">
                <table class="dash-table">
                    <thead>
                        <tr>
                            <th style="width: 45px;">#</th>
                            <th>Service / Target</th>
                            <th>Class & Method</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($services as $idx => $s) { ?>
                            <tr>
                                <td class="text-muted font-mono"><?= $idx + 1 ?></td>
                                <td style="font-family: var(--font-mono); font-size: 12px; font-weight: 600; color: var(--text-primary); word-break: break-all;">
                                    <?= Template::e($s['service']) ?>
                                </td>
                                <td style="font-family: var(--font-mono); font-size: 12px; color: var(--text-secondary);">
                                    <?= Template::e($s['class'] . '->' . $s['method']) ?>
                                </td>
                                <td>
                                    <span class="dash-badge <?= $s['status'] === 'success' ? 'badge-success' : 'badge-danger' ?>">
                                        <?= Template::e($s['status']) ?>
                                    </span>
                                </td>
                            </tr>
                        <?php } ?>
                    </tbody>
                </table>
            </div>
        <?php } ?>
    </div>
</div>

<!-- 7. TAB: EVENTS -->
<div class="dash-tab-pane" id="pane-events">
    <div class="dash-panel">
        <div class="dash-panel-header">
            <div class="dash-panel-title">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"></polygon>
                </svg>
                <span>Dispatched Application Events</span>
                <span class="dash-badge"><?= count($events) ?></span>
            </div>

            <?php if ($events !== []) { ?>
                <?php
                $vendorEvents = count(array_filter($events, static fn(array $e): bool => ExceptionTrace::isVendor($e['file'])));
                ?>
                <div class="flt" data-flt="events">
                    <?php $filterSearch('Filter event name or location… ( / )'); ?>
                    <?php if ($vendorEvents > 0 && $vendorEvents < count($events)) { ?>
                        <button type="button" class="flt-toggle" data-flt-key="app" title="Hide events declared in vendor/ (<?= $vendorEvents ?>)">App events only</button>
                    <?php } ?>
                    <span class="flt-count"></span>
                    <button type="button" class="flt-clear" hidden>Clear</button>
                </div>
            <?php } ?>
        </div>

        <?php if ($events === []) { ?>
            <div style="padding: 3rem; text-align: center; color: var(--text-muted);">
                No events recorded.
            </div>
        <?php } else { ?>
            <div class="dash-table-container" data-flt-scope="events">
                <table class="dash-table">
                    <thead>
                        <tr>
                            <th style="width: 45px;">#</th>
                            <th>Event Name</th>
                            <th>Caller Location</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($events as $idx => $ev) { ?>
                            <tr data-flt-item data-app="<?= ExceptionTrace::isVendor($ev['file']) ? '0' : '1' ?>">
                                <td class="text-muted font-mono"><?= $idx + 1 ?></td>
                                <td style="font-family: var(--font-mono); font-size: 12.5px; font-weight: 600; color: var(--text-primary);">
                                    <?= Template::e($ev['name']) ?>
                                </td>
                                <td style="font-family: var(--font-mono); font-size: 11.5px; color: var(--text-muted);">
                                    <?= $ev['line'] !== '' ? $locationHtml($ev['line']) : Template::e($ev['file'] ?? '-') ?>
                                </td>
                            </tr>
                        <?php } ?>
                    </tbody>
                </table>
            </div>
        <?php } ?>
    </div>
</div>

<!-- 8. TAB: RAW COLLECTORS -->
<div class="dash-tab-pane" id="pane-raw">
    <div class="dash-panel">
        <div class="dash-panel-header">
            <div class="dash-panel-title">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <polyline points="16 18 22 12 16 6"></polyline>
                    <polyline points="8 6 2 12 8 18"></polyline>
                </svg>
                <span>Raw Collector Dumps</span>
            </div>

            <div style="display: flex; align-items: center; gap: 0.6rem;">
                <select id="collector-select" style="background: var(--bg-input); border: 1px solid var(--border-subtle); border-radius: var(--radius-sm); color: var(--text-primary); padding: 0.38rem 0.65rem; font-size: 12px; outline: none; cursor: pointer;">
                    <?php foreach ($collectorNames as $cName) { ?>
                        <option value="<?= Template::e($cName) ?>">
                            <?= Template::e(substr($cName, (int)strrpos($cName, '\\') + 1)) ?> (<?= Template::e($cName) ?>)
                        </option>
                    <?php } ?>
                </select>

                <button class="dash-btn" id="btn-copy-collector">
                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <rect width="14" height="14" x="8" y="8" rx="2" ry="2"></rect>
                        <path d="M4 16c-1.1 0-2-.9-2-2V4c0-1.1.9-2 2-2h10c1.1 0 2 .9 2 2"></path>
                    </svg>
                    <span>Copy JSON</span>
                </button>
            </div>
        </div>

        <div style="padding: 1rem;">
            <?php foreach ($collectorNames as $idx => $cName) { ?>
                <div class="raw-collector-block" id="col-block-<?= Template::e(md5($cName)) ?>" style="<?= $idx > 0 ? 'display: none;' : '' ?>">
                    <pre style="max-height: 600px;" id="col-pre-<?= Template::e(md5($cName)) ?>"><?= Template::e($view->raw($cName)) ?></pre>
                </div>
            <?php } ?>
        </div>
    </div>
</div>

<script>
    // Tab switching logic with Hash support
    (function() {
        var tabs = document.querySelectorAll('.dash-tab');
        var panes = document.querySelectorAll('.dash-tab-pane');

        function activateTab(tabName) {
            tabs.forEach(function(t) {
                if (t.getAttribute('data-tab') === tabName) {
                    t.classList.add('active');
                } else {
                    t.classList.remove('active');
                }
            });
            panes.forEach(function(p) {
                if (p.id === 'pane-' + tabName) {
                    p.classList.add('active');
                } else {
                    p.classList.remove('active');
                }
            });
            try {
                history.replaceState(null, null, '#' + tabName);
            } catch (e) {}
        }

        tabs.forEach(function(tab) {
            tab.addEventListener('click', function() {
                var name = this.getAttribute('data-tab');
                activateTab(name);
            });
        });

        // Check initial hash
        var hash = (window.location.hash || '').replace('#', '');
        if (hash && document.getElementById('pane-' + hash)) {
            activateTab(hash);
        }

        // Jump to a query in the Database tab (timeline rows, insight cards)
        document.querySelectorAll('[data-goto-query]').forEach(function(el) {
            el.addEventListener('click', function() {
                activateTab('sql');
                var target = document.getElementById('q-' + this.getAttribute('data-goto-query'));
                if (target) {
                    target.scrollIntoView({ block: 'center' });
                    target.classList.remove('query-flash');
                    void target.offsetWidth;
                    target.classList.add('query-flash');
                }
            });
        });

        // Timeline type filter
        document.querySelectorAll('.tl-chip[data-tl-type]').forEach(function(chip) {
            chip.addEventListener('click', function() {
                this.classList.toggle('off');
                var hidden = {};
                document.querySelectorAll('.tl-chip.off').forEach(function(c) {
                    hidden[c.getAttribute('data-tl-type')] = true;
                });
                document.querySelectorAll('.tl-row[data-type]').forEach(function(row) {
                    row.style.display = hidden[row.getAttribute('data-type')] ? 'none' : '';
                });
            });
        });

        // Per-tab filters (SQL, logs, events). Each bar `data-flt="x"` filters the
        // `data-flt-item` elements inside `data-flt-scope="x"`:
        //   .flt-input               space-separated terms, all must appear in the item's text
        //   .flt-select[data-flt-key]  item's data-<key> must equal the chosen value
        //   .flt-toggle[data-flt-key]  when on, item's data-<key> must be "1"
        //   [data-flt-chip][data-flt-value]  turned off, hides items whose data-<chip> is that value
        document.querySelectorAll('.flt[data-flt]').forEach(function(bar) {
            var scope = document.querySelector('[data-flt-scope="' + bar.getAttribute('data-flt') + '"]');
            if (!scope) { return; }

            var items = Array.prototype.slice.call(scope.querySelectorAll('[data-flt-item]'));
            var input = bar.querySelector('.flt-input');
            var selects = bar.querySelectorAll('.flt-select');
            var toggles = bar.querySelectorAll('.flt-toggle');
            var chips = bar.querySelectorAll('[data-flt-chip]');
            var count = bar.querySelector('.flt-count');
            var clear = bar.querySelector('.flt-clear');
            var empty = scope.querySelector('.flt-empty');
            if (!empty) {
                empty = document.createElement('div');
                empty.className = 'flt-empty';
                empty.hidden = true;
                empty.textContent = 'Nothing matches the current filters.';
                scope.appendChild(empty);
            }

            var haystacks = null;
            function haystack(i) {
                if (!haystacks) {
                    haystacks = items.map(function(el) {
                        var part = el.querySelector('[data-flt-text]') || el;
                        return part.textContent.toLowerCase();
                    });
                }
                return haystacks[i];
            }

            function apply() {
                var terms = (input ? input.value : '').toLowerCase().split(/\s+/).filter(Boolean);
                var sel = {}, on = {}, off = {}, k;
                selects.forEach(function(s) { if (s.value) { sel[s.getAttribute('data-flt-key')] = s.value; } });
                toggles.forEach(function(t) { if (t.classList.contains('on')) { on[t.getAttribute('data-flt-key')] = true; } });
                chips.forEach(function(c) {
                    if (c.classList.contains('off')) {
                        var key = c.getAttribute('data-flt-chip');
                        (off[key] = off[key] || {})[c.getAttribute('data-flt-value')] = true;
                    }
                });

                var visible = 0;
                items.forEach(function(el, i) {
                    var ok = true;
                    for (k in sel) { if (el.getAttribute('data-' + k) !== sel[k]) { ok = false; } }
                    for (k in on) { if (el.getAttribute('data-' + k) !== '1') { ok = false; } }
                    for (k in off) { if (off[k][el.getAttribute('data-' + k)]) { ok = false; } }
                    if (ok && terms.length) {
                        var h = haystack(i);
                        ok = terms.every(function(t) { return h.indexOf(t) !== -1; });
                    }
                    el.style.display = ok ? '' : 'none';
                    if (ok) { visible++; }
                });

                var active = terms.length > 0 || Object.keys(sel).length > 0 || Object.keys(on).length > 0 || Object.keys(off).length > 0;
                if (count) { count.textContent = active ? visible + ' of ' + items.length : ''; }
                if (clear) { clear.hidden = !active; }
                empty.hidden = !(active && visible === 0);
            }

            if (input) {
                input.addEventListener('input', apply);
                input.addEventListener('keydown', function(e) {
                    if (e.key === 'Escape') { this.value = ''; this.blur(); apply(); }
                });
            }
            selects.forEach(function(s) { s.addEventListener('change', apply); });
            toggles.forEach(function(t) {
                t.addEventListener('click', function() { this.classList.toggle('on'); apply(); });
            });
            chips.forEach(function(c) {
                c.addEventListener('click', function() { this.classList.toggle('off'); apply(); });
            });
            if (clear) {
                clear.addEventListener('click', function() {
                    if (input) { input.value = ''; }
                    selects.forEach(function(s) { s.value = ''; });
                    toggles.forEach(function(t) { t.classList.remove('on'); });
                    chips.forEach(function(c) { c.classList.remove('off'); });
                    apply();
                });
            }
        });

        // '/' focuses the filter box of the tab you are looking at
        window.addEventListener('keydown', function(e) {
            if (e.key !== '/' || e.ctrlKey || e.metaKey || e.altKey) { return; }
            if (['INPUT', 'TEXTAREA', 'SELECT'].indexOf(document.activeElement.tagName) !== -1) { return; }
            var box = document.querySelector('.dash-tab-pane.active .flt-input');
            if (box) {
                e.preventDefault();
                box.focus();
                box.select();
            }
        });

        // Raw collector switcher
        var colSelect = document.getElementById('collector-select');
        var copyColBtn = document.getElementById('btn-copy-collector');

        function md5(str) {
            // simple hash map lookup
            var map = {
                <?php foreach ($collectorNames as $cName) { ?>
                    <?= json_encode($cName) ?>: <?= json_encode(md5($cName)) ?>,
                <?php } ?>
            };
            return map[str] || '';
        }

        if (colSelect) {
            colSelect.addEventListener('change', function() {
                var selected = this.value;
                var hashId = md5(selected);
                document.querySelectorAll('.raw-collector-block').forEach(function(b) {
                    b.style.display = 'none';
                });
                var activeBlock = document.getElementById('col-block-' + hashId);
                if (activeBlock) {
                    activeBlock.style.display = 'block';
                }
            });
        }

        if (copyColBtn && colSelect) {
            copyColBtn.addEventListener('click', function() {
                var selected = colSelect.value;
                var hashId = md5(selected);
                var pre = document.getElementById('col-pre-' + hashId);
                if (pre) {
                    copyToClipboard(pre.textContent, 'Collector JSON');
                }
            });
        }
    })();
</script>
