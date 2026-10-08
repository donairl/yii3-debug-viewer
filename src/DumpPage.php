<?php

declare(strict_types=1);

namespace Dxn\DebugViewer;

/**
 * The template parameters a dump needs, whether it is served live or written
 * out as a snapshot.
 */
final class DumpPage
{
    /**
     * @param array{id: string, meta: array<string, mixed>, summary: array, data: array, warning: ?string, redaction: array{enabled: bool, revealed: bool, masked: int}} $dump
     *
     * @return array<string, mixed>
     */
    public static function params(array $dump, EditorLinker $editor): array
    {
        return [
            'title' => $dump['meta']['method'] . ' ' . ($dump['meta']['path'] ?: '/'),
            'meta' => $dump['meta'],
            'summary' => $dump['summary'],
            'view' => new DumpView($dump['data']),
            'editor' => $editor,
            'warning' => $dump['warning'],
        ];
    }
}
