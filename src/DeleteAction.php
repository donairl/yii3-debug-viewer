<?php

declare(strict_types=1);

namespace Dxn\DebugViewer;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Yiisoft\Router\CurrentRoute;
use Yiisoft\Router\UrlGeneratorInterface;

/**
 * Deletes dumps: one (`one()`) or all of them (`all()`).
 *
 * POST only, behind the viewer's own CSRF check, and switchable with
 * `allowDelete`. Both answer with a redirect to the list, which says how many
 * dumps went.
 */
final readonly class DeleteAction
{
    public function __construct(
        private DumpStorage $storage,
        private Template $template,
        private CurrentRoute $currentRoute,
        private UrlGeneratorInterface $urlGenerator,
        private Csrf $csrf,
        private bool $enabled = false,
        private bool $allowDelete = true,
    ) {}

    public function one(ServerRequestInterface $request): ResponseInterface
    {
        return $this->guarded($request, fn(): int => $this->storage->delete((string)$this->currentRoute->getArgument('id')) ? 1 : 0);
    }

    public function all(ServerRequestInterface $request): ResponseInterface
    {
        return $this->guarded($request, fn(): int => $this->storage->clear());
    }

    /**
     * @param callable(): int $delete Does the deleting, returns how many dumps went.
     */
    private function guarded(ServerRequestInterface $request, callable $delete): ResponseInterface
    {
        if (!$this->enabled) {
            return $this->template->html('disabled', ['title' => 'Debug disabled'], 404);
        }
        if (!$this->allowDelete) {
            return $this->template->download("Deleting dumps is switched off (allowDelete).\n", 'text/plain; charset=UTF-8', null, 403);
        }
        if ($request->getMethod() !== 'POST') {
            return $this->template->download("Use POST.\n", 'text/plain; charset=UTF-8', null, 405)->withHeader('Allow', 'POST');
        }
        if (!$this->csrf->isValid($request)) {
            return $this->template->download("Invalid or missing CSRF token. Reload the page and try again.\n", 'text/plain; charset=UTF-8', null, 403);
        }

        return $this->template->redirect($this->urlGenerator->generate('debug.index', [], ['deleted' => (string)$delete()]));
    }
}
