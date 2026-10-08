<?php

declare(strict_types=1);

namespace Dxn\DebugViewer;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Yiisoft\Router\CurrentRoute;
use Yiisoft\Router\UrlGeneratorInterface;

/**
 * One collected request: SQL, logs, exceptions, route, events.
 */
final readonly class ViewAction
{
    public function __construct(
        private DumpStorage $storage,
        private Template $template,
        private CurrentRoute $currentRoute,
        private EditorLinker $editor,
        private Redactor $redactor,
        private UrlGeneratorInterface $urlGenerator,
        private bool $enabled = false,
        private bool $redact = true,
    ) {}

    public function __invoke(ServerRequestInterface $request): ResponseInterface
    {
        if (!$this->enabled) {
            return $this->template->html('disabled', ['title' => 'Debug disabled'], 404);
        }

        $indexUrl = $this->urlGenerator->generate('debug.index');
        $id = (string)$this->currentRoute->getArgument('id');
        $dump = $this->storage->get($id);

        if ($dump === null) {
            return $this->template->html('missing', [
                'title' => 'Dump not found',
                'indexUrl' => $indexUrl,
                'id' => $id,
            ], 404);
        }

        $data = $dump['data'];
        $summary = $dump['summary'];
        $meta = $dump['meta'];

        // Values are masked here, before anything is rendered, so they never reach the page.
        // `?reveal=1` turns that off for this view.
        $reveal = $this->redact && ($request->getQueryParams()['reveal'] ?? '') === '1';
        $masked = 0;
        if ($this->redact && !$reveal) {
            [$data, $maskedData] = $this->redactor->dump($data);
            [$summary, $maskedSummary] = $this->redactor->dump($summary);
            $meta['url'] = $this->redactor->url((string)$meta['url']);
            $masked = $maskedData + $maskedSummary;
        }

        return $this->template->html('view', [
            'title' => $meta['method'] . ' ' . ($meta['path'] ?: '/'),
            'indexUrl' => $indexUrl,
            'meta' => $meta,
            'summary' => $summary,
            'view' => new DumpView($data),
            'editor' => $this->editor,
            'warning' => $dump['warning'],
            'redaction' => [
                'enabled' => $this->redact,
                'revealed' => $reveal,
                'masked' => $masked,
                'toggleUrl' => $this->redact
                    ? $this->urlGenerator->generate('debug.view', ['id' => $id], $reveal ? [] : ['reveal' => '1'])
                    : '',
            ],
        ]);
    }
}
