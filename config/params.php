<?php

declare(strict_types=1);

return [
    'dxn/yii3-debug-viewer' => [
        // Off by default: the viewer exposes request data, so the host
        // application decides when it is available.
        'enabled' => false,
        'dumpPath' => '@runtime/debug',
        'listLimit' => 100,
    ],
];
