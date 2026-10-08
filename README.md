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

The list filters in the browser: search over path, route name, action and
status (several words must all match), method, status class, errors only,
slow (>500 ms), a minimum query count and a time range. Click a column
header to sort by it (click again to reverse, a third time to reset). The
filter and sort state is kept in the URL, for example
`/debug?err=1&minq=20&sort=duration&dir=desc`, so a filtered view can be
bookmarked or shared. Filters apply to the newest `listLimit` dumps (100 by
default), and the page says so when the limit is reached.

Detail: SQL queries with parameters substituted and the calling `file:line`,
log entries by level, exceptions, matched route, request/response, events and
the raw collector summary. On top of that:

- **What went wrong.** The top of the Overview lists everything worth a
  look, errors first, each with a button that opens the right tab and
  highlights the entry: exceptions, failed queries, error-level logs, 5xx
  responses, requests slower than 500 ms (warning above 200 ms), warning
  logs, 4xx responses, N+1 and duplicate queries, queries slower than
  100 ms, failed container services and peak memory of 64 MB or more. When
  there is nothing, it says so. The container probing for services it then
  autowires (`NotFoundException`) is ignored, since it happens on every
  request.
- **Readable bodies.** Request and response bodies are shown by their
  `Content-Type`: JSON as a collapsible tree (with Expand/Collapse all, plus
  Pretty and Raw views), a form as a name/value table, anything else as text,
  and binary as a one-line note. The tree is built on the server from native
  `<details>`, so it works without JavaScript and in an exported snapshot.
  Large bodies are cut (200 KB shown, JSON over 1 MB or 5,000 nodes stays text)
  and a cut body offers no Copy. HTML is shown as source, never rendered.
- **Replay as cURL.** The Request tab rebuilds the recorded request as a
  `curl` command you can paste into a shell: method, URL, headers and body,
  quoted for POSIX shells. Credentials are masked by default (see
  *Sensitive values*) and a checkbox includes the real values once you have
  revealed them. Headers curl sets itself (`Host`, `Content-Length`,
  `Accept-Encoding`) are dropped. Binary bodies are left out, and multipart
  bodies are not masked, which the panel says.
- **Stack traces.** Each exception (and its `previous` chain) is listed as
  frames with `file:line`. The throw site and your own code stay visible;
  runs of vendor frames fold away. "Copy trace" copies message and frames.
- **Query insights.** N+1 (the same statement shape run 3+ times with
  different values) and duplicate queries (identical statement and values
  run again) are flagged on the Overview and Database tabs, with the time
  that could be saved and the calling line.
- **Filters.** The Database, Logs and Events tabs each have a filter bar.
  Search takes several words (all must match) and press `/` to focus it,
  `Esc` to clear. Database: status, slow queries (>20 ms), N+1/duplicate
  only. Logs: toggle levels on and off, entries with context only. Events:
  hide events declared in `vendor/`. Filtering is in the browser, so it
  needs no extra request.
- **Timeline.** SQL, container services, events, log entries and exceptions
  on one time axis. Filter by type; click a query to jump to it. Services
  faster than 0.5 ms are counted, not listed.

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
    'maxDumpSize' => 16 * 1024 * 1024,   // optional, bytes; 0 = no limit
    'allowDelete' => true,           // optional, see below
    'redact' => true,                // optional, see below
    'redactKeys' => ['ssn'],         // optional, extra sensitive names
    'editor' => 'phpstorm',          // optional, see below
    'pathMap' => ['/app' => '/home/me/project'],   // optional
],
```

`maxDumpSize` guards memory: decoding a `data.json` takes several times its
size in RAM, so a larger file is not loaded. The request page still opens
from its summary and says why the rest is missing. The same notice appears
when `data.json` is missing or cannot be decoded, rather than showing empty
tabs with no explanation.

Dumps are listed newest first by request id (yii-debug ids start with the
time), so listing and opening a dump do not scan the whole dump directory.

### Sensitive values

The request view masks credentials before it renders anything, so the real
values never reach the page (and so not a screenshot, a screen share or a
copy-paste). A banner says how many were masked and links to `?reveal=1` on
the same URL, which shows the real values for that view and offers to mask
them again.

What is masked, by name: values of keys, headers, query parameters and
form/JSON fields whose name contains `password`, `token`, `secret`,
`api_key`, `cookie`, `authorization`, `csrf` and similar words (camelCase and
punctuation are split, so `apiToken` and `X-CSRF-Token` match while
`compass` and `bypass` do not). It covers the raw request and response
(headers, cookies, URL query, form and JSON bodies), log context, bound SQL
parameters and the literals compared to a sensitive column
(`password = 'x'`), exception messages, bearer tokens in any text, and the
arguments of stack frames. Names stay, values become `[REDACTED]`.

This is best effort: a secret under an innocuous name, inside free text, or in
a multipart upload stays visible. Add names with `redactKeys`, and turn the
whole thing off with `'redact' => false`. The request list shows no request
data, so it is unaffected.

### Deleting dumps

The request page has a **Delete** button and the list has a trash button per
row and **Delete all**, each behind a confirmation. yii-debug already prunes
its own history (`historySize`, 50 by default), so this is for clearing by
hand. Only complete dumps are removed: other files in the dump directory, a
dump still being written, and anything reached through a symlink are left
alone. `'allowDelete' => false` removes the buttons and refuses the requests.

Deleting is `POST` only (`<prefix>/<id>/delete`, `<prefix>/clear`) and has its
own CSRF protection, with nothing needed from your application: a random token
in a `HttpOnly; SameSite=Strict` cookie scoped to the viewer's path has to come
back in the form, and a browser that reports the request as cross-site is
refused.

If your application already applies a CSRF middleware (such as
`yiisoft/csrf`) to every POST, it will reject these forms, since they carry
the viewer's token and not yours. Leave the viewer routes out of that
middleware, or set `allowDelete` to `false`.

### Export

The request page has **Export JSON** and **Export HTML** links, for attaching
a request to an issue. They go through the same masking as the page, so
credentials are masked unless you exported from a revealed view (`?reveal=1`),
in which case the file says it carries real values.

- JSON: `{generator, exportedAt, id, redaction, warning, meta, summary, data}`,
  where `data` is the collector data as the viewer reads it.
- HTML: the request page as one self-contained file (inline CSS and script, no
  external requests), with a banner stating when it was exported. All tabs
  work offline; links back to the live viewer are left out.

The URLs are `<prefix>/<id>/export?format=json|html[&reveal=1]`. They come
with `Routes::create()`, so an application that already spreads those routes
gets the new one on update.

### Open in editor

With `editor` set, every `file:line` the viewer shows (query callers, log
lines, exception frames, events) becomes a link that opens the file at that
line. `editor` is one of `phpstorm`, `idea`, `vscode`, `cursor`, `sublime`,
`textmate`, or a custom URL with `{file}` and `{line}` placeholders, for
example `'myeditor://open?path={file}&line={line}'`. Left empty, locations
stay plain text.

The links use your editor's URL handler, so the editor must be installed on
the machine whose browser you view the page in (for PhpStorm, JetBrains
Toolbox registers `phpstorm://`).

Dumps record paths as the application saw them. When the app runs in a
container or VM, map those prefixes to paths on the machine running the
editor with `pathMap`: `['/app' => '/home/me/project']`. The longest matching
prefix wins and only whole directories match (`/app` does not touch
`/application`). Paths that are not absolute, such as `[internal function]`,
are never linked.

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

Tests:

```bash
composer test
```

Static analysis (Psalm, level 4, clean):

```bash
composer psalm
```

`psalm.xml` silences two things on purpose: constructors and action methods
that only the host's container and router call, and the `Routes` class the
host spreads into its own route list.
