<?php

declare(strict_types=1);

return [
    'dxn/yii3-debug-viewer' => [
        // Off by default: the viewer exposes request data, so the host
        // application decides when it is available.
        'enabled' => false,
        'dumpPath' => '@runtime/debug',
        'listLimit' => 100,
        // Show "delete" buttons and accept the POST requests behind them. yii-debug
        // already prunes its own history (historySize), so this is for clearing by hand.
        'allowDelete' => true,
        // Mask passwords, tokens, cookies and similar in what the request view
        // shows. `?reveal=1` on the view URL shows the real values.
        'redact' => true,
        // More words that make a name sensitive, e.g. ['ssn', 'card_number'].
        'redactKeys' => [],
        // data.json larger than this many bytes is not decoded (decoding needs
        // several times the file size in memory). 0 removes the limit.
        'maxDumpSize' => 16 * 1024 * 1024,
        // Turn file:line locations into "open in editor" links. One of
        // phpstorm, idea, vscode, cursor, sublime, textmate, or a custom URL
        // containing {file} and {line}. Empty: plain text.
        'editor' => '',
        // Rewrite application paths to paths on the machine running the
        // editor, e.g. ['/app' => '/home/me/project'] for a container.
        'pathMap' => [],
    ],
];
