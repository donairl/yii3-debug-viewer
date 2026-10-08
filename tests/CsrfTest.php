<?php

declare(strict_types=1);

namespace Dxn\DebugViewer\Tests;

use Dxn\DebugViewer\Csrf;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\StreamInterface;

final class CsrfTest extends TestCase
{
    private const TOKEN = '0123456789abcdef0123456789abcdef';
    private const OTHER = 'fedcba9876543210fedcba9876543210';

    /**
     * @param array<string, string> $cookies
     * @param array<string, string> $headers
     */
    private function request(array $cookies = [], mixed $parsed = null, array $headers = [], string $raw = ''): ServerRequestInterface
    {
        $stream = $this->createMock(StreamInterface::class);
        $stream->method('__toString')->willReturn($raw);
        $request = $this->createMock(ServerRequestInterface::class);
        $request->method('getCookieParams')->willReturn($cookies);
        $request->method('getParsedBody')->willReturn($parsed);
        $request->method('getBody')->willReturn($stream);
        $request->method('getHeaderLine')->willReturnCallback(static fn(string $name): string => $headers[$name] ?? '');

        return $request;
    }

    public function testIssuesANewTokenWhenThereIsNoCookie(): void
    {
        $csrf = new Csrf();

        ['token' => $a, 'isNew' => $newA] = $csrf->token($this->request());
        ['token' => $b] = $csrf->token($this->request());

        self::assertTrue($newA);
        self::assertMatchesRegularExpression('/^[a-f0-9]{32}$/', $a);
        self::assertNotSame($a, $b);
    }

    public function testKeepsTheTokenAlreadyInTheCookie(): void
    {
        self::assertSame(['token' => self::TOKEN, 'isNew' => false], (new Csrf())->token($this->request([Csrf::COOKIE => self::TOKEN])));
    }

    public function testReplacesACookieThatIsNotAToken(): void
    {
        foreach (['', 'short', self::TOKEN . "\n", strtoupper(self::TOKEN), self::TOKEN . 'ab'] as $bad) {
            $t = (new Csrf())->token($this->request([Csrf::COOKIE => $bad]));
            self::assertTrue($t['isNew'], var_export($bad, true));
            self::assertNotSame($bad, $t['token']);
        }
    }

    public function testCookieIsHttpOnlyStrictAndScopedToTheViewer(): void
    {
        $csrf = new Csrf();

        self::assertSame('dxn_debug_csrf=' . self::TOKEN . '; Path=/debug; HttpOnly; SameSite=Strict', $csrf->cookie(self::TOKEN, '/debug', false));
        self::assertStringEndsWith('; Secure', $csrf->cookie(self::TOKEN, '/debug', true));
        self::assertStringContainsString('Path=/;', $csrf->cookie(self::TOKEN, '', false));
    }

    public function testAcceptsMatchingCookieAndFormField(): void
    {
        $request = $this->request([Csrf::COOKIE => self::TOKEN], [Csrf::FIELD => self::TOKEN]);

        self::assertTrue((new Csrf())->isValid($request));
    }

    public function testRejectsAMismatchOrAMissingHalf(): void
    {
        $csrf = new Csrf();

        self::assertFalse($csrf->isValid($this->request([Csrf::COOKIE => self::TOKEN], [Csrf::FIELD => self::OTHER])));
        self::assertFalse($csrf->isValid($this->request([], [Csrf::FIELD => self::TOKEN])), 'no cookie');
        self::assertFalse($csrf->isValid($this->request([Csrf::COOKIE => self::TOKEN], [])), 'no field');
        self::assertFalse($csrf->isValid($this->request([Csrf::COOKIE => self::TOKEN], null)), 'no body');
        self::assertFalse($csrf->isValid($this->request([Csrf::COOKIE => self::TOKEN], [Csrf::FIELD => ['x']])), 'not a string');
        self::assertFalse($csrf->isValid($this->request([Csrf::COOKIE => 'x', ], [Csrf::FIELD => 'x'])), 'a malformed token matches itself but is no token');
    }

    public function testReadsTheFormItselfWhenTheHostDoesNotParseBodies(): void
    {
        $request = $this->request([Csrf::COOKIE => self::TOKEN], null, ['Content-Type' => 'application/x-www-form-urlencoded; charset=UTF-8'], '_csrf=' . self::TOKEN . '&other=1');

        self::assertTrue((new Csrf())->isValid($request));
        self::assertFalse((new Csrf())->isValid($this->request([Csrf::COOKIE => self::TOKEN], null, ['Content-Type' => 'text/plain'], '_csrf=' . self::TOKEN)), 'only forms are read');
    }

    public function testRefusesRequestsTheBrowserSaysAreFromAnotherSite(): void
    {
        $csrf = new Csrf();
        $good = [Csrf::COOKIE => self::TOKEN];
        $body = [Csrf::FIELD => self::TOKEN];

        foreach (['cross-site', 'same-site', 'Cross-Site'] as $site) {
            self::assertFalse($csrf->isValid($this->request($good, $body, ['Sec-Fetch-Site' => $site])), $site);
        }
        foreach (['same-origin', 'none', ''] as $site) {
            self::assertTrue($csrf->isValid($this->request($good, $body, ['Sec-Fetch-Site' => $site])), "[$site]");
        }
    }
}
