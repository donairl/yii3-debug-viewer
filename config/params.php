<?php

declare(strict_types=1);

return [
    'dxn/yii3-debug-viewer' => [
        // Off by default: the viewer exposes request data, so the host
        // application decides when it is available.
        'enabled' => false,
        'dumpPath' => '@runtime/debug',
        'listLimit' => 100,
        // Turn file:line locations into "open in editor" links. One of
        // phpstorm, idea, vscode, cursor, sublime, textmate, or a custom URL
        // containing {file} and {line}. Empty: plain text.
        'editor' => '',
        // Rewrite application paths to paths on the machine running the
        // editor, e.g. ['/app' => '/home/me/project'] for a container.
        'pathMap' => [],
    ],
];
