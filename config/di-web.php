<?php

declare(strict_types=1);

use Dxn\DebugViewer\DumpStorage;
use Dxn\DebugViewer\EditorLinker;
use Dxn\DebugViewer\IndexAction;
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
        ],
    ],

    Template::class => Template::class,

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
];
