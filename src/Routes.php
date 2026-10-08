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
            // Optional trailing slash: a webserver or upstream rule can shadow
            // the exact path while letting "<prefix>/" through, and vice versa.
            Route::get($prefix . '[/]')
                ->action(IndexAction::class)
                ->name('debug.index'),
            Route::get($prefix . '/{id:[A-Za-z0-9]+}')
                ->action(ViewAction::class)
                ->name('debug.view'),
            Route::get($prefix . '/{id:[A-Za-z0-9]+}/export')
                ->action(ExportAction::class)
                ->name('debug.export'),
            // "clear" can never be a request id for the GET route above: that one is a different method
            Route::post($prefix . '/clear')
                ->action([DeleteAction::class, 'all'])
                ->name('debug.clear'),
            Route::post($prefix . '/{id:[A-Za-z0-9]+}/delete')
                ->action([DeleteAction::class, 'one'])
                ->name('debug.delete'),
        ];
    }
}
