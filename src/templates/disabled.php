<?php

declare(strict_types=1);

use Dxn\DebugViewer\Template;
?>
<div class="dash-panel" style="max-width: 600px; margin: 3rem auto; text-align: center; padding: 3rem 2rem;">
    <div style="width: 56px; height: 56px; border-radius: 50%; background: var(--bg-surface); border: 1px solid var(--border-subtle); display: inline-flex; align-items: center; justify-content: center; margin-bottom: 1.25rem; color: var(--text-muted);">
        <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <rect width="18" height="11" x="3" y="11" rx="2" ry="2"></rect>
            <path d="M7 11V7a5 5 0 0 1 10 0v4"></path>
        </svg>
    </div>
    <h2 style="font-size: 18px; font-weight: 700; color: var(--text-primary); margin-bottom: 0.5rem;">Debug Viewer is Disabled</h2>
    <p style="color: var(--text-secondary); font-size: 13.5px; margin-bottom: 1.5rem; line-height: 1.6;">
        The profiler is restricted in this environment. To enable it, activate <code>dxn/yii-debug-viewer</code> in your application parameters.
    </p>
    <div style="background: var(--bg-surface); padding: 0.75rem 1rem; border-radius: var(--radius-sm); border: 1px solid var(--border-subtle); font-family: var(--font-mono); font-size: 12px; color: var(--text-secondary); text-align: left; display: inline-block;">
        'dxn/yii-debug-viewer' => [<br>
        &nbsp;&nbsp;&nbsp;&nbsp;'enabled' => true,<br>
        ]
    </div>
</div>
