<?php

declare(strict_types=1);

namespace Dxn\DebugViewer;

use Psr\Http\Message\ResponseInterface;
use Yiisoft\Router\UrlGeneratorInterface;

/**
 * Collected requests, newest first.
 */
final readonly class IndexAction
{
    public function __construct(
        private DumpStorage $storage,
        private Template $template,
        private UrlGeneratorInterface $urlGenerator,
        private bool $enabled = false,
        private int $listLimit = 100,
    ) {}

    public function __invoke(): ResponseInterface
    {
        if (!$this->enabled) {
            return $this->template->html('disabled', ['title' => 'Debug disabled'], 404);
        }

        return $this->template->html('index', [
            'title' => 'Debug requests',
            'indexUrl' => $this->urlGenerator->generate('debug.index'),
            'rows' => $this->storage->list($this->listLimit),
            'limit' => $this->listLimit,
            'viewUrl' => fn(string $id): string => $this->urlGenerator->generate('debug.view', ['id' => $id]),
        ]);
    }
}
