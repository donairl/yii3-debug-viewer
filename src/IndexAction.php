<?php

declare(strict_types=1);

namespace Dxn\DebugViewer;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Yiisoft\Router\UrlGeneratorInterface;

use function ctype_digit;
use function is_string;
use function parse_url;
use function rtrim;
use function strlen;

use const PHP_URL_PATH;

/**
 * Collected requests, newest first.
 */
final readonly class IndexAction
{
    public function __construct(
        private DumpStorage $storage,
        private Template $template,
        private UrlGeneratorInterface $urlGenerator,
        private Csrf $csrf,
        private bool $enabled = false,
        private int $listLimit = 100,
        private bool $allowDelete = true,
    ) {}

    public function __invoke(ServerRequestInterface $request): ResponseInterface
    {
        if (!$this->enabled) {
            return $this->template->html('disabled', ['title' => 'Debug disabled'], 404);
        }

        $indexUrl = $this->urlGenerator->generate('debug.index');
        ['token' => $token, 'isNew' => $isNew] = $this->csrf->token($request);

        // how many dumps the last delete removed (shown once, see the template)
        $deleted = $request->getQueryParams()['deleted'] ?? null;

        $response = $this->template->html('index', [
            'title' => 'Debug requests',
            'indexUrl' => $indexUrl,
            'rows' => $this->storage->list($this->listLimit),
            'limit' => $this->listLimit,
            'viewUrl' => fn(string $id): string => $this->urlGenerator->generate('debug.view', ['id' => $id]),
            'canDelete' => $this->allowDelete,
            'csrf' => $token,
            'deleteUrl' => fn(string $id): string => $this->urlGenerator->generate('debug.delete', ['id' => $id]),
            'clearUrl' => $this->urlGenerator->generate('debug.clear'),
            'deleted' => is_string($deleted) && ctype_digit($deleted) && strlen($deleted) <= 9 ? (int)$deleted : null,
        ]);

        return $isNew && $this->allowDelete
            ? $response->withAddedHeader('Set-Cookie', $this->csrf->cookie($token, rtrim((string)parse_url($indexUrl, PHP_URL_PATH), '/'), $request->getUri()->getScheme() === 'https'))
            : $response;
    }
}
