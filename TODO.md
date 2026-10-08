# TODO

State when this was written: `main` at `d8da29e`, pushed to `origin` and `gitea`,
255 tests, `composer psalm` clean at level 4.

## Next up

### #11 Live tail on the index

Design is settled; two choices are still open (see the end of this section).

- A **Live** toggle in the filter bar, kept in the URL (`?live=1`) like the other filters.
- New requests appear at the top and flash. Active filters and sort still apply.
  Sorting by something other than time, or being scrolled down, shows a
  "3 new" button instead of moving rows under the reader.
- The page never holds more than `listLimit` rows: the oldest are dropped as new ones arrive.
- New read-only route `GET <prefix>/poll?after=<newest id on the page>`, added to
  `Routes::create()`. Ids start with the time, so "newer than X" is one
  directory listing that stops at X (same trick as `DumpStorage::list()`).
- It returns the new rows **rendered by the server** plus the latest id. For that,
  move the row markup of `index.php` into a partial shared by the page and the
  poll; the JS only inserts. That move is the one step that touches working
  code, so verify it with the computed-style comparison used for the CSS
  refactor (dump `getComputedStyle` of every element before and after, with all
  tabs forced visible; first prove the comparison fails when a rule is removed).
- Poll while the tab is visible (Page Visibility API), pause when hidden, back off after errors.
- Same `enabled` gate as the other pages. List data only, no request bodies.
  Delete buttons in polled rows use the CSRF cookie token.
- KPI cards: recompute in the browser from the rows on the page, no server aggregates.
- Not handled on purpose: dumps deleted elsewhere stay on an open page until reload;
  a storage that writes non-time-ordered ids breaks "newer than X" (the list
  ordering already assumes time-ordered ids).
- Tests: poll action (order, `after`, nothing newer, malformed `after`, viewer
  off), the row partial, and insert/filter/sort/"N new"/trimming in headless Chromium.
- Estimate: about $3-5 of session cost.

Open choices before starting:
1. Live **off** by default (suggested) or on?
2. Poll every **2 s** or **5 s**?

### #7 Compare two requests

Pick two dumps in the index, then diff: status, duration, memory, query count,
SQL that only one of them ran, route, headers, log levels, exceptions.
Likely needs a selection UI on the index and a `compare?a=&b=` page.
Both sides must go through `DumpReader`, so masking and `?reveal=1` apply to both.
Query diff can reuse `QueryAnalyzer::fingerprint()` to match statements by shape.

### #8 EXPLAIN a query

Opt-in, off by default, SELECT only, through the host's own DB connection.
Highest security risk of the open items (runs SQL from a dump), so it needs its
own design pass first: allow-list of statement shapes, no multi-statement,
read-only transaction, timeout, and never against a statement that was masked.
Fallback with no risk: a "Copy as EXPLAIN" button.

## Small follow-ups

- Source-code snippet around the line in the stack trace tab (local files only,
  through `pathMap`), and masked frame arguments.
- Detail-tab filter state in the URL, like the index has.
- Search across dumps beyond `listLimit` (needs server-side search).
- "Clear" on the index should also reset the sort (today it only clears filters).
- Make the problem-panel thresholds configurable (100/200/500 ms, 64 MB are
  constants in `ProblemFinder`).
- Psalm level 3 reports 9 findings, nearly all guard clauses Psalm cannot follow
  (`min()` after an emptiness check, `$segments[$last]` after `$last >= 0`).
  Worth doing only if someone wants level 3.
- A message that holds a bare secret in free text, or a multipart upload, stays
  visible to `Redactor`; consider an upload-aware rule if that matters.
- `EditorLinker::enabled()` and `Redactor::sql()` are public but only used by tests.

## Left out of the body view (#13, slim version)

- HTML response preview in a sandboxed iframe (`sandbox=""`, CSP, loaded on click).
  The design was worked out: CSP meta inserted after the doctype so quirks mode
  is not triggered, `<base>` for the app origin, a stricter CSP inside exported
  snapshots, preview capped at about 256 KB.
- XML pretty-printing.
- Charset conversion: a non-UTF-8 body is shown as binary.

## Never verified in a real Yii host

Everything is tested with mocks, the real FastRoute router and headless
Chromium, but not inside an actual application. Check these once:

- [ ] The router accepts the array action `[DeleteAction::class, 'one']` / `'all'`.
- [ ] `ServerRequestInterface` is injected into `__invoke` of `IndexAction`,
      `ViewAction`, `ExportAction`.
- [ ] A host CSRF middleware (e.g. `yiisoft/csrf`) on all POSTs rejects the
      viewer's delete forms: exclude the viewer routes or set `allowDelete` to false.
- [ ] The export links really download (`download` attribute plus
      `Content-Disposition`), and the `confirm()` dialogs work.
- [ ] The CSRF cookie is set with the right `Path` when the host serves the
      viewer under a prefix, and with `Secure` over HTTPS.
- [ ] "App events only" toggle: the sample dumps had no non-vendor events, so it
      was never seen working.
- [ ] A multi-gigabyte `runtime/debug` and a dump near `maxDumpSize`.

## Decisions already made (do not re-litigate without a reason)

- **Masking is on by default** (`redact => true`). Real values never reach the
  page; `?reveal=1` shows them for that view. Exports follow the page they were
  started from.
- **Delete CSRF** is a double-submit cookie (`HttpOnly; SameSite=Strict`, scoped
  to the viewer path) plus a `Sec-Fetch-Site` check, so nothing is needed from
  the host. No automatic retention: yii-debug already prunes with `historySize`.
- **Newest-first order** comes from the dump id (`uniqid`, time first), not
  `filemtime`. It is also correct inside one second, which mtime was not.
- **Container `NotFoundException`** is routine (the container probes with
  `get()` before autowiring) and is not reported as a failed service.
- **Psalm** runs at level 4; constructors and actions that only the host's
  container and router call are suppressed in `psalm.xml` on purpose.
- **Inline styles:** repeated or long ones became classes; the roughly 110
  one-off ones are left inline deliberately.

## Done

Stack traces, N+1 / duplicate query detection, timeline, filters on the detail
tabs, open-in-editor links, filters / sorting / deep links on the index,
`DumpStorage` speed-up and `maxDumpSize`, "what went wrong" panel, cURL replay,
credential masking, JSON / HTML export, deleting dumps, JSON tree and form table
for bodies, `highlightSql` tokenizer, Psalm.
