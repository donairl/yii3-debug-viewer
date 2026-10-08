<?php

declare(strict_types=1);

namespace Dxn\DebugViewer\Tests;

use Dxn\DebugViewer\DumpStorage;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Yiisoft\Aliases\Aliases;

final class DumpStorageTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/dxn-debug-' . bin2hex(random_bytes(6));
        mkdir($this->root . '/debug', 0777, true);
    }

    protected function tearDown(): void
    {
        $this->remove($this->root);
    }

    private function storage(int $maxDumpBytes = 16 * 1048576): DumpStorage
    {
        return new DumpStorage(new Aliases(['@runtime' => $this->root]), '@runtime/debug', $maxDumpBytes);
    }

    /**
     * ids are uniqid()-shaped, like yii-debug writes: the first 13 hex digits
     * are time, so a bigger id is a newer dump.
     */
    private static function id(int $seconds, int $micro = 0, int $tail = 12345678): string
    {
        return sprintf('%08x%05x%08d', $seconds, $micro, $tail);
    }

    private function dump(string $date, string $id, ?array $data = ['k' => 'v'], string|array|null $summary = null): void
    {
        $dir = "$this->root/debug/$date/$id";
        mkdir($dir, 0777, true);
        $summary ??= ['summary' => [
            'Yiisoft\\Yii\\Debug\\Collector\\Web\\RequestCollector' => ['request' => ['method' => 'GET', 'path' => '/p' . $id], 'response' => ['statusCode' => 200]],
        ]];
        if ($summary !== '') {
            file_put_contents("$dir/summary.json", is_array($summary) ? json_encode($summary) : $summary);
        }
        if ($data !== null) {
            file_put_contents("$dir/data.json", json_encode($data));
        }
    }

    private function remove(string $path): void
    {
        if (is_file($path) || is_link($path)) {
            unlink($path);
            return;
        }
        foreach (glob($path . '/*') ?: [] as $child) {
            $this->remove($child);
        }
        @rmdir($path);
    }

    public function testListsNewestFirstAcrossDatesAndWithinADate(): void
    {
        $a = self::id(1000);
        $b = self::id(2000);
        $c = self::id(2000, 1);
        $d = self::id(3000);
        // written in no particular order, and the newest id sits in the older date folder's neighbour
        $this->dump('2026-10-01', $b);
        $this->dump('2026-10-02', $d);
        $this->dump('2026-10-01', $a);
        $this->dump('2026-10-01', $c);

        self::assertSame([$d, $c, $b, $a], array_column($this->storage()->list(), 'id'));
    }

    public function testLimitStopsEarly(): void
    {
        foreach ([1, 2, 3, 4] as $n) {
            $this->dump('2026-10-01', self::id($n * 100));
        }

        self::assertSame([self::id(400), self::id(300)], array_column($this->storage()->list(2), 'id'));
    }

    public function testListSkipsDumpsWithoutAReadableSummaryWithoutCountingThem(): void
    {
        $this->dump('2026-10-03', self::id(5000), summary: '');
        $this->dump('2026-10-03', self::id(4000), summary: '{not json');
        $this->dump('2026-10-02', self::id(3000));
        $this->dump('2026-10-01', self::id(2000));

        self::assertSame([self::id(3000), self::id(2000)], array_column($this->storage()->list(2), 'id'));
    }

    public function testListIgnoresStrayFilesAndMissingBase(): void
    {
        file_put_contents($this->root . '/debug/.gitignore', '*');
        mkdir($this->root . '/debug/2026-10-01');
        file_put_contents($this->root . '/debug/2026-10-01/note.txt', 'x');
        $this->dump('2026-10-01', self::id(100));

        self::assertCount(1, $this->storage()->list());
        self::assertSame([], (new DumpStorage(new Aliases(['@runtime' => $this->root . '/nope'])))->list());
    }

    public function testListRowCarriesRequestFields(): void
    {
        $id = self::id(100);
        $this->dump('2026-10-01', $id);

        $row = $this->storage()->list()[0];

        self::assertSame(['id' => $id, 'method' => 'GET', 'path' => '/p' . $id, 'status' => 200], array_intersect_key($row, array_flip(['id', 'method', 'path', 'status'])));
    }

    public function testGetFindsADumpInAnyDate(): void
    {
        $old = self::id(1000);
        $new = self::id(9000);
        $this->dump('2026-09-01', $old, ['which' => 'old']);
        $this->dump('2026-10-01', $new, ['which' => 'new']);

        $got = $this->storage()->get($old);

        self::assertSame($old, $got['id']);
        self::assertSame(['which' => 'old'], $got['data']);
        self::assertNull($got['warning']);
        self::assertSame(['which' => 'new'], $this->storage()->get($new)['data']);
        self::assertNull($this->storage()->get(self::id(5)));
    }

    public function testGetDoesNotReadOtherDumps(): void
    {
        $target = self::id(500);
        $this->dump('2026-10-01', $target);
        for ($i = 1; $i <= 50; $i++) {
            // unreadable summaries: a lookup that touched them would trip over them
            $this->dump('2026-10-01', self::id(500 + $i), summary: '{broken');
        }

        self::assertSame($target, $this->storage()->get($target)['id']);
    }

    #[DataProvider('badIds')]
    public function testGetRejectsIdsThatAreNotPlainTokens(string $id): void
    {
        $this->dump('2026-10-01', self::id(100));
        file_put_contents($this->root . '/secret.json', '{"summary":{}}');

        self::assertNull($this->storage()->get($id));
    }

    public static function badIds(): iterable
    {
        yield 'traversal' => ['../../secret'];
        yield 'dot' => ['..'];
        yield 'slash' => ['2026-10-01/' . self::id(100)];
        yield 'glob star' => ['*'];
        yield 'glob class' => ['[a-f]*'];
        yield 'empty' => [''];
        yield 'too long' => [str_repeat('a', 65)];
        yield 'trailing newline' => [self::id(100) . "\n"];
        yield 'null byte' => ["abc\0def"];
    }

    public function testOversizedDataIsNotLoadedAndSaysSo(): void
    {
        $id = self::id(100);
        $this->dump('2026-10-01', $id, ['pad' => str_repeat('x', 5000)]);

        $got = $this->storage(maxDumpBytes: 1000)->get($id);

        self::assertSame([], $got['data']);
        self::assertSame('GET', $got['meta']['method'], 'the summary is still shown');
        self::assertStringContainsString('too large', (string)$got['warning']);
        self::assertStringContainsString('maxDumpSize', (string)$got['warning']);
    }

    public function testZeroMeansNoSizeLimit(): void
    {
        $id = self::id(100);
        $this->dump('2026-10-01', $id, ['pad' => str_repeat('x', 5000)]);

        $got = $this->storage(maxDumpBytes: 0)->get($id);

        self::assertNull($got['warning']);
        self::assertSame(5000, strlen($got['data']['pad']));
    }

    public function testBrokenOrMissingDataIsReportedNotSilentlyEmpty(): void
    {
        $broken = self::id(100);
        $missing = self::id(200);
        $this->dump('2026-10-01', $broken);
        file_put_contents("$this->root/debug/2026-10-01/$broken/data.json", '{"cut off');
        $this->dump('2026-10-01', $missing, data: null);

        $a = $this->storage()->get($broken);
        $b = $this->storage()->get($missing);

        self::assertSame([], $a['data']);
        self::assertStringContainsString('could not be decoded', (string)$a['warning']);
        self::assertSame([], $b['data']);
        self::assertStringContainsString('missing', (string)$b['warning']);
    }

    public function testIsValidId(): void
    {
        self::assertTrue(DumpStorage::isValidId(self::id(1)));
        self::assertFalse(DumpStorage::isValidId('a/b'));
    }

    public function testDeleteRemovesTheDumpAndAnEmptiedDateFolder(): void
    {
        $a = self::id(100);
        $b = self::id(200);
        $this->dump('2026-10-01', $a);
        $this->dump('2026-10-02', $b);
        file_put_contents("$this->root/debug/2026-10-01/$a/objects.json", '{}');

        self::assertTrue($this->storage()->delete($a));

        self::assertDirectoryDoesNotExist("$this->root/debug/2026-10-01/$a");
        self::assertDirectoryDoesNotExist("$this->root/debug/2026-10-01", 'the date folder went with its last dump');
        self::assertNull($this->storage()->get($a));
        self::assertSame($b, $this->storage()->get($b)['id']);
    }

    public function testDeleteKeepsADateFolderThatStillHasDumps(): void
    {
        $this->dump('2026-10-01', self::id(100));
        $this->dump('2026-10-01', self::id(200));

        self::assertTrue($this->storage()->delete(self::id(100)));

        self::assertDirectoryExists("$this->root/debug/2026-10-01");
        self::assertCount(1, $this->storage()->list());
    }

    public function testDeleteIsFalseForMissingOrMalformedIds(): void
    {
        $this->dump('2026-10-01', self::id(100));
        mkdir($this->root . '/outside');
        file_put_contents($this->root . '/outside/keep.txt', 'x');

        self::assertFalse($this->storage()->delete(self::id(999)));
        self::assertFalse($this->storage()->delete('../outside'));
        self::assertFalse($this->storage()->delete('..'));
        self::assertFalse($this->storage()->delete(''));
        self::assertFileExists($this->root . '/outside/keep.txt');
        self::assertCount(1, $this->storage()->list());
    }

    public function testDeleteLeavesADumpThatIsStillBeingWritten(): void
    {
        $id = self::id(100);
        $this->dump('2026-10-01', $id, summary: ''); // data.json, no summary.json yet

        self::assertFalse($this->storage()->delete($id));
        self::assertFileExists("$this->root/debug/2026-10-01/$id/data.json");
    }

    public function testDeleteNeverFollowsASymlinkOutOfTheDumpDirectory(): void
    {
        $target = $this->root . '/precious';
        mkdir($target);
        file_put_contents($target . '/summary.json', '{"summary":{}}');
        file_put_contents($target . '/data.json', '{}');
        mkdir($this->root . '/debug/2026-10-01');
        $id = self::id(100);
        symlink($target, "$this->root/debug/2026-10-01/$id");

        self::assertFalse($this->storage()->delete($id));
        self::assertSame(0, $this->storage()->clear());

        self::assertFileExists($target . '/summary.json');
        self::assertFileExists($target . '/data.json');
    }

    public function testDeleteRefusesAFolderThatHoldsSubfolders(): void
    {
        $id = self::id(100);
        $this->dump('2026-10-01', $id);
        mkdir("$this->root/debug/2026-10-01/$id/not-from-yii-debug");

        self::assertFalse($this->storage()->delete($id));
        self::assertDirectoryExists("$this->root/debug/2026-10-01/$id/not-from-yii-debug");
    }

    public function testClearRemovesEveryCompleteDumpAndOnlyThose(): void
    {
        $this->dump('2026-10-01', self::id(100));
        $this->dump('2026-10-01', self::id(200));
        $this->dump('2026-10-02', self::id(300));
        $this->dump('2026-10-02', self::id(400), summary: ''); // still being written
        file_put_contents($this->root . '/debug/.gitignore', '*');
        file_put_contents($this->root . '/debug/2026-10-01/notes.txt', 'mine');
        mkdir($this->root . '/debug/2026-10-03/not-a-dump', 0777, true);

        self::assertSame(3, $this->storage()->clear());

        self::assertSame([], $this->storage()->list());
        self::assertFileExists($this->root . '/debug/.gitignore');
        self::assertFileExists($this->root . '/debug/2026-10-01/notes.txt', 'a date folder with other content stays');
        self::assertDirectoryExists($this->root . '/debug/2026-10-02/' . self::id(400), 'the unfinished dump stays');
        self::assertDirectoryExists($this->root . '/debug/2026-10-03/not-a-dump');
    }

    public function testClearOnAnEmptyOrMissingDirectory(): void
    {
        self::assertSame(0, $this->storage()->clear());
        self::assertSame(0, (new DumpStorage(new Aliases(['@runtime' => $this->root . '/nope'])))->clear());
    }
}
