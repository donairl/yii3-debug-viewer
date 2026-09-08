<?php

declare(strict_types=1);

namespace Dxn\DebugViewer;

use Psr\Http\Message\ResponseInterface;
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
        private UrlGeneratorInterface $urlGenerator,
        private bool $enabled = false,
    ) {}

    public function __invoke(): ResponseInterface
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

        return $this->template->html('view', [
            'title' => $dump['meta']['method'] . ' ' . ($dump['meta']['path'] ?: '/'),
            'indexUrl' => $indexUrl,
            'meta' => $dump['meta'],
            'summary' => $dump['summary'],
            'view' => new DumpView($dump['data']),
        ]);
    }
}
