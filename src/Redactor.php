<?php

declare(strict_types=1);

namespace Dxn\DebugViewer;

use function addcslashes;
use function array_unique;
use function explode;
use function implode;
use function in_array;
use function is_array;
use function is_scalar;
use function is_string;
use function ltrim;
use function preg_match;
use function preg_match_all;
use function preg_quote;
use function preg_replace;
use function preg_replace_callback;
use function str_contains;
use function str_ends_with;
use function str_replace;
use function strlen;
use function strpos;
use function strspn;
use function strrpos;
use function strtolower;
use function substr;
use function trim;
use function urldecode;

/**
 * Masks credentials and similar values in a dump, so what is on screen (or
 * pasted into a ticket) does not carry live secrets.
 *
 * Names decide: a key, header, query parameter or form/JSON field is
 * sensitive when one of its words (`password`, `token`, `secret`, `api_key`,
 * `cookie`, `authorization`, `csrf`...) appears in it, after splitting
 * camelCase and punctuation. `apiToken`, `X-CSRF-Token` and `access_token`
 * match; `compass` and `bypass` do not. Values then get replaced, and the
 * names stay, so a masked request still shows what was sent.
 *
 * This is best effort, not a guarantee. A secret under an innocuous name or
 * inside free text stays visible; `redactKeys` adds words for such names.
 *
 * Instances count what they mask (see `dump()`), so do not share one across
 * concurrent use.
 */
final class Redactor
{
    public const MASK = '[REDACTED]';

    private const WORDS = [
        'password', 'passwd', 'passphrase', 'pass', 'pwd', 'secret', 'token', 'apikey', 'api_key',
        'authorization', 'cookie', 'credential', 'csrf', 'xsrf', 'private_key', 'privatekey',
        'sessid', 'sessionid', 'session_id',
    ];

    /** Message-like fields get their `password=...` style fragments scrubbed too. */
    private const TEXT_KEYS = ['message', 'error', 'traceAsString'];

    private string $keyPattern;

    private int $count = 0;

    /**
     * @param list<string> $extraWords More words that make a name sensitive, e.g. `ssn`, `card_number`.
     */
    public function __construct(array $extraWords = [])
    {
        $words = [];
        foreach ([...self::WORDS, ...$extraWords] as $word) {
            $normal = self::normalize((string)$word);
            if ($normal !== '') {
                // plural too (tokens, secrets), except `pass`, which would catch "passes"
                $words[] = preg_quote($normal, '/') . ($normal === 'pass' ? '' : 's?');
            }
        }

        $this->keyPattern = '/(?:^|_)(?:' . implode('|', array_unique($words)) . ')(?:_|$)/';
    }

    public function isSensitiveKey(string $key): bool
    {
        return preg_match($this->keyPattern, self::normalize($key)) === 1;
    }

    /**
     * Masks a whole dump (the `data` of one request).
     *
     * @param array<mixed> $data
     *
     * @return array{0: array<mixed>, 1: int} The masked data and how many values were masked.
     */
    public function dump(array $data): array
    {
        $this->count = 0;
        $out = [];

        foreach ($data as $collector => $payload) {
            if (!is_array($payload)) {
                $out[$collector] = $payload;
                continue;
            }
            $out[$collector] = match ($collector) {
                DumpView::DB => $this->database($payload),
                DumpView::REQUEST => $this->requestCollector($payload),
                default => $this->walk($payload),
            };
        }

        return [$out, $this->count];
    }

    /**
     * A header value, masked when the header carries credentials.
     */
    public function header(string $name, string $value): string
    {
        if (!$this->isSensitiveKey($name) || trim($value) === '') {
            return $value;
        }

        $lower = strtolower($name);

        if ($lower === 'cookie') {
            return (string)preg_replace_callback(
                '/(^|;\s*)([^=;]+)=([^;]*)/',
                fn(array $m): string => $m[1] . $m[2] . '=' . $this->mask(),
                $value,
            );
        }

        if ($lower === 'set-cookie') {
            return (string)preg_replace_callback(
                '/^([^=;]+)=([^;]*)/',
                fn(array $m): string => $m[1] . '=' . $this->mask(),
                $value,
            );
        }

        // "Bearer abc" keeps the scheme, which is useful and not secret
        if ($lower !== 'x-api-key' && preg_match('/^([A-Za-z][\w.-]*)\s+\S/', $value, $m) === 1) {
            return $m[1] . ' ' . $this->mask();
        }

        return $this->mask();
    }

    /**
     * Masks sensitive query parameters of a URL, leaving the rest as it was.
     */
    public function url(string $url): string
    {
        return (string)preg_replace_callback(
            '/([?&])([^=&#]+)=([^&#]*)/',
            fn(array $m): string => $this->isSensitiveKey(urldecode($m[2])) && $m[3] !== ''
                ? $m[1] . $m[2] . '=' . $this->maskEncoded()
                : $m[0],
            $url,
        );
    }

    /**
     * A bare query string (`a=1&token=x`), as RequestCollector stores it.
     */
    public function query(string $query): string
    {
        return ltrim($this->url('?' . $query), '?');
    }

    /**
     * A JSON or form body. Other types are returned as they are.
     */
    public function body(string $contentType, string $body): string
    {
        $type = strtolower($contentType);

        if (str_contains($type, 'json')) {
            return $this->json($body);
        }

        if (str_contains($type, 'x-www-form-urlencoded')) {
            return (string)preg_replace_callback(
                '/(^|&)([^=&]+)=([^&]*)/',
                fn(array $m): string => $this->isSensitiveKey(urldecode($m[2])) && $m[3] !== ''
                    ? $m[1] . $m[2] . '=' . $this->maskEncoded()
                    : $m[0],
                $body,
            );
        }

        return $body;
    }

    /**
     * A raw HTTP message as RequestCollector records it: request line (query
     * string), headers and body.
     */
    public function http(string $raw): string
    {
        if ($raw === '') {
            return $raw;
        }
        if (preg_match('/\r?\n\r?\n/', $raw, $m, PREG_OFFSET_CAPTURE) !== 1) {
            return $this->httpHead($raw, $contentType);
        }

        $head = substr($raw, 0, $m[0][1]);
        $body = substr($raw, $m[0][1] + strlen($m[0][0]));
        $maskedHead = $this->httpHead($head, $contentType);

        return $maskedHead . $m[0][0] . $this->body($contentType, $body);
    }

    /**
     * A statement and its bound params with sensitive values masked: params
     * named like a secret, params bound to a sensitive column
     * (`password = :qp0`), and literals compared to one
     * (`token LIKE 'abc%'`). `$statement` may have values substituted or
     * placeholders.
     *
     * @param array<mixed> $params
     *
     * @return array{0: string, 1: array<mixed>}
     */
    public function sql(string $statement, array $params): array
    {
        $sensitive = $this->sensitiveParams($params, $statement);

        return [$this->maskStatement($statement, $params, $sensitive), $this->maskParams($params, $sensitive)];
    }

    private function mask(): string
    {
        $this->count++;

        return self::MASK;
    }

    private function maskEncoded(): string
    {
        $this->count++;

        return '%5BREDACTED%5D';
    }

    /**
     * @param array<mixed> $params
     *
     * @return array<string, true> Names of the params that hold secrets.
     */
    private function sensitiveParams(array $params, string $statement): array
    {
        $sensitive = [];
        foreach ($params as $name => $_) {
            if (is_string($name) && $this->isSensitiveKey($name)) {
                $sensitive[$name] = true;
            }
        }

        // `password = :qp0`: the placeholder name says nothing, the column does
        preg_match_all('/([`"\[]?[\w.]+[`"\]]?)\s*(?:=|<>|!=|like|ilike)\s*(:\w+)/i', $statement, $found, PREG_SET_ORDER);
        foreach ($found as $f) {
            if ($this->isSensitiveKey($this->columnName($f[1]))) {
                $sensitive[$f[2]] = true;
            }
        }

        return $sensitive;
    }

    /**
     * @param array<mixed> $params
     * @param array<string, true> $sensitive
     *
     * @return array<mixed>
     */
    private function maskParams(array $params, array $sensitive): array
    {
        foreach ($params as $name => $value) {
            if (isset($sensitive[$name]) && is_scalar($value) && (string)$value !== '') {
                $params[$name] = $this->mask();
            }
        }

        return $params;
    }

    /**
     * @param array<mixed> $params
     * @param array<string, true> $sensitive
     */
    private function maskStatement(string $statement, array $params, array $sensitive): string
    {
        foreach ($sensitive as $name => $_) {
            $value = $params[$name] ?? null;
            if (is_scalar($value) && (string)$value !== '' && (string)$value !== self::MASK) {
                $statement = $this->replaceLiteral($statement, (string)$value);
            }
        }

        // `password = 'hunter2'`, `token LIKE 'abc%'`
        return (string)preg_replace_callback(
            "/([`\"\\[]?[\\w.]+[`\"\\]]?)(\\s*(?:=|<>|!=|like|ilike)\\s*)'(?:[^']|'')*'/i",
            fn(array $m): string => $this->isSensitiveKey($this->columnName($m[1])) ? $m[1] . $m[2] . "'" . self::MASK . "'" : $m[0],
            $statement,
        );
    }

    private static function normalize(string $key): string
    {
        $split = (string)preg_replace('/(?<=[a-z0-9])(?=[A-Z])/', '_', $key);

        return trim((string)preg_replace('/[^a-z0-9]+/', '_', strtolower($split)), '_');
    }

    private function columnName(string $identifier): string
    {
        $identifier = trim($identifier, '`"[]');
        $dot = strrpos($identifier, '.');

        return trim($dot === false ? $identifier : substr($identifier, $dot + 1), '`"[]');
    }

    /**
     * @param array<mixed> $payload
     *
     * @return array<mixed>
     */
    private function database(array $payload): array
    {
        $queries = $payload['queries'] ?? null;
        if (!is_array($queries)) {
            return $this->walk($payload);
        }

        unset($payload['queries']);
        $out = $this->walk($payload);
        $out['queries'] = [];

        foreach ($queries as $key => $query) {
            if (!is_array($query)) {
                $out['queries'][$key] = $query;
                continue;
            }

            $params = is_array($query['params'] ?? null) ? $query['params'] : [];
            $statements = [];
            foreach (['sql', 'rawSql'] as $field) {
                if (isset($query[$field]) && is_string($query[$field])) {
                    $statements[$field] = $query[$field];
                }
            }
            $sensitive = $this->sensitiveParams($params, $statements['sql'] ?? $statements['rawSql'] ?? '');

            unset($query['sql'], $query['rawSql'], $query['params']);
            $masked = $this->walk($query);
            foreach ($statements as $field => $statement) {
                $masked[$field] = $this->maskStatement($statement, $params, $sensitive);
            }
            if (isset($queries[$key]['params'])) {
                $masked['params'] = $this->maskParams($params, $sensitive);
            }

            $out['queries'][$key] = $masked;
        }

        return $out;
    }

    /**
     * @param array<mixed> $payload
     *
     * @return array<mixed>
     */
    private function requestCollector(array $payload): array
    {
        foreach (['requestRaw', 'responseRaw'] as $key) {
            if (isset($payload[$key]) && is_string($payload[$key])) {
                $payload[$key] = $this->http($payload[$key]);
            }
        }
        if (isset($payload['requestUrl']) && is_string($payload['requestUrl'])) {
            $payload['requestUrl'] = $this->url($payload['requestUrl']);
        }
        if (isset($payload['requestQuery']) && is_string($payload['requestQuery'])) {
            $payload['requestQuery'] = $this->query($payload['requestQuery']);
        }

        return $this->walk($payload, skip: ['requestRaw', 'responseRaw', 'requestUrl', 'requestQuery']);
    }

    /**
     * @param array<mixed> $value
     * @param list<string> $skip Keys already handled by the caller.
     *
     * @return array<mixed>
     */
    private function walk(array $value, array $skip = []): array
    {
        $out = [];

        foreach ($value as $key => $item) {
            if (is_string($key) && in_array($key, $skip, true)) {
                $out[$key] = $item;
                continue;
            }

            if (is_string($key) && $key === 'args' && is_array($item) && $item !== []) {
                // call arguments of a stack frame: anything could be in there
                $out[$key] = [$this->mask()];
                continue;
            }

            if (is_string($key) && $this->isSensitiveKey($key) && (is_array($item) ? $item !== [] : (is_scalar($item) && (string)$item !== ''))) {
                $out[$key] = $this->mask();
                continue;
            }

            if (is_array($item)) {
                $out[$key] = $this->walk($item);
            } elseif (is_string($item)) {
                $out[$key] = $this->text($item, is_string($key) && in_array($key, self::TEXT_KEYS, true));
            } else {
                $out[$key] = $item;
            }
        }

        return $out;
    }

    /**
     * A string leaf: URLs with a query string, bearer tokens everywhere,
     * `password=...` fragments in message-like fields, and the values of
     * JSON-looking strings.
     */
    private function text(string $text, bool $isMessage): string
    {
        if ($text === '') {
            return $text;
        }

        // A string that is a whole URL or request target with a query string, wherever it sits
        // (summaries, HTTP client calls, ...): `https://api/x?key=...`, `/login?token=...`
        if (str_contains($text, '?') && preg_match('#^(?:https?://|/)\S*\?\S*$#', $text) === 1) {
            $text = $this->url($text);
        }

        if (str_contains($text, 'Bearer ') || str_contains($text, 'Basic ')) {
            $text = (string)preg_replace_callback(
                '/\b(Bearer|Basic)(\s+)[A-Za-z0-9._~+\/=-]{8,}/',
                fn(array $m): string => $m[1] . $m[2] . $this->mask(),
                $text,
            );
        }

        if ($isMessage) {
            $text = (string)preg_replace_callback(
                '/\b([A-Za-z_]*(?:password|passwd|secret|token|api[_-]?key)[A-Za-z_]*)(["\']?\s*[=:]\s*)("[^"]*"|\'[^\']*\'|[^\s,;&)}\]"\']+)/i',
                fn(array $m): string => str_contains($m[3], self::MASK) ? $m[0] : $m[1] . $m[2] . $this->mask(),
                $text,
            );
        }

        $first = $text[strspn($text, " \t\r\n")] ?? '';
        if ($first === '{' || $first === '[') {
            $text = $this->json($text);
        }

        return $text;
    }

    /**
     * Values of sensitive keys in JSON text. Layout and everything else stays
     * byte for byte; strings, numbers and literals under a sensitive key are
     * replaced.
     */
    private function json(string $json): string
    {
        return (string)preg_replace_callback(
            '/("(?:[^"\\\\]|\\\\.)*")(\s*:\s*)("(?:[^"\\\\]|\\\\.)*"|-?\d[\d.eE+-]*|true|false)/',
            fn(array $m): string => $this->isSensitiveKey(substr($m[1], 1, -1)) && $m[3] !== '""'
                ? $m[1] . $m[2] . '"' . $this->mask() . '"'
                : $m[0],
            $json,
        );
    }

    /**
     * Header block of a raw message; also masks secrets in the request line.
     *
     * @param-out string $contentType
     */
    private function httpHead(string $head, ?string &$contentType = null): string
    {
        $contentType = '';
        $lines = explode("\n", $head);

        foreach ($lines as $i => $line) {
            $cr = str_ends_with($line, "\r") ? "\r" : '';
            $text = $cr !== '' ? substr($line, 0, -1) : $line;

            if ($i === 0) {
                // GET /path?token=abc HTTP/1.1
                $lines[$i] = $this->url($text) . $cr;
                continue;
            }

            if (preg_match('/^([A-Za-z0-9!#$%&\'*+.^_`|~-]+):[ \t]*(.*)$/', $text, $m) === 1) {
                if (strtolower($m[1]) === 'content-type') {
                    $contentType = $m[2];
                }
                $lines[$i] = $m[1] . ': ' . $this->header($m[1], $m[2]) . $cr;
            }
        }

        return implode("\n", $lines);
    }

    /**
     * Replaces a bound value inside a statement that already has values
     * substituted, whichever way the driver quoted it.
     */
    private function replaceLiteral(string $sql, string $value): string
    {
        $mask = "'" . self::MASK . "'";
        $quoted = ["'" . str_replace("'", "''", $value) . "'", "'" . addcslashes($value, "'\\") . "'"];
        $sql = str_replace($quoted, $mask, $sql);

        // a short bare value (a PIN, `1`) is too likely to appear elsewhere
        if (strlen($value) >= 4 && strpos($sql, $value) !== false) {
            $sql = str_replace($value, self::MASK, $sql);
        }

        return $sql;
    }
}
