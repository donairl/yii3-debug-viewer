<?php

declare(strict_types=1);

use Dxn\DebugViewer\Template;

/**
 * @var list<array<string, mixed>> $rows
 * @var Closure(string): string $viewUrl
 */

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
                    placeholder="Filter path, method, status... ( / )" 
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
        <div class="dash-table-container">
            <table class="dash-table" id="requests-table">
                <thead>
                    <tr>
                        <th style="width: 85px;">Time</th>
                        <th style="width: 75px;">Method</th>
                        <th style="width: 100px;">Status</th>
                        <th>Path & Action</th>
                        <th style="text-align: right; width: 90px;">Queries</th>
                        <th style="text-align: right; width: 85px;">Logs</th>
                        <th style="text-align: right; width: 100px;">Duration</th>
                        <th style="text-align: right; width: 95px;">Memory</th>
                        <th style="text-align: center; width: 75px;">Details</th>
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
                            <td style="text-align: center;">
                                <a href="<?= Template::e($targetUrl) ?>" class="dash-btn dash-btn-icon" title="View Profile Detail" style="display: inline-flex;">
                                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                                        <path d="M5 12h14"></path>
                                        <path d="m12 5 7 7-7 7"></path>
                                    </svg>
                                </a>
                            </td>
                        </tr>
                    <?php } ?>
                </tbody>
            </table>
        </div>
    <?php } ?>
</div>

<script>
    // Live filter and search implementation
    (function() {
        var searchInput = document.getElementById('req-search');
        var methodFilter = document.getElementById('method-filter');
        var statusFilter = document.getElementById('status-filter');
        var countBadge = document.getElementById('visible-count');
        var rows = document.querySelectorAll('.request-row');

        function applyFilter() {
            var q = (searchInput ? searchInput.value : '').toLowerCase().trim();
            var method = methodFilter ? methodFilter.value : 'ALL';
            var statusGroup = statusFilter ? statusFilter.value : 'ALL';
            var visible = 0;

            rows.forEach(function(row) {
                var rMethod = row.getAttribute('data-method') || '';
                var rStatus = parseInt(row.getAttribute('data-status') || '0', 10);
                var rPath = row.getAttribute('data-path') || '';
                var rAction = row.getAttribute('data-action') || '';

                // Method match
                var matchMethod = (method === 'ALL' || rMethod === method);

                // Status match
                var matchStatus = true;
                if (statusGroup === '2XX') matchStatus = (rStatus >= 200 && rStatus < 300);
                else if (statusGroup === '3XX') matchStatus = (rStatus >= 300 && rStatus < 400);
                else if (statusGroup === '4XX') matchStatus = (rStatus >= 400 && rStatus < 500);
                else if (statusGroup === '5XX') matchStatus = (rStatus >= 500);

                // Query match
                var matchQuery = true;
                if (q !== '') {
                    matchQuery = rPath.includes(q) || rAction.includes(q) || rMethod.toLowerCase().includes(q) || String(rStatus).includes(q);
                }

                if (matchMethod && matchStatus && matchQuery) {
                    row.style.display = '';
                    visible++;
                } else {
                    row.style.display = 'none';
                }
            });

            if (countBadge) {
                countBadge.textContent = visible;
            }
        }

        if (searchInput) searchInput.addEventListener('input', applyFilter);
        if (methodFilter) methodFilter.addEventListener('change', applyFilter);
        if (statusFilter) statusFilter.addEventListener('change', applyFilter);

        // Shortcut '/' to focus search
        window.addEventListener('keydown', function(e) {
            if (e.key === '/' && !['INPUT', 'TEXTAREA'].includes(document.activeElement.tagName)) {
                e.preventDefault();
                if (searchInput) {
                    searchInput.focus();
                    searchInput.select();
                }
            }
        });
    })();
</script>
