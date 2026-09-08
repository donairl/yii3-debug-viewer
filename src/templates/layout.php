<?php

declare(strict_types=1);

use Dxn\DebugViewer\Template;

/**
 * @var string $title
 * @var string $indexUrl
 * @var string $content
 * @var string|null $template
 * @var string|null $id
 * @var array<string, mixed>|null $meta
 */
?>
<!DOCTYPE html>
<html lang="en" data-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0">
    <title><?= Template::e($title) ?> · Yii3 DevProfiler</title>
    <script>
        // Immediately apply saved theme to avoid flash
        (function() {
            try {
                var saved = localStorage.getItem('dsh_debug_theme');
                var theme = saved || (window.matchMedia('(prefers-color-scheme: light)').matches ? 'light' : 'dark');
                document.documentElement.setAttribute('data-theme', theme);
            } catch (e) {}
        })();
    </script>
    <style>
        :root {
            --font-sans: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
            --font-mono: ui-monospace, SFMono-Regular, "JetBrains Mono", Menlo, Monaco, Consolas, monospace;
            --radius-sm: 6px;
            --radius-md: 10px;
            --radius-lg: 14px;
            --radius-xl: 18px;
            --shadow-sm: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
            --shadow-md: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -2px rgba(0, 0, 0, 0.1);
            --shadow-lg: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -4px rgba(0, 0, 0, 0.1);
            --transition: all 0.18s cubic-bezier(0.4, 0, 0.2, 1);
        }

        /* DARK THEME (DEFAULT) */
        [data-theme="dark"] {
            --bg-body: #090d16;
            --bg-header: rgba(15, 23, 42, 0.82);
            --bg-card: #0f172a;
            --bg-card-hover: #162036;
            --bg-surface: #1e293b;
            --bg-surface-subtle: #131d31;
            --bg-input: #131d31;
            --border-subtle: rgba(255, 255, 255, 0.08);
            --border-strong: rgba(255, 255, 255, 0.16);
            --text-primary: #f8fafc;
            --text-secondary: #94a3b8;
            --text-muted: #64748b;
            --accent: #6366f1;
            --accent-glow: rgba(99, 102, 241, 0.25);
            --accent-hover: #818cf8;
            --brand-gradient: linear-gradient(135deg, #6366f1 0%, #06b6d4 100%);
            --code-bg: #070a12;
            --code-border: rgba(255, 255, 255, 0.07);
            
            --c-ok: #34d399;
            --c-ok-bg: rgba(52, 211, 153, 0.12);
            --c-ok-border: rgba(52, 211, 153, 0.25);
            
            --c-info: #38bdf8;
            --c-info-bg: rgba(56, 189, 248, 0.12);
            --c-info-border: rgba(56, 189, 248, 0.25);
            
            --c-warn: #fbbf24;
            --c-warn-bg: rgba(251, 191, 36, 0.12);
            --c-warn-border: rgba(251, 191, 36, 0.25);
            
            --c-err: #f87171;
            --c-err-bg: rgba(248, 113, 113, 0.14);
            --c-err-border: rgba(248, 113, 113, 0.3);

            --method-get-color: #38bdf8;
            --method-get-bg: rgba(56, 189, 248, 0.14);
            --method-post-color: #34d399;
            --method-post-bg: rgba(52, 211, 153, 0.14);
            --method-put-color: #fbbf24;
            --method-put-bg: rgba(251, 191, 36, 0.14);
            --method-del-color: #f87171;
            --method-del-bg: rgba(248, 113, 113, 0.14);
        }

        /* LIGHT THEME */
        [data-theme="light"] {
            --bg-body: #f8fafc;
            --bg-header: rgba(255, 255, 255, 0.85);
            --bg-card: #ffffff;
            --bg-card-hover: #f1f5f9;
            --bg-surface: #f1f5f9;
            --bg-surface-subtle: #f8fafc;
            --bg-input: #ffffff;
            --border-subtle: #e2e8f0;
            --border-strong: #cbd5e1;
            --text-primary: #0f172a;
            --text-secondary: #475569;
            --text-muted: #94a3b8;
            --accent: #4f46e5;
            --accent-glow: rgba(79, 70, 229, 0.15);
            --accent-hover: #4338ca;
            --brand-gradient: linear-gradient(135deg, #4f46e5 0%, #0284c7 100%);
            --code-bg: #f8fafc;
            --code-border: #e2e8f0;
            
            --c-ok: #059669;
            --c-ok-bg: rgba(5, 150, 105, 0.1);
            --c-ok-border: rgba(5, 150, 105, 0.2);
            
            --c-info: #0284c7;
            --c-info-bg: rgba(2, 132, 199, 0.1);
            --c-info-border: rgba(2, 132, 199, 0.2);
            
            --c-warn: #d97706;
            --c-warn-bg: rgba(217, 119, 6, 0.1);
            --c-warn-border: rgba(217, 119, 6, 0.2);
            
            --c-err: #dc2626;
            --c-err-bg: rgba(220, 38, 38, 0.1);
            --c-err-border: rgba(220, 38, 38, 0.2);

            --method-get-color: #0284c7;
            --method-get-bg: rgba(2, 132, 199, 0.1);
            --method-post-color: #059669;
            --method-post-bg: rgba(5, 150, 105, 0.1);
            --method-put-color: #d97706;
            --method-put-bg: rgba(217, 119, 6, 0.1);
            --method-del-color: #dc2626;
            --method-del-bg: rgba(220, 38, 38, 0.1);
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            background-color: var(--bg-body);
            color: var(--text-primary);
            font-family: var(--font-sans);
            font-size: 13.5px;
            line-height: 1.55;
            -webkit-font-smoothing: antialiased;
            -moz-osx-font-smoothing: grayscale;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }

        a {
            color: var(--accent);
            text-decoration: none;
            transition: var(--transition);
        }
        a:hover {
            color: var(--accent-hover);
        }

        /* Top Header Navbar */
        .dash-header {
            position: sticky;
            top: 0;
            z-index: 50;
            background: var(--bg-header);
            backdrop-filter: blur(16px);
            -webkit-backdrop-filter: blur(16px);
            border-bottom: 1px solid var(--border-subtle);
            padding: 0.65rem 1.5rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 1rem;
        }

        .dash-brand {
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .dash-logo-icon {
            width: 32px;
            height: 32px;
            border-radius: var(--radius-sm);
            background: var(--brand-gradient);
            display: flex;
            align-items: center;
            justify-content: center;
            color: #ffffff;
            box-shadow: 0 2px 10px var(--accent-glow);
            flex-shrink: 0;
        }

        .dash-brand-title {
            font-weight: 700;
            font-size: 14px;
            letter-spacing: -0.01em;
            color: var(--text-primary);
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .dash-env-pill {
            font-size: 10px;
            font-weight: 700;
            text-transform: uppercase;
            padding: 2px 7px;
            border-radius: 9999px;
            background: var(--c-info-bg);
            color: var(--c-info);
            border: 1px solid var(--c-info-border);
            letter-spacing: 0.04em;
        }

        .dash-nav {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .dash-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.4rem;
            padding: 0.42rem 0.8rem;
            font-size: 12.5px;
            font-weight: 500;
            border-radius: var(--radius-sm);
            background: var(--bg-surface);
            color: var(--text-secondary);
            border: 1px solid var(--border-subtle);
            cursor: pointer;
            transition: var(--transition);
            white-space: nowrap;
        }
        .dash-btn:hover {
            background: var(--bg-card-hover);
            color: var(--text-primary);
            border-color: var(--border-strong);
        }
        .dash-btn-primary {
            background: var(--accent);
            color: #ffffff;
            border-color: transparent;
        }
        .dash-btn-primary:hover {
            background: var(--accent-hover);
            color: #ffffff;
        }

        .dash-btn-icon {
            padding: 0.45rem;
            width: 32px;
            height: 32px;
            border-radius: var(--radius-sm);
        }

        .dash-main {
            flex: 1;
            padding: 1.5rem 1.5rem 3rem;
            max-width: 1400px;
            width: 100%;
            margin: 0 auto;
        }

        /* Metric KPI Cards Grid */
        .dash-metrics-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 0.85rem;
            margin-bottom: 1.5rem;
        }

        .dash-metric-card {
            background: var(--bg-card);
            border: 1px solid var(--border-subtle);
            border-radius: var(--radius-md);
            padding: 1rem 1.15rem;
            display: flex;
            flex-direction: column;
            gap: 0.35rem;
            position: relative;
            overflow: hidden;
            transition: var(--transition);
        }
        .dash-metric-card:hover {
            border-color: var(--border-strong);
            transform: translateY(-1px);
        }
        .dash-metric-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 2px;
            background: transparent;
            transition: var(--transition);
        }
        .dash-metric-card.accent-indigo::before { background: #6366f1; }
        .dash-metric-card.accent-emerald::before { background: #10b981; }
        .dash-metric-card.accent-amber::before { background: #f59e0b; }
        .dash-metric-card.accent-rose::before { background: #ef4444; }
        .dash-metric-card.accent-sky::before { background: #0284c7; }

        .dash-metric-label {
            font-size: 11.5px;
            text-transform: uppercase;
            letter-spacing: 0.04em;
            color: var(--text-muted);
            font-weight: 600;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        .dash-metric-value {
            font-size: 1.45rem;
            font-weight: 700;
            letter-spacing: -0.02em;
            color: var(--text-primary);
            line-height: 1.2;
            display: flex;
            align-items: baseline;
            gap: 0.3rem;
        }
        .dash-metric-sub {
            font-size: 11px;
            color: var(--text-muted);
        }

        /* HTTP Method Pills */
        .dash-pill-method {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-family: var(--font-mono);
            font-weight: 700;
            font-size: 11px;
            padding: 2px 7px;
            border-radius: var(--radius-sm);
            text-transform: uppercase;
            letter-spacing: 0.03em;
        }
        .method-get {
            color: var(--method-get-color);
            background: var(--method-get-bg);
            border: 1px solid rgba(56, 189, 248, 0.25);
        }
        .method-post {
            color: var(--method-post-color);
            background: var(--method-post-bg);
            border: 1px solid rgba(52, 211, 153, 0.25);
        }
        .method-put {
            color: var(--method-put-color);
            background: var(--method-put-bg);
            border: 1px solid rgba(251, 191, 36, 0.25);
        }
        .method-delete {
            color: var(--method-del-color);
            background: var(--method-del-bg);
            border: 1px solid rgba(248, 113, 113, 0.25);
        }
        .method-default {
            color: var(--text-secondary);
            background: var(--bg-surface);
            border: 1px solid var(--border-subtle);
        }

        /* Status Badge */
        .dash-pill-status {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            font-family: var(--font-mono);
            font-weight: 700;
            font-size: 11.5px;
            padding: 2px 8px;
            border-radius: 9999px;
            white-space: nowrap;
        }
        .status-dot {
            width: 6px;
            height: 6px;
            border-radius: 50%;
            display: inline-block;
        }
        .status-2xx {
            color: var(--c-ok);
            background: var(--c-ok-bg);
            border: 1px solid var(--c-ok-border);
        }
        .status-2xx .status-dot { background: var(--c-ok); box-shadow: 0 0 6px var(--c-ok); }
        .status-3xx {
            color: var(--c-info);
            background: var(--c-info-bg);
            border: 1px solid var(--c-info-border);
        }
        .status-3xx .status-dot { background: var(--c-info); }
        .status-4xx {
            color: var(--c-warn);
            background: var(--c-warn-bg);
            border: 1px solid var(--c-warn-border);
        }
        .status-4xx .status-dot { background: var(--c-warn); }
        .status-5xx {
            color: var(--c-err);
            background: var(--c-err-bg);
            border: 1px solid var(--c-err-border);
        }
        .status-5xx .status-dot { background: var(--c-err); box-shadow: 0 0 6px var(--c-err); }

        /* Badge Counters */
        .dash-badge {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            font-family: var(--font-mono);
            font-size: 11px;
            font-weight: 600;
            padding: 2px 7px;
            border-radius: var(--radius-sm);
            background: var(--bg-surface);
            color: var(--text-secondary);
            border: 1px solid var(--border-subtle);
        }
        .dash-badge.badge-danger {
            background: var(--c-err-bg);
            color: var(--c-err);
            border-color: var(--c-err-border);
        }
        .dash-badge.badge-warning {
            background: var(--c-warn-bg);
            color: var(--c-warn);
            border-color: var(--c-warn-border);
        }
        .dash-badge.badge-success {
            background: var(--c-ok-bg);
            color: var(--c-ok);
            border-color: var(--c-ok-border);
        }

        /* Card Container */
        .dash-panel {
            background: var(--bg-card);
            border: 1px solid var(--border-subtle);
            border-radius: var(--radius-md);
            overflow: hidden;
            box-shadow: var(--shadow-sm);
        }

        .dash-panel-header {
            padding: 0.9rem 1.25rem;
            border-bottom: 1px solid var(--border-subtle);
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 1rem;
            background: var(--bg-surface-subtle);
        }
        .dash-panel-title {
            font-size: 13.5px;
            font-weight: 600;
            color: var(--text-primary);
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        /* Modern Table */
        .dash-table-container {
            width: 100%;
            overflow-x: auto;
        }
        table.dash-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 13px;
            text-align: left;
        }
        table.dash-table th {
            background: var(--bg-surface-subtle);
            color: var(--text-muted);
            font-weight: 600;
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: 0.04em;
            padding: 0.65rem 1rem;
            border-bottom: 1px solid var(--border-subtle);
            white-space: nowrap;
        }
        table.dash-table td {
            padding: 0.75rem 1rem;
            border-bottom: 1px solid var(--border-subtle);
            vertical-align: middle;
            color: var(--text-secondary);
        }
        table.dash-table tbody tr {
            transition: var(--transition);
        }
        table.dash-table tbody tr:hover {
            background: var(--bg-card-hover);
        }
        table.dash-table tr:last-child td {
            border-bottom: none;
        }

        /* Monospace / Code styling */
        code, pre, .font-mono {
            font-family: var(--font-mono);
        }
        code {
            font-size: 12px;
            background: var(--bg-surface);
            padding: 2px 5px;
            border-radius: var(--radius-sm);
            border: 1px solid var(--border-subtle);
            color: var(--text-primary);
        }
        pre {
            background: var(--code-bg);
            border: 1px solid var(--code-border);
            border-radius: var(--radius-md);
            padding: 1rem;
            overflow-x: auto;
            font-size: 12.5px;
            line-height: 1.5;
            color: var(--text-primary);
        }

        /* SQL Syntax Highlighting */
        .sql-kw {
            color: #818cf8;
            font-weight: 700;
        }
        .sql-str {
            color: #34d399;
        }
        .sql-param {
            color: #38bdf8;
            font-weight: 600;
        }
        .sql-num {
            color: #fbbf24;
        }
        .sql-ident {
            color: #e2e8f0;
        }
        [data-theme="light"] .sql-kw { color: #4338ca; }
        [data-theme="light"] .sql-str { color: #059669; }
        [data-theme="light"] .sql-param { color: #0284c7; }
        [data-theme="light"] .sql-num { color: #d97706; }
        [data-theme="light"] .sql-ident { color: #1e293b; }

        /* Toast notifications */
        #dash-toast {
            position: fixed;
            bottom: 24px;
            right: 24px;
            background: #1e293b;
            color: #ffffff;
            border: 1px solid rgba(255, 255, 255, 0.15);
            padding: 0.65rem 1.1rem;
            border-radius: var(--radius-md);
            font-size: 13px;
            font-weight: 500;
            box-shadow: var(--shadow-lg);
            display: flex;
            align-items: center;
            gap: 0.5rem;
            z-index: 1000;
            opacity: 0;
            pointer-events: none;
            transform: translateY(10px);
            transition: opacity 0.2s ease, transform 0.2s ease;
        }
        #dash-toast.show {
            opacity: 1;
            transform: translateY(0);
        }

        /* Helper utilities */
        .text-muted { color: var(--text-muted); }
        .text-primary { color: var(--text-primary); }
        .text-truncate {
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }
        .copy-btn {
            background: transparent;
            border: none;
            color: var(--text-muted);
            cursor: pointer;
            padding: 3px 5px;
            border-radius: 4px;
            display: inline-flex;
            align-items: center;
            transition: var(--transition);
        }
        .copy-btn:hover {
            color: var(--text-primary);
            background: var(--bg-surface);
        }

        /* Responsive */
        @media (max-width: 768px) {
            .dash-header { padding: 0.6rem 1rem; }
            .dash-main { padding: 1rem 0.75rem 2rem; }
            .dash-brand-title span.title-text { display: none; }
        }
    </style>
</head>
<body>
<header class="dash-header">
    <div class="dash-brand">
        <a href="<?= Template::e($indexUrl) ?>" style="display:flex;align-items:center;gap:0.65rem;color:inherit;text-decoration:none;">
            <div class="dash-logo-icon">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="m8 2 1.88 1.88M14.12 3.88 16 2M9 7.13v-1a3.003 3.003 0 1 1 6 0v1"></path>
                    <path d="M12 20c-3.3 0-6-2.7-6-6v-3a4 4 0 0 1 4-4h4a4 4 0 0 1 4 4v3c0 3.3-2.7 6-6 6"></path>
                    <path d="M12 20v-9M6.53 9C4.6 8.8 3 7.1 3 5M6 13H2M3 21c0-2.1 1.7-3.9 3.8-4M20.97 5c0 2.1-1.6 3.8-3.5 4M22 13h-4M17.2 17c2.1.1 3.8 1.9 3.8 4"></path>
                </svg>
            </div>
            <div class="dash-brand-title">
                <span class="title-text">Yii3 Profiler</span>
                <span class="dash-env-pill">DEV</span>
            </div>
        </a>
    </div>

    <div class="dash-nav">
        <?php if ($template === 'view') { ?>
            <a href="<?= Template::e($indexUrl) ?>" class="dash-btn">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="m15 18-6-6 6-6"></path>
                </svg>
                <span>Requests</span>
            </a>
        <?php } ?>

        <button type="button" class="dash-btn dash-btn-icon" id="btn-refresh" title="Reload requests (R)">
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M21 12a9 9 0 0 0-9-9 9.75 9.75 0 0 0-6.74 2.74L3 8"></path>
                <path d="M3 3v5h5"></path>
                <path d="M3 12a9 9 0 0 0 9 9 9.75 9.75 0 0 0 6.74-2.74L21 16"></path>
                <path d="M16 21h5v-5"></path>
            </svg>
        </button>

        <button type="button" class="dash-btn dash-btn-icon" id="btn-theme-toggle" title="Toggle Dark / Light Theme">
            <svg id="icon-moon" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M12 3a6 6 0 0 0 9 9 9 9 0 1 1-9-9Z"></path>
            </svg>
            <svg id="icon-sun" style="display:none;" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                <circle cx="12" cy="12" r="4"></circle>
                <path d="M12 2v2M12 20v2M4.93 4.93l1.41 1.41M17.66 17.66l1.41 1.41M2 12h2M20 12h2M6.34 17.66l-1.41 1.41M19.07 4.93l-1.41 1.41"></path>
            </svg>
        </button>

        <a href="/" class="dash-btn" title="Open Main Application">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"></path>
                <polyline points="15 3 21 3 21 9"></polyline>
                <line x1="10" y1="14" x2="21" y2="3"></line>
            </svg>
            <span>App</span>
        </a>
    </div>
</header>

<main class="dash-main">
    <?= $content ?>
</main>

<div id="dash-toast">
    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#34d399" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
        <path d="M20 6 9 17l-5-5"></path>
    </svg>
    <span id="dash-toast-msg">Copied to clipboard!</span>
</div>

<script>
    // Global copy helper
    window.copyToClipboard = function(text, label) {
        if (!navigator.clipboard) {
            var ta = document.createElement('textarea');
            ta.value = text;
            ta.style.position = 'fixed';
            ta.style.opacity = '0';
            document.body.appendChild(ta);
            ta.select();
            document.execCommand('copy');
            document.body.removeChild(ta);
        } else {
            navigator.clipboard.writeText(text);
        }
        var toast = document.getElementById('dash-toast');
        var msg = document.getElementById('dash-toast-msg');
        if (toast && msg) {
            msg.textContent = (label || 'Copied') + ' copied!';
            toast.classList.add('show');
            setTimeout(function() {
                toast.classList.remove('show');
            }, 2200);
        }
    };

    // Theme Switcher
    (function() {
        var btn = document.getElementById('btn-theme-toggle');
        var iconMoon = document.getElementById('icon-moon');
        var iconSun = document.getElementById('icon-sun');

        function updateIcons(theme) {
            if (theme === 'light') {
                iconMoon.style.display = 'none';
                iconSun.style.display = 'block';
            } else {
                iconMoon.style.display = 'block';
                iconSun.style.display = 'none';
            }
        }

        var current = document.documentElement.getAttribute('data-theme') || 'dark';
        updateIcons(current);

        if (btn) {
            btn.addEventListener('click', function() {
                var now = document.documentElement.getAttribute('data-theme') === 'light' ? 'dark' : 'light';
                document.documentElement.setAttribute('data-theme', now);
                localStorage.setItem('dsh_debug_theme', now);
                updateIcons(now);
            });
        }

        var refreshBtn = document.getElementById('btn-refresh');
        if (refreshBtn) {
            refreshBtn.addEventListener('click', function() {
                window.location.reload();
            });
        }

        // Keyboard shortcuts: 'r' to reload
        window.addEventListener('keydown', function(e) {
            if (e.key === 'r' && !['INPUT', 'TEXTAREA'].includes(document.activeElement.tagName)) {
                e.preventDefault();
                window.location.reload();
            }
        });
    })();
</script>
</body>
</html>
