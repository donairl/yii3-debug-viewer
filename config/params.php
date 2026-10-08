<?php

declare(strict_types=1);

return [
    'dxn/yii3-debug-viewer' => [
        // Off by default: the viewer exposes request data, so the host
        // application decides when it is available.
        'enabled' => false,
        'dumpPath' => '@runtime/debug',
        'listLimit' => 100,
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
