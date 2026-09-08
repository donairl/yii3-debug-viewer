<?php

declare(strict_types=1);

namespace Dxn\DebugViewer;

use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;

use function array_merge;
use function htmlspecialchars;
use function is_scalar;
use function json_encode;
use function number_format;
use function ob_get_clean;
use function ob_start;
use function time;

/**
 * Minimal, zero-dependency PHP templating for the debug viewer.
 *
 * Deliberately not Inertia/Vue: the viewer works with no JS bundle built,
 * giving engineers a rock-solid, production-grade diagnostic dashboard.
 */
final readonly class Template
{
    public function __construct(
        private ResponseFactoryInterface $responseFactory,
    ) {}

    public function html(string $template, array $params = [], int $status = 200): ResponseInterface
    {
        $body = self::render(__DIR__ . '/templates/' . $template . '.php', $params);
        $layoutParams = array_merge($params, [
            'template' => $template,
            'title' => (string)($params['title'] ?? 'Debug'),
            'indexUrl' => (string)($params['indexUrl'] ?? ''),
            'content' => $body,
        ]);
        $html = self::render(__DIR__ . '/templates/layout.php', $layoutParams);

        $response = $this->responseFactory->createResponse($status);
        $response->getBody()->write($html);

        return $response->withHeader('Content-Type', 'text/html; charset=UTF-8');
    }

    private static function render(string $file, array $params): string
    {
        extract($params, EXTR_SKIP);
        ob_start();
        require $file;

        return (string)ob_get_clean();
    }

    public static function e(mixed $value): string
    {
        return htmlspecialchars(is_scalar($value) || $value === null ? (string)$value : (string)json_encode($value), ENT_QUOTES, 'UTF-8');
    }

    public static function timeAgo(float|int $timestamp): string
    {
        $diff = time() - (int)$timestamp;
        if ($diff < 5) {
            return 'just now';
        }
        if ($diff < 60) {
            return $diff . 's ago';
        }
        if ($diff < 3600) {
            return ((int)($diff / 60)) . 'm ago';
        }
        if ($diff < 86400) {
            return ((int)($diff / 3600)) . 'h ago';
        }
        return ((int)($diff / 86400)) . 'd ago';
    }

    public static function methodClass(string $method): string
    {
        return match (strtoupper($method)) {
            'GET' => 'method-get',
            'POST' => 'method-post',
            'PUT', 'PATCH' => 'method-put',
            'DELETE' => 'method-delete',
            'OPTIONS', 'HEAD' => 'method-options',
            default => 'method-default',
        };
    }

    public static function statusClass(int $status): string
    {
        if ($status >= 500) {
            return 'status-5xx';
        }
        if ($status >= 400) {
            return 'status-4xx';
        }
        if ($status >= 300) {
            return 'status-3xx';
        }
        if ($status >= 200) {
            return 'status-2xx';
        }
        return 'status-default';
    }

    public static function statusText(int $status): string
    {
        return DumpView::statusText($status);
    }

    public static function formatMs(float|int $ms): string
    {
        if ($ms < 1) {
            return number_format((float)$ms, 2) . ' ms';
        }
        if ($ms < 1000) {
            return number_format((float)$ms, 1) . ' ms';
        }
        return number_format((float)$ms / 1000, 2) . ' s';
    }
}
