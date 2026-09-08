<?php

declare(strict_types=1);

use Dxn\DebugViewer\Template;

/**
 * @var string $id
 * @var string $indexUrl
 */
?>
<div class="dash-panel" style="max-width: 600px; margin: 3rem auto; text-align: center; padding: 3rem 2rem;">
    <div style="width: 56px; height: 56px; border-radius: 50%; background: var(--c-warn-bg); border: 1px solid var(--c-warn-border); display: inline-flex; align-items: center; justify-content: center; margin-bottom: 1.25rem; color: var(--c-warn);">
        <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <circle cx="12" cy="12" r="10"></circle>
            <line x1="12" y1="8" x2="12" y2="12"></line>
            <line x1="12" y1="16" x2="12.01" y2="16"></line>
        </svg>
    </div>
    <h2 style="font-size: 18px; font-weight: 700; color: var(--text-primary); margin-bottom: 0.5rem;">Dump Not Found</h2>
    <p style="color: var(--text-secondary); font-size: 13.5px; margin-bottom: 1.5rem; line-height: 1.6;">
        No debug profile exists with identifier <code style="font-family: var(--font-mono); font-size: 13px;"><?= Template::e($id) ?></code>.<br>
        It may have been cleared or removed.
    </p>
    <a href="<?= Template::e($indexUrl) ?>" class="dash-btn dash-btn-primary" style="padding: 0.5rem 1.25rem; font-size: 13px;">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
            <line x1="19" y1="12" x2="5" y2="12"></line>
            <polyline points="12 19 5 12 12 5"></polyline>
        </svg>
        <span>Back to Request Feed</span>
    </a>
</div>
