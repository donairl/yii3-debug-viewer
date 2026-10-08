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
        private DumpReader $reader,
        private Template $template,
        private CurrentRoute $currentRoute,
        private EditorLinker $editor,
        private UrlGeneratorInterface $urlGenerator,
        private bool $enabled = false,
    ) {}

    public function __invoke(ServerRequestInterface $request): ResponseInterface
    {
        if (!$this->enabled) {
            return $this->template->html('disabled', ['title' => 'Debug disabled'], 404);
        }

        $indexUrl = $this->urlGenerator->generate('debug.index');
        $id = (string)$this->currentRoute->getArgument('id');

        // `?reveal=1` shows the real values of this view (see DumpReader)
        $reveal = ($request->getQueryParams()['reveal'] ?? '') === '1';
        $dump = $this->reader->get($id, $reveal);

        if ($dump === null) {
            return $this->template->html('missing', [
                'title' => 'Dump not found',
                'indexUrl' => $indexUrl,
                'id' => $id,
            ], 404);
        }

        $redaction = $dump['redaction'];
        // An export carries over what this page shows: masked, or revealed
        $exportQuery = $redaction['revealed'] ? ['reveal' => '1'] : [];

        return $this->template->html('view', DumpPage::params($dump, $this->editor) + [
            'indexUrl' => $indexUrl,
            'redaction' => $redaction + [
                'toggleUrl' => $redaction['enabled']
                    ? $this->urlGenerator->generate('debug.view', ['id' => $id], $redaction['revealed'] ? [] : ['reveal' => '1'])
                    : '',
            ],
            'exportUrls' => [
                'json' => $this->urlGenerator->generate('debug.export', ['id' => $id], ['format' => 'json'] + $exportQuery),
                'html' => $this->urlGenerator->generate('debug.export', ['id' => $id], ['format' => 'html'] + $exportQuery),
            ],
        ]);
    }
}
