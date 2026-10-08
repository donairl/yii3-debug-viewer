<?php

declare(strict_types=1);

namespace Dxn\DebugViewer;

/**
 * Loads one dump the way it may be shown or handed out: with sensitive values
 * masked unless the caller asked to reveal them. The request page and the
 * export both go through here, so they cannot disagree about what is hidden.
 */
final class DumpReader
{
    public function __construct(
        private readonly DumpStorage $storage,
        private readonly Redactor $redactor,
        private readonly bool $redact = true,
    ) {}

    public function redactionEnabled(): bool
    {
        return $this->redact;
    }

    /**
     * @param bool $reveal Show real values. Ignored when redaction is switched off, which shows them anyway.
     *
     * @return array{
     *     id: string,
     *     meta: array<string, mixed>,
     *     summary: array,
     *     data: array,
     *     warning: ?string,
     *     redaction: array{enabled: bool, revealed: bool, masked: int}
     * }|null
     */
    public function get(string $id, bool $reveal = false): ?array
    {
        $dump = $this->storage->get($id);
        if ($dump === null) {
            return null;
        }

        $revealed = $this->redact && $reveal;
        $masked = 0;

        // Masked here, before anything is rendered or written out, so the real
        // values never leave this method unless they were asked for.
        if ($this->redact && !$reveal) {
            [$dump['data'], $maskedData] = $this->redactor->dump($dump['data']);
            [$dump['summary'], $maskedSummary] = $this->redactor->dump($dump['summary']);
            $dump['meta']['url'] = $this->redactor->url((string)$dump['meta']['url']);
            $masked = $maskedData + $maskedSummary;
        }

        $dump['redaction'] = ['enabled' => $this->redact, 'revealed' => $revealed, 'masked' => $masked];

        return $dump;
    }
}
