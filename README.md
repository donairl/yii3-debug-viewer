# dxn/yii3-debug-viewer

Server-rendered viewer for the request dumps written by
[`yiisoft/yii-debug`](https://github.com/yiisoft/yii-debug), for Yii 3
applications on `yiisoft/router` 4. Built because
`yiisoft/yii-debug-viewer` requires `yiisoft/router ^3` and
`yiisoft/yii-debug-api` writes to `Application::$dispatcher`, which is
`readonly` since `yii-http 1.1.0`.

Plain PHP templates, no JS bundle, no database: it reads
`runtime/debug/<date>/<request-id>/*.json`. That matters when the thing you
are debugging is the asset build.

Source: <https://github.com/donairl/yii3-debug-viewer>

## What it shows

Index: collected requests newest first with method, path, status, SQL count,
log count, error count, duration and peak memory. Reads only `summary.json`,
so it stays fast with many dumps.

Detail: SQL queries with parameters substituted and the calling `file:line`,
log entries by level, exceptions, matched route, request/response, events and
the raw collector summary.

## Install

The package is not on Packagist, so declare the GitHub repository first, then
require it:

```bash
composer config repositories.yii3-debug-viewer vcs https://github.com/donairl/yii3-debug-viewer.git
composer require --dev dxn/yii3-debug-viewer:dev-main
```

The equivalent `composer.json` edit, if you prefer editing by hand — run
`composer update dxn/yii3-debug-viewer` afterwards:

```json
{
    "repositories": [
        { "type": "vcs", "url": "https://github.com/donairl/yii3-debug-viewer.git" }
    ],
    "require-dev": { "dxn/yii3-debug-viewer": "dev-main" }
}
```

Or from a checkout next to the project, for local development:

```json
{
    "repositories": [
        { "type": "path", "url": "../yii3-debug-viewer", "options": { "symlink": true } }
    ],
    "require-dev": { "dxn/yii3-debug-viewer": "@dev" }
}
```

A path repository is resolved on the machine that runs Composer, so a
deployment that does not have the sibling checkout needs the VCS form.

Enable it in the host's web params — off by default, since it exposes request
data:

```php
'dxn/yii3-debug-viewer' => [
    'enabled' => Environment::isDev(),
    'dumpPath' => '@runtime/debug',  // optional
    'listLimit' => 100,              // optional
],
```

Register the routes. They are not shipped as a config-plugin `routes` file on
purpose: an application served from a subpath registers its routes inside a
prefixed `Group`, and merged top-level routes would bypass that prefix.

```php
use Dxn\DebugViewer\Routes as DebugViewerRoutes;

if (Environment::isDev() && class_exists(DebugViewerRoutes::class)) {
    array_push($routes, ...DebugViewerRoutes::create());   // or create('/_debug')
}
```

Keep the viewer's own requests out of the dumps:

```php
'yiisoft/yii-debug' => [
    'ignoredRequests' => ['/debug', '/debug/*'],
],
```

## Requirements

A Yii 3 application with `yiisoft/config`, `yiisoft/router` 4,
`yiisoft/aliases`, a PSR-17 response factory, and `yiisoft/yii-debug`
collecting to `@runtime/debug`.

## Working on the package itself

Clone and install the dev dependencies:

```bash
git clone https://github.com/donairl/yii3-debug-viewer.git
cd yii3-debug-viewer
composer install
```

`composer install` reads `composer.lock` when one is present and installs the
exact pinned versions; without a lock file it resolves `composer.json` and
writes one. Use it after cloning and after every `git pull`. Useful flags:

```bash
composer install --no-dev                  # runtime deps only, for deployment
composer install --no-interaction --prefer-dist   # CI
composer update                            # re-resolve and rewrite composer.lock
composer dump-autoload                      # regenerate the autoloader only
```

Static analysis:

```bash
composer install
vendor/bin/psalm
```
