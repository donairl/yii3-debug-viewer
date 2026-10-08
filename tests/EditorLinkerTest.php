<?php

declare(strict_types=1);

namespace Dxn\DebugViewer\Tests;

use Dxn\DebugViewer\EditorLinker;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class EditorLinkerTest extends TestCase
{
    public function testDisabledByDefault(): void
    {
        $linker = new EditorLinker();

        self::assertFalse($linker->enabled());
        self::assertNull($linker->url('/app/src/A.php', 12));
        self::assertNull($linker->urlForLocation('/app/src/A.php:12'));
    }

    #[DataProvider('presets')]
    public function testPresets(string $editor, string $expected): void
    {
        self::assertSame($expected, (new EditorLinker($editor))->url('/app/src/A.php', 12));
    }

    public static function presets(): iterable
    {
        yield 'phpstorm' => ['phpstorm', 'phpstorm://open?file=/app/src/A.php&line=12'];
        yield 'vscode' => ['vscode', 'vscode://file//app/src/A.php:12'];
        yield 'case-insensitive' => ['PhpStorm', 'phpstorm://open?file=/app/src/A.php&line=12'];
        yield 'custom' => ['myed://{file}#{line}', 'myed:///app/src/A.php#12'];
    }

    public function testUnknownEditorDisablesLinks(): void
    {
        self::assertFalse((new EditorLinker('emacs'))->enabled());
        self::assertFalse((new EditorLinker('myed://no-placeholder'))->enabled());
    }

    public function testPathsAreEncodedSegmentWiseKeepingSlashes(): void
    {
        $url = (new EditorLinker('phpstorm'))->url('/my app/src/Ä&b.php', 3);

        self::assertSame('phpstorm://open?file=/my%20app/src/%C3%84%26b.php&line=3', $url);
    }

    public function testBadOrMissingLineOpensAtOne(): void
    {
        $linker = new EditorLinker('phpstorm');

        self::assertSame('phpstorm://open?file=/a.php&line=1', $linker->url('/a.php'));
        self::assertSame('phpstorm://open?file=/a.php&line=1', $linker->url('/a.php', 'x"><script>'));
        self::assertSame('phpstorm://open?file=/a.php&line=1', $linker->url('/a.php', 0));
    }

    public function testRelativeAndPseudoPathsAreNotLinked(): void
    {
        $linker = new EditorLinker('phpstorm');

        self::assertNull($linker->url('src/A.php', 1));
        self::assertNull($linker->url('[internal function]', 1));
        self::assertNull($linker->url('', 1));
    }

    public function testWindowsPaths(): void
    {
        self::assertSame(
            'vscode://file/C%3A/app/src/A.php:4',
            (new EditorLinker('vscode'))->url('C:\\app\\src\\A.php', 4),
        );
    }

    public function testPathMapRewritesPrefix(): void
    {
        $linker = new EditorLinker('phpstorm', ['/app' => '/home/me/project']);

        self::assertSame('phpstorm://open?file=/home/me/project/src/A.php&line=5', $linker->url('/app/src/A.php', 5));
        self::assertSame('phpstorm://open?file=/home/me/project&line=1', $linker->url('/app'));
    }

    public function testPathMapRespectsDirectoryBoundariesAndPrefersLongest(): void
    {
        $linker = new EditorLinker('phpstorm', [
            '/app' => '/local',
            '/app/vendor/' => '/local-vendor',
        ]);

        self::assertSame('phpstorm://open?file=/application/A.php&line=1', $linker->url('/application/A.php'));
        self::assertSame('phpstorm://open?file=/local-vendor/x/Y.php&line=2', $linker->url('/app/vendor/x/Y.php', 2));
        self::assertSame('phpstorm://open?file=/local/src/Z.php&line=3', $linker->url('/app/src/Z.php', 3));
    }

    public function testLocationStrings(): void
    {
        $linker = new EditorLinker('phpstorm');

        self::assertSame('phpstorm://open?file=/app/src/A.php&line=77', $linker->urlForLocation('/app/src/A.php:77'));
        self::assertSame('phpstorm://open?file=/app/src/A.php&line=1', $linker->urlForLocation('/app/src/A.php'));
        self::assertSame(['/a:b/c.php', '9'], EditorLinker::splitLocation('/a:b/c.php:9'));
        self::assertSame(['C:\\x.php', '9'], EditorLinker::splitLocation('C:\\x.php:9'));
    }
}
