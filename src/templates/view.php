<?php

declare(strict_types=1);

use Dxn\DebugViewer\DumpView;
use Dxn\DebugViewer\Template;

/**
 * @var array<string, mixed> $meta
 * @var array<string, mixed> $summary
 * @var DumpView $view
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

    <button class="dash-tab" data-tab="sql">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <ellipse cx="12" cy="5" rx="9" ry="3"></ellipse>
            <path d="M3 5V19A9 3 0 0 0 21 19V5"></path>
            <path d="M3 12A9 3 0 0 0 21 12"></path>
        </svg>
        <span>Database</span>
        <span class="tab-badge <?= $queryErrors > 0 ? 'badge-err' : '' ?>"><?= count($queries) ?></span>
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
                                            <?= Template::e($q['line']) ?>
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
    </div>

    <?php if ($queries === []) { ?>
        <div class="dash-panel" style="padding: 3rem; text-align: center;">
            <p style="color: var(--text-muted);">No database queries were executed during this request.</p>
        </div>
    <?php } else { ?>
        <div style="display: flex; flex-direction: column; gap: 1rem;">
            <?php foreach ($queries as $i => $query) { ?>
                <?php
                $qDur = $query['durationMs'];
                $qSql = (string)$query['sql'];
                ?>
                <div class="dash-panel">
                    <div class="dash-panel-header" style="padding: 0.65rem 1rem;">
                        <div style="display: flex; align-items: center; gap: 0.6rem;">
                            <span style="font-family: var(--font-mono); font-weight: 700; font-size: 12px; color: var(--text-muted);">
                                #<?= $i + 1 ?>
                            </span>
                            <span class="dash-badge <?= $query['status'] === 'success' ? 'badge-success' : 'badge-danger' ?>">
                                <?= Template::e($query['status']) ?>
                            </span>
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

                    <div style="padding: 1rem;">
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
                                <span style="color: var(--text-secondary);"><?= Template::e($query['line']) ?></span>
                            </div>
                        <?php } ?>
                    </div>
                </div>
            <?php } ?>
        </div>
    <?php } ?>
</div>

<!-- 3. TAB: LOGS & EXCEPTIONS -->
<div class="dash-tab-pane" id="pane-logs">
    <!-- Exceptions Section -->
    <?php if ($exceptions !== []) { ?>
        <div style="margin-bottom: 1.5rem;">
            <div style="display: flex; align-items: center; gap: 0.5rem; margin-bottom: 0.75rem;">
                <span class="dash-badge badge-danger" style="font-size: 13px; padding: 4px 9px;">
                    <?= count($exceptions) ?> Exceptions
                </span>
            </div>
            <?php foreach ($exceptions as $ex) { ?>
                <div class="dash-panel" style="border-color: var(--c-err-border); margin-bottom: 1rem; background: var(--bg-card);">
                    <div class="dash-panel-header" style="background: var(--c-err-bg); border-color: var(--c-err-border);">
                        <div style="font-weight: 700; color: var(--c-err); font-family: var(--font-mono);">
                            <?= Template::e($ex['class'] ?? $ex['type'] ?? 'Exception') ?>
                        </div>
                    </div>
                    <div style="padding: 1rem;">
                        <div style="font-size: 14px; font-weight: 600; color: var(--text-primary); margin-bottom: 0.5rem;">
                            <?= Template::e($ex['message'] ?? 'No message') ?>
                        </div>
                        <?php if (isset($ex['file'])) { ?>
                            <div style="font-family: var(--font-mono); font-size: 12px; color: var(--text-muted); margin-bottom: 0.75rem;">
                                <?= Template::e($ex['file']) ?>:<?= Template::e($ex['line'] ?? '') ?>
                            </div>
                        <?php } ?>
                        <pre style="max-height: 400px;"><?= Template::e(DumpView::json($ex)) ?></pre>
                    </div>
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
        </div>

        <?php if ($logs === []) { ?>
            <div style="padding: 3rem; text-align: center; color: var(--text-muted);">
                No application messages were logged for this request.
            </div>
        <?php } else { ?>
            <div class="dash-table-container">
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
                            <tr>
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
        </div>

        <?php if ($events === []) { ?>
            <div style="padding: 3rem; text-align: center; color: var(--text-muted);">
                No events recorded.
            </div>
        <?php } else { ?>
            <div class="dash-table-container">
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
                            <tr>
                                <td class="text-muted font-mono"><?= $idx + 1 ?></td>
                                <td style="font-family: var(--font-mono); font-size: 12.5px; font-weight: 600; color: var(--text-primary);">
                                    <?= Template::e($ev['name']) ?>
                                </td>
                                <td style="font-family: var(--font-mono); font-size: 11.5px; color: var(--text-muted);">
                                    <?= Template::e($ev['line'] !== '' ? $ev['line'] : ($ev['file'] ?? '-')) ?>
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
