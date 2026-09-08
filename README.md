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

## What it shows

Index: collected requests newest first with method, path, status, SQL count,
log count, error count, duration and peak memory. Reads only `summary.json`,
so it stays fast with many dumps.

Detail: SQL queries with parameters substituted and the calling `file:line`,
log entries by level, exceptions, matched route, request/response, events and
the raw collector summary.

## Install

From a Git remote:

```json
{
    "repositories": [
        { "type": "vcs", "url": "https://gitea.dxn2u.net/donny/yii3-debug-viewer.git" }
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
