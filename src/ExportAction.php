<?php

declare(strict_types=1);

namespace Dxn\DebugViewer;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Yiisoft\Router\CurrentRoute;

use function gmdate;
use function in_array;
use function json_encode;
use function strtolower;

use const JSON_INVALID_UTF8_SUBSTITUTE;
use const JSON_PARTIAL_OUTPUT_ON_ERROR;
use const JSON_PRETTY_PRINT;
use const JSON_UNESCAPED_SLASHES;
use const JSON_UNESCAPED_UNICODE;

/**
 * One request as a file to attach to an issue: `?format=json` (the data) or
 * `?format=html` (the request page as one self-contained file).
 *
 * It goes through the same DumpReader as the page, so values are masked unless
 * `?reveal=1` is given.
 */
final readonly class ExportAction
{
    public function __construct(
        private DumpReader $reader,
        private Template $template,
        private CurrentRoute $currentRoute,
        private EditorLinker $editor,
        private bool $enabled = false,
    ) {}

    public function __invoke(ServerRequestInterface $request): ResponseInterface
    {
        if (!$this->enabled) {
            return $this->template->html('disabled', ['title' => 'Debug disabled'], 404);
        }

        $query = $request->getQueryParams();
        $format = strtolower((string)($query['format'] ?? 'json'));
        if (!in_array($format, ['json', 'html'], true)) {
            return $this->template->download("Unknown export format. Use format=json or format=html.\n", 'text/plain; charset=UTF-8', null, 400);
        }

        $id = (string)$this->currentRoute->getArgument('id');
        $dump = $this->reader->get($id, ($query['reveal'] ?? '') === '1');
        if ($dump === null) {
            return $this->template->html('missing', ['title' => 'Dump not found', 'indexUrl' => '', 'id' => $id], 404);
        }

        $at = gmdate('Y-m-d H:i:s') . ' UTC';

        if ($format === 'html') {
            $response = $this->template->html('view', DumpPage::params($dump, $this->editor) + [
                'indexUrl' => '',
                'redaction' => $dump['redaction'] + ['toggleUrl' => ''],
                'export' => ['at' => $at],
            ]);

            return $response
                ->withHeader('Content-Disposition', 'attachment; filename="debug-' . $id . '.html"')
                ->withHeader('X-Content-Type-Options', 'nosniff');
        }

        $json = json_encode([
            'generator' => 'dxn/yii3-debug-viewer',
            'exportedAt' => $at,
            'id' => $id,
            'redaction' => $dump['redaction'],
            'warning' => $dump['warning'],
            'meta' => $dump['meta'],
            'summary' => $dump['summary'],
            'data' => $dump['data'],
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE | JSON_PARTIAL_OUTPUT_ON_ERROR);

        return $this->template->download((string)$json . "\n", 'application/json; charset=UTF-8', 'debug-' . $id . '.json');
    }
}
