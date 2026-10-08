<?php

declare(strict_types=1);

namespace Dxn\DebugViewer;

use Psr\Http\Message\ServerRequestInterface;

use function bin2hex;
use function hash_equals;
use function in_array;
use function is_array;
use function is_string;
use function parse_str;
use function preg_match;
use function random_bytes;
use function sprintf;
use function str_contains;
use function strtolower;

/**
 * CSRF protection for the viewer's state-changing requests, with nothing from
 * the host application: no session, no secret, no middleware.
 *
 * Double-submit cookie: a random token lives in a `HttpOnly; SameSite=Strict`
 * cookie, and the same token has to come back in the form. Another site's
 * page can make the browser send the cookie but cannot read it, so it cannot
 * fill in the field. A browser that says the request is not same-origin
 * (`Sec-Fetch-Site`) is refused outright.
 */
final class Csrf
{
    public const COOKIE = 'dxn_debug_csrf';
    public const FIELD = '_csrf';

    /**
     * The token for this visitor: the one already in their cookie, or a new
     * one that the response must set (see `cookie()`).
     *
     * @return array{token: string, isNew: bool}
     */
    public function token(ServerRequestInterface $request): array
    {
        $cookie = $request->getCookieParams()[self::COOKIE] ?? null;
        if (is_string($cookie) && self::isWellFormed($cookie)) {
            return ['token' => $cookie, 'isNew' => false];
        }

        return ['token' => bin2hex(random_bytes(16)), 'isNew' => true];
    }

    /**
     * A Set-Cookie value, scoped to the viewer's own path so the rest of the
     * application never receives it.
     */
    public function cookie(string $token, string $path, bool $secure): string
    {
        return sprintf('%s=%s; Path=%s; HttpOnly; SameSite=Strict%s', self::COOKIE, $token, $path === '' ? '/' : $path, $secure ? '; Secure' : '');
    }

    public function isValid(ServerRequestInterface $request): bool
    {
        $site = strtolower($request->getHeaderLine('Sec-Fetch-Site'));
        if ($site !== '' && !in_array($site, ['same-origin', 'none'], true)) {
            return false;
        }

        $cookie = $request->getCookieParams()[self::COOKIE] ?? null;
        $sent = $this->submitted($request);

        return is_string($cookie) && self::isWellFormed($cookie) && $sent !== null && hash_equals($cookie, $sent);
    }

    private function submitted(ServerRequestInterface $request): ?string
    {
        $body = $request->getParsedBody();
        if (is_array($body)) {
            return is_string($body[self::FIELD] ?? null) ? $body[self::FIELD] : null;
        }

        // The host has no body parser: read the form ourselves
        if (str_contains(strtolower($request->getHeaderLine('Content-Type')), 'application/x-www-form-urlencoded')) {
            parse_str((string)$request->getBody(), $fields);

            return is_string($fields[self::FIELD] ?? null) ? $fields[self::FIELD] : null;
        }

        return null;
    }

    private static function isWellFormed(string $token): bool
    {
        return preg_match('/^[a-f0-9]{32}$/D', $token) === 1;
    }
}
