<?php

declare(strict_types=1);

use Dxn\DebugViewer\DumpReader;
use Dxn\DebugViewer\DumpStorage;
use Dxn\DebugViewer\EditorLinker;
use Dxn\DebugViewer\ExportAction;
use Dxn\DebugViewer\IndexAction;
use Dxn\DebugViewer\Redactor;
use Dxn\DebugViewer\Template;
use Dxn\DebugViewer\ViewAction;
use Yiisoft\Aliases\Aliases;
use Yiisoft\Definitions\Reference;

/** @var array $params */

$config = $params['dxn/yii3-debug-viewer'];

return [
    DumpStorage::class => [
        'class' => DumpStorage::class,
        '__construct()' => [
            'aliases' => Reference::to(Aliases::class),
            'path' => $config['dumpPath'],
            'maxDumpBytes' => max(0, (int)($config['maxDumpSize'] ?? 16 * 1024 * 1024)),
        ],
    ],

    Template::class => Template::class,

    DumpReader::class => [
        'class' => DumpReader::class,
        '__construct()' => [
            'redact' => (bool)($config['redact'] ?? true),
        ],
    ],

    Redactor::class => [
        'class' => Redactor::class,
        '__construct()' => [
            'extraWords' => array_values(array_map('strval', (array)($config['redactKeys'] ?? []))),
        ],
    ],

    EditorLinker::class => [
        'class' => EditorLinker::class,
        '__construct()' => [
            'editor' => (string)($config['editor'] ?? ''),
            'pathMap' => (array)($config['pathMap'] ?? []),
        ],
    ],

    IndexAction::class => [
        'class' => IndexAction::class,
        '__construct()' => [
            'enabled' => (bool)$config['enabled'],
            'listLimit' => max(1, (int)($config['listLimit'] ?? 100)),
        ],
    ],

    ViewAction::class => [
        'class' => ViewAction::class,
        '__construct()' => [
            'enabled' => (bool)$config['enabled'],
        ],
    ],

    ExportAction::class => [
        'class' => ExportAction::class,
        '__construct()' => [
            'enabled' => (bool)$config['enabled'],
        ],
    ],
];
