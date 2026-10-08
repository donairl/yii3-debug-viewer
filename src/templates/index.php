<?php

declare(strict_types=1);

use Dxn\DebugViewer\Template;

/**
 * @var list<array<string, mixed>> $rows
 * @var Closure(string): string $viewUrl
 * @var int $limit
 * @var bool|null $canDelete
 * @var string|null $csrf
 * @var Closure(string): string|null $deleteUrl
 * @var string|null $clearUrl
 * @var int|null $deleted How many dumps the last delete removed.
 */

$canDelete ??= false;
$csrf ??= '';
$deleted ??= null;
$clearUrl ??= '';

$listLimit = (int)($limit ?? 100);

// Calculate overview dashboard metrics
$totalRequests = count($rows);
$totalQueries = 0;
$totalExceptions = 0;
$totalSlowRequests = 0; // > 500ms
$sumDuration = 0.0;
$peakMemoryAll = 0.0;

foreach ($rows as $r) {
    $totalQueries += (int)($r['queries'] ?? 0);
    $totalExceptions += (int)($r['exceptions'] ?? 0) + (int)($r['queryErrors'] ?? 0);
    $dur = (float)($r['durationMs'] ?? 0);
    $sumDuration += $dur;
    if ($dur > 500) {
        $totalSlowRequests++;
    }
    $mem = (float)($r['memoryMb'] ?? 0);
    if ($mem > $peakMemoryAll) {
        $peakMemoryAll = $mem;
    }
}
$avgDuration = $totalRequests > 0 ? $sumDuration / $totalRequests : 0;
?>

<?php if ($deleted !== null) { ?>
    <div class="dash-notice">
        <span>
            <?php if ($deleted === 0) { ?>
                Nothing was deleted: the dump was already gone.
            <?php } else { ?>
                Deleted <b><?= (int)$deleted ?></b> request <?= $deleted === 1 ? 'dump' : 'dumps' ?>.
            <?php } ?>
        </span>
    </div>
<?php } ?>

<!-- Metric KPI Cards -->
<div class="dash-metrics-grid">
    <div class="dash-metric-card accent-indigo">
        <div class="dash-metric-label">
            <span>Total Requests</span>
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <circle cx="12" cy="12" r="10"></circle>
                <polyline points="12 6 12 12 16 14"></polyline>
            </svg>
        </div>
        <div class="dash-metric-value"><?= Template::e($totalRequests) ?></div>
        <div class="dash-metric-sub">Recorded in runtime/debug</div>
    </div>

    <div class="dash-metric-card accent-sky">
        <div class="dash-metric-label">
            <span>Avg Response Time</span>
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"></polygon>
            </svg>
        </div>
        <div class="dash-metric-value"><?= Template::e(Template::formatMs($avgDuration)) ?></div>
        <div class="dash-metric-sub"><?= $totalSlowRequests > 0 ? $totalSlowRequests . ' requests > 500ms' : 'Fast application throughput' ?></div>
    </div>

    <div class="dash-metric-card accent-emerald">
        <div class="dash-metric-label">
            <span>Database Queries</span>
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <ellipse cx="12" cy="5" rx="9" ry="3"></ellipse>
                <path d="M3 5V19A9 3 0 0 0 21 19V5"></path>
                <path d="M3 12A9 3 0 0 0 21 12"></path>
            </svg>
        </div>
        <div class="dash-metric-value"><?= Template::e($totalQueries) ?></div>
        <div class="dash-metric-sub"><?= Template::e($totalRequests > 0 ? number_format($totalQueries / $totalRequests, 1) : 0) ?> queries / request avg</div>
    </div>

    <div class="dash-metric-card <?= $totalExceptions > 0 ? 'accent-rose' : 'accent-amber' ?>">
        <div class="dash-metric-label">
            <span>Exceptions & Errors</span>
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="m21.73 18-8-14a2 2 0 0 0-3.48 0l-8 14A2 2 0 0 0 4 21h16a2 2 0 0 0 1.73-3Z"></path>
                <line x1="12" y1="9" x2="12" y2="13"></line>
                <line x1="12" y1="17" x2="12.01" y2="17"></line>
            </svg>
        </div>
        <div class="dash-metric-value" style="<?= $totalExceptions > 0 ? 'color: var(--c-err);' : '' ?>">
            <?= Template::e($totalExceptions) ?>
        </div>
        <div class="dash-metric-sub"><?= $totalExceptions > 0 ? 'Errors detected in captured requests' : 'Zero unhandled exceptions' ?></div>
    </div>

    <div class="dash-metric-card accent-indigo">
        <div class="dash-metric-label">
            <span>Peak Memory</span>
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M6 19v-9a6 6 0 0 1 12 0v9"></path>
                <line x1="10" y1="19" x2="14" y2="19"></line>
            </svg>
        </div>
        <div class="dash-metric-value"><?= Template::e(number_format($peakMemoryAll, 2)) ?> <span style="font-size:1rem;color:var(--text-muted);">MB</span></div>
        <div class="dash-metric-sub">Across active request set</div>
    </div>
</div>

<div class="dash-panel">
    <!-- Toolbar: Search, Filters & Stats -->
    <div class="dash-panel-header" style="flex-wrap: wrap;">
        <div class="dash-panel-title">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                <line x1="8" y1="6" x2="21" y2="6"></line>
                <line x1="8" y1="12" x2="21" y2="12"></line>
                <line x1="8" y1="18" x2="21" y2="18"></line>
                <line x1="3" y1="6" x2="3.01" y2="6"></line>
                <line x1="3" y1="12" x2="3.01" y2="12"></line>
                <line x1="3" y1="18" x2="3.01" y2="18"></line>
            </svg>
            <span>Request History</span>
            <span class="dash-badge" id="visible-count"><?= Template::e($totalRequests) ?></span>
        </div>

        <div style="display: flex; align-items: center; gap: 0.6rem; flex-wrap: wrap;">
            <!-- Instant Search Input -->
            <div style="position: relative;">
                <input 
                    type="text" 
                    id="req-search" 
                    placeholder="Path, route, status… ( / )" 
                    style="background: var(--bg-input); border: 1px solid var(--border-subtle); border-radius: var(--radius-sm); color: var(--text-primary); padding: 0.38rem 0.75rem 0.38rem 2rem; font-size: 12.5px; width: 240px; outline: none; transition: var(--transition);"
                    onfocus="this.style.borderColor='var(--accent)'; this.style.width='300px';"
                    onblur="this.style.borderColor='var(--border-subtle)'; if(!this.value) this.style.width='240px';"
                >
                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="var(--text-muted)" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" style="position: absolute; left: 9px; top: 50%; transform: translateY(-50%); pointer-events: none;">
                    <circle cx="11" cy="11" r="8"></circle>
                    <line x1="21" y1="21" x2="16.65" y2="16.65"></line>
                </svg>
            </div>

            <!-- Method Filter Selector -->
            <select id="method-filter" style="background: var(--bg-input); border: 1px solid var(--border-subtle); border-radius: var(--radius-sm); color: var(--text-secondary); padding: 0.38rem 0.65rem; font-size: 12.5px; outline: none; cursor: pointer;">
                <option value="ALL">All Methods</option>
                <option value="GET">GET</option>
                <option value="POST">POST</option>
                <option value="PUT">PUT</option>
                <option value="DELETE">DELETE</option>
            </select>

            <!-- Status Filter Selector -->
            <select id="status-filter" style="background: var(--bg-input); border: 1px solid var(--border-subtle); border-radius: var(--radius-sm); color: var(--text-secondary); padding: 0.38rem 0.65rem; font-size: 12.5px; outline: none; cursor: pointer;">
                <option value="ALL">All Status</option>
                <option value="2XX">2xx Success</option>
                <option value="3XX">3xx Redirect</option>
                <option value="4XX">4xx Client Error</option>
                <option value="5XX">5xx Server Error</option>
            </select>
        </div>
    </div>

    <?php if ($rows === []) { ?>
        <div style="padding: 4rem 2rem; text-align: center;">
            <div style="width: 56px; height: 56px; border-radius: 50%; background: var(--bg-surface); display: inline-flex; align-items: center; justify-content: center; margin-bottom: 1rem; color: var(--text-muted);">
                <svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <circle cx="12" cy="12" r="10"></circle>
                    <path d="M8 12h8"></path>
                </svg>
            </div>
            <h3 style="font-size: 16px; font-weight: 600; margin-bottom: 0.4rem; color: var(--text-primary);">No Request Dumps Found</h3>
            <p style="color: var(--text-muted); font-size: 13px; max-width: 440px; margin: 0 auto 1.25rem;">Make any HTTP request to the application, then refresh this page to inspect complete execution metrics.</p>
            <div style="display: inline-flex; align-items: center; gap: 0.5rem; background: var(--bg-surface); padding: 0.4rem 0.9rem; border-radius: var(--radius-sm); font-size: 12px; font-family: var(--font-mono); color: var(--text-secondary); border: 1px solid var(--border-subtle);">
                <span>Reset anytime:</span>
                <code>php yii debug:reset</code>
            </div>
        </div>
    <?php } else { ?>
        <div class="flt flt-bar" id="idx-filters">
            <button type="button" class="flt-toggle" id="f-errors" title="Requests with an exception or a failed query">Errors only</button>
            <button type="button" class="flt-toggle" id="f-slow" title="Requests slower than 500 ms">Slow &gt;500 ms</button>
            <input type="number" min="0" step="1" class="flt-input flt-num" id="f-minq" placeholder="Queries ≥" aria-label="Minimum number of queries">
            <select class="flt-select" id="f-since" aria-label="Time range">
                <option value="">Any time</option>
                <option value="300">Last 5 minutes</option>
                <option value="3600">Last hour</option>
                <option value="86400">Last 24 hours</option>
            </select>
            <span class="flt-count" id="f-count"></span>
            <button type="button" class="flt-clear" id="f-clear" hidden>Clear</button>
            <div style="margin-left: auto; display: flex; align-items: center; gap: 0.75rem; flex-wrap: wrap;">
                <?php if ($totalRequests >= $listLimit) { ?>
                    <span class="text-muted" style="font-size: 11.5px;">Newest <?= (int)$listLimit ?> dumps loaded, filters apply to these (<code>listLimit</code>).</span>
                <?php } ?>
                <?php if ($canDelete) { ?>
                    <form method="post" action="<?= Template::e($clearUrl) ?>" class="inline" onsubmit="return confirm('Delete ALL request dumps? This cannot be undone.');">
                        <input type="hidden" name="_csrf" value="<?= Template::e($csrf) ?>">
                        <button type="submit" class="flt-toggle dash-btn-danger" title="Delete every collected request">Delete all</button>
                    </form>
                <?php } ?>
            </div>
        </div>

        <div class="dash-table-container">
            <table class="dash-table" id="requests-table" data-now="<?= time() ?>">
                <thead>
                    <tr>
                        <th style="width: 85px;" data-sort="time" data-sort-first="desc">Time</th>
                        <th style="width: 75px;" data-sort="method" data-sort-first="asc">Method</th>
                        <th style="width: 100px;" data-sort="status" data-sort-first="desc">Status</th>
                        <th data-sort="path" data-sort-first="asc">Path & Action</th>
                        <th style="text-align: right; width: 90px;" data-sort="queries" data-sort-first="desc">Queries</th>
                        <th style="text-align: right; width: 85px;" data-sort="logs" data-sort-first="desc">Logs</th>
                        <th style="text-align: right; width: 100px;" data-sort="duration" data-sort-first="desc">Duration</th>
                        <th style="text-align: right; width: 95px;" data-sort="memory" data-sort-first="desc">Memory</th>
                        <th style="text-align: center; width: <?= $canDelete ? 105 : 75 ?>px;">Details</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($rows as $row) { ?>
                        <?php
                        $status = (int)$row['status'];
                        $method = (string)$row['method'];
                        $path = (string)($row['path'] !== '' ? $row['path'] : '/');
                        $action = (string)($row['action'] ?? '');
                        $problems = (int)($row['exceptions'] ?? 0) + (int)($row['queryErrors'] ?? 0);
                        $dur = (float)$row['durationMs'];
                        $id = (string)$row['id'];
                        $targetUrl = $viewUrl($id);
                        ?>
                        <tr 
                            class="request-row" 
                            data-method="<?= Template::e(strtoupper($method)) ?>"
                            data-status="<?= Template::e($status) ?>"
                            data-path="<?= Template::e(strtolower($path)) ?>"
                            data-action="<?= Template::e(strtolower($action)) ?>"
                            data-route="<?= Template::e(strtolower((string)($row['routeName'] ?? ''))) ?>"
                            data-problems="<?= $problems ?>"
                            data-queries="<?= (int)$row['queries'] ?>"
                            data-logs="<?= (int)$row['logs'] ?>"
                            data-duration="<?= Template::e(round($dur, 3)) ?>"
                            data-memory="<?= Template::e(round((float)$row['memoryMb'], 3)) ?>"
                            data-time="<?= (int)$row['time'] ?>"
                            style="cursor: pointer;"
                            onclick="if(!event.target.closest('a, button, .copy-btn')) window.location='<?= Template::e($targetUrl) ?>'"
                        >
                            <!-- Time -->
                            <td style="white-space: nowrap;">
                                <div style="font-family: var(--font-mono); font-weight: 500; font-size: 12px; color: var(--text-primary);">
                                    <?= Template::e(date('H:i:s', (int)$row['time'])) ?>
                                </div>
                                <div style="font-size: 10.5px; color: var(--text-muted);">
                                    <?= Template::e(Template::timeAgo($row['time'])) ?>
                                </div>
                            </td>

                            <!-- Method -->
                            <td>
                                <span class="dash-pill-method <?= Template::methodClass($method) ?>">
                                    <?= Template::e($method) ?>
                                </span>
                            </td>

                            <!-- Status -->
                            <td>
                                <span class="dash-pill-status <?= Template::statusClass($status) ?>">
                                    <span class="status-dot"></span>
                                    <span><?= Template::e($status) ?></span>
                                    <span style="font-size: 10px; font-weight: 500; opacity: 0.85;"><?= Template::e(Template::statusText($status)) ?></span>
                                </span>
                            </td>

                            <!-- Path & Action -->
                            <td style="max-width: 380px;">
                                <div style="display: flex; align-items: center; gap: 0.4rem;">
                                    <a href="<?= Template::e($targetUrl) ?>" style="font-weight: 600; font-family: var(--font-mono); font-size: 13px; color: var(--text-primary);" class="text-truncate">
                                        <?= Template::e($path) ?>
                                    </a>
                                    <?php if ($problems > 0) { ?>
                                        <span class="dash-badge badge-danger" title="<?= $problems ?> exceptions / DB errors">
                                            <?= $problems ?> err
                                        </span>
                                    <?php } ?>
                                </div>
                                <?php if ($action !== '') { ?>
                                    <div style="font-size: 11px; color: var(--text-muted); font-family: var(--font-mono); margin-top: 1px;" class="text-truncate">
                                        <?= Template::e($action) ?>
                                    </div>
                                <?php } ?>
                            </td>

                            <!-- Database Queries -->
                            <td style="text-align: right;">
                                <?php if ((int)$row['queries'] > 0) { ?>
                                    <span class="dash-badge" style="background: var(--bg-surface-subtle);">
                                        <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                                            <ellipse cx="12" cy="5" rx="9" ry="3"></ellipse>
                                            <path d="M3 5V19A9 3 0 0 0 21 19V5"></path>
                                            <path d="M3 12A9 3 0 0 0 21 12"></path>
                                        </svg>
                                        <?= Template::e($row['queries']) ?>
                                    </span>
                                <?php } else { ?>
                                    <span class="text-muted" style="font-size: 12px;">0</span>
                                <?php } ?>
                            </td>

                            <!-- Logs -->
                            <td style="text-align: right;">
                                <?php if ((int)$row['logs'] > 0) { ?>
                                    <span class="dash-badge">
                                        <?= Template::e($row['logs']) ?>
                                    </span>
                                <?php } else { ?>
                                    <span class="text-muted" style="font-size: 12px;">0</span>
                                <?php } ?>
                            </td>

                            <!-- Duration -->
                            <td style="text-align: right; white-space: nowrap;">
                                <span style="font-family: var(--font-mono); font-size: 12.5px; font-weight: 600; color: <?= $dur > 500 ? 'var(--c-err)' : ($dur > 200 ? 'var(--c-warn)' : 'var(--text-primary)') ?>;">
                                    <?= Template::e(Template::formatMs($dur)) ?>
                                </span>
                            </td>

                            <!-- Memory -->
                            <td style="text-align: right; white-space: nowrap;">
                                <span style="font-family: var(--font-mono); font-size: 12px; color: var(--text-secondary);">
                                    <?= Template::e(number_format((float)$row['memoryMb'], 2)) ?> MB
                                </span>
                            </td>

                            <!-- Action button -->
                            <td style="text-align: center; white-space: nowrap;">
                                <div style="display: inline-flex; align-items: center; gap: 0.35rem;">
                                <a href="<?= Template::e($targetUrl) ?>" class="dash-btn dash-btn-icon" title="View Profile Detail" style="display: inline-flex;">
                                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                                        <path d="M5 12h14"></path>
                                        <path d="m12 5 7 7-7 7"></path>
                                    </svg>
                                </a>
                                <?php if ($canDelete) { ?>
                                    <form method="post" action="<?= Template::e($deleteUrl($id)) ?>" class="inline" onsubmit="return confirm('Delete this request dump? This cannot be undone.');">
                                        <input type="hidden" name="_csrf" value="<?= Template::e($csrf) ?>">
                                        <button type="submit" class="dash-btn dash-btn-icon dash-btn-danger" title="Delete this dump"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"></polyline><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"></path><path d="M10 11v6M14 11v6"></path><path d="M9 6V4a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v2"></path></svg></button>
                                    </form>
                                <?php } ?>
                                </div>
                            </td>
                        </tr>
                    <?php } ?>
                    <tr class="idx-empty" hidden>
                        <td colspan="9" style="padding: 2.5rem; text-align: center; color: var(--text-muted);">No requests match the current filters.</td>
                    </tr>
                </tbody>
            </table>
        </div>
    <?php } ?>
</div>

<script>
    // The "Deleted N dumps" notice is shown once: take it out of the URL so a reload does not repeat it
    (function() {
        try {
            var u = new URL(window.location.href);
            if (u.searchParams.has('deleted')) {
                u.searchParams.delete('deleted');
                history.replaceState(null, '', u.pathname + u.search + u.hash);
            }
        } catch (e) {}
    })();

    // Request list: filters, sorting and deep links. State lives in the query string
    // (?q=&method=&status=&err=1&slow=1&minq=&since=&sort=&dir=) so a filtered view can be bookmarked or shared.
    (function() {
        var table = document.getElementById('requests-table');
        if (!table) { return; }

        var tbody = table.tBodies[0];
        var rows = Array.prototype.slice.call(tbody.querySelectorAll('.request-row'));
        var emptyRow = tbody.querySelector('.idx-empty');
        var heads = table.querySelectorAll('th[data-sort]');
        var ctl = {
            q: document.getElementById('req-search'),
            method: document.getElementById('method-filter'),
            status: document.getElementById('status-filter'),
            since: document.getElementById('f-since'),
            minq: document.getElementById('f-minq'),
            err: document.getElementById('f-errors'),
            slow: document.getElementById('f-slow')
        };
        var countBadge = document.getElementById('visible-count');
        var countText = document.getElementById('f-count');
        var clearBtn = document.getElementById('f-clear');

        var serverNow = parseInt(table.getAttribute('data-now') || '0', 10);
        var loadedAt = Date.now();
        var sort = { key: '', dir: '' };

        rows.forEach(function(row, i) {
            row._i = i;
            row._text = [
                row.getAttribute('data-path'), row.getAttribute('data-action'), row.getAttribute('data-route'),
                (row.getAttribute('data-method') || '').toLowerCase(), row.getAttribute('data-status')
            ].join(' ');
        });

        function num(row, attr) { return parseFloat(row.getAttribute('data-' + attr) || '0') || 0; }

        function statusMatches(group, status) {
            if (group === '2XX') { return status >= 200 && status < 300; }
            if (group === '3XX') { return status >= 300 && status < 400; }
            if (group === '4XX') { return status >= 400 && status < 500; }
            if (group === '5XX') { return status >= 500; }
            return true;
        }

        function readUrl() {
            var p = new URLSearchParams(window.location.search);
            if (ctl.q) { ctl.q.value = p.get('q') || ''; }
            if (ctl.method) { ctl.method.value = p.get('method') || 'ALL'; if (!ctl.method.value) { ctl.method.value = 'ALL'; } }
            if (ctl.status) { ctl.status.value = p.get('status') || 'ALL'; if (!ctl.status.value) { ctl.status.value = 'ALL'; } }
            if (ctl.since) { ctl.since.value = p.get('since') || ''; }
            if (ctl.minq) { ctl.minq.value = /^\d+$/.test(p.get('minq') || '') ? p.get('minq') : ''; }
            if (ctl.err) { ctl.err.classList.toggle('on', p.get('err') === '1'); }
            if (ctl.slow) { ctl.slow.classList.toggle('on', p.get('slow') === '1'); }

            var key = p.get('sort') || '';
            var valid = Array.prototype.some.call(heads, function(h) { return h.getAttribute('data-sort') === key; });
            sort = valid ? { key: key, dir: p.get('dir') === 'asc' ? 'asc' : 'desc' } : { key: '', dir: '' };
        }

        function writeUrl(state) {
            // Keep parameters this page does not own.
            var p = new URLSearchParams(window.location.search);
            ['q', 'method', 'status', 'since', 'minq', 'err', 'slow', 'sort', 'dir'].forEach(function(k) { p.delete(k); });
            if (state.q) { p.set('q', state.q); }
            if (state.method !== 'ALL') { p.set('method', state.method); }
            if (state.statusGroup !== 'ALL') { p.set('status', state.statusGroup); }
            if (state.since) { p.set('since', state.since); }
            if (state.minq) { p.set('minq', state.minq); }
            if (state.err) { p.set('err', '1'); }
            if (state.slow) { p.set('slow', '1'); }
            if (sort.key) { p.set('sort', sort.key); p.set('dir', sort.dir); }
            var qs = p.toString();
            try {
                history.replaceState(null, '', window.location.pathname + (qs ? '?' + qs : '') + window.location.hash);
            } catch (e) {}
        }

        function currentState() {
            return {
                q: (ctl.q ? ctl.q.value : '').toLowerCase().trim(),
                method: ctl.method ? ctl.method.value : 'ALL',
                statusGroup: ctl.status ? ctl.status.value : 'ALL',
                since: ctl.since ? ctl.since.value : '',
                minq: ctl.minq && /^\d+$/.test(ctl.minq.value) ? ctl.minq.value : '',
                err: !!ctl.err && ctl.err.classList.contains('on'),
                slow: !!ctl.slow && ctl.slow.classList.contains('on')
            };
        }

        function apply() {
            var st = currentState();
            var terms = st.q.split(/\s+/).filter(Boolean);
            var now = serverNow + (Date.now() - loadedAt) / 1000;
            var visible = 0;

            rows.forEach(function(row) {
                var ok = (st.method === 'ALL' || row.getAttribute('data-method') === st.method)
                    && statusMatches(st.statusGroup, num(row, 'status'))
                    && (!st.err || num(row, 'problems') > 0)
                    && (!st.slow || num(row, 'duration') > 500)
                    && (!st.minq || num(row, 'queries') >= parseInt(st.minq, 10))
                    && (!st.since || !serverNow || now - num(row, 'time') <= parseInt(st.since, 10));
                if (ok && terms.length) {
                    ok = terms.every(function(t) { return row._text.indexOf(t) !== -1; });
                }
                row.hidden = !ok;
                if (ok) { visible++; }
            });

            var sorted = rows.slice();
            if (sort.key) {
                var factor = sort.dir === 'asc' ? 1 : -1;
                sorted.sort(function(a, b) {
                    var x, y;
                    if (sort.key === 'path' || sort.key === 'method') {
                        x = a.getAttribute('data-' + sort.key); y = b.getAttribute('data-' + sort.key);
                        var c = x < y ? -1 : (x > y ? 1 : 0);
                        return c !== 0 ? c * factor : a._i - b._i;
                    }
                    x = num(a, sort.key); y = num(b, sort.key);
                    return x !== y ? (x - y) * factor : a._i - b._i;
                });
            } else {
                sorted.sort(function(a, b) { return a._i - b._i; });
            }
            sorted.forEach(function(row) { tbody.insertBefore(row, emptyRow); });

            heads.forEach(function(h) {
                var on = h.getAttribute('data-sort') === sort.key;
                h.setAttribute('aria-sort', on ? (sort.dir === 'asc' ? 'ascending' : 'descending') : 'none');
            });

            var active = terms.length > 0 || st.method !== 'ALL' || st.statusGroup !== 'ALL' || !!st.since || !!st.minq || st.err || st.slow;
            if (countBadge) { countBadge.textContent = active ? visible + ' / ' + rows.length : rows.length; }
            if (countText) { countText.textContent = active ? visible + ' of ' + rows.length + ' shown' : ''; }
            if (clearBtn) { clearBtn.hidden = !active; }
            if (emptyRow) { emptyRow.hidden = visible !== 0; }

            writeUrl(st);
        }

        heads.forEach(function(h) {
            h.addEventListener('click', function() {
                var key = this.getAttribute('data-sort');
                var first = this.getAttribute('data-sort-first') === 'asc' ? 'asc' : 'desc';
                var second = first === 'asc' ? 'desc' : 'asc';
                if (sort.key !== key) { sort = { key: key, dir: first }; }
                else if (sort.dir === first) { sort = { key: key, dir: second }; }
                else { sort = { key: '', dir: '' }; }
                apply();
            });
        });

        if (ctl.q) {
            ctl.q.addEventListener('input', apply);
            ctl.q.addEventListener('keydown', function(e) {
                if (e.key === 'Escape') { this.value = ''; this.blur(); apply(); }
            });
        }
        [ctl.method, ctl.status, ctl.since].forEach(function(c) { if (c) { c.addEventListener('change', apply); } });
        if (ctl.minq) { ctl.minq.addEventListener('input', apply); }
        [ctl.err, ctl.slow].forEach(function(t) {
            if (t) { t.addEventListener('click', function() { this.classList.toggle('on'); apply(); }); }
        });
        if (clearBtn) {
            clearBtn.addEventListener('click', function() {
                if (ctl.q) { ctl.q.value = ''; }
                if (ctl.method) { ctl.method.value = 'ALL'; }
                if (ctl.status) { ctl.status.value = 'ALL'; }
                if (ctl.since) { ctl.since.value = ''; }
                if (ctl.minq) { ctl.minq.value = ''; }
                if (ctl.err) { ctl.err.classList.remove('on'); }
                if (ctl.slow) { ctl.slow.classList.remove('on'); }
                apply();
            });
        }

        // Shortcut '/' to focus search
        window.addEventListener('keydown', function(e) {
            if (e.key === '/' && !e.ctrlKey && !e.metaKey && !e.altKey
                && ['INPUT', 'TEXTAREA', 'SELECT'].indexOf(document.activeElement.tagName) === -1) {
                e.preventDefault();
                if (ctl.q) {
                    ctl.q.focus();
                    ctl.q.select();
                }
            }
        });

        readUrl();
        apply();
    })();
</script>
