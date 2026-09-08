<?php

declare(strict_types=1);

namespace Dxn\DebugViewer;

use Yiisoft\Router\Route;

/**
 * Route definitions for the viewer.
 *
 * Deliberately not shipped as a config-plugin "routes" file: an application
 * served from a subpath registers its routes inside a prefixed Group, and
 * merged top-level routes would bypass that prefix. The host spreads these
 * into whichever group it uses:
 *
 *     $routes = [...];
 *     if (Environment::isDev()) {
 *         array_push($routes, ...Routes::create());
 *     }
 */
final class Routes
{
    /**
     * @param string $prefix Path the viewer is mounted on, within the host's group.
     *
     * @return list<Route>
     */
    public static function create(string $prefix = '/debug'): array
    {
        $prefix = '/' . trim($prefix, '/');

        return [
            Route::get($prefix)
                ->action(IndexAction::class)
                ->name('debug.index'),
            Route::get($prefix . '/{id:[A-Za-z0-9]+}')
                ->action(ViewAction::class)
                ->name('debug.view'),
        ];
    }
}
