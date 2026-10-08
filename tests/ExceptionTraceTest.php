<?php

declare(strict_types=1);

namespace Dxn\DebugViewer\Tests;

use Dxn\DebugViewer\ExceptionTrace;
use PHPUnit\Framework\TestCase;

final class ExceptionTraceTest extends TestCase
{
    public function testThrowSiteIsFirstFrameThenTraceInOrder(): void
    {
        $d = ExceptionTrace::describe([
            'class' => 'RuntimeException',
            'message' => 'boom',
            'code' => 7,
            'file' => '/app/src/Service.php',
            'line' => 42,
            'trace' => [
                ['file' => '/app/src/Controller.php', 'line' => 10, 'function' => 'run', 'class' => 'App\\Service', 'type' => '->'],
                ['file' => '/app/vendor/yiisoft/router/Dispatcher.php', 'line' => 99, 'function' => 'dispatch', 'class' => 'Yiisoft\\Router\\Dispatcher', 'type' => '->'],
                ['function' => '{closure}'],
            ],
        ]);

        self::assertSame('RuntimeException', $d['class']);
        self::assertSame('7', $d['code']);
        self::assertCount(4, $d['frames']);
        self::assertSame(['index' => 0, 'file' => '/app/src/Service.php', 'line' => '42', 'call' => 'thrown here', 'vendor' => false], $d['frames'][0]);
        self::assertSame('App\\Service->run()', $d['frames'][1]['call']);
        self::assertSame('10', $d['frames'][1]['line']);
        self::assertTrue($d['frames'][2]['vendor']);
        self::assertSame('{closure}()', $d['frames'][3]['call']);
        self::assertSame('', $d['frames'][3]['file']);
        self::assertSame([0, 1, 2, 3], array_column($d['frames'], 'index'));
    }

    public function testFallsBackToTraceAsStringWhenTraceIsMissing(): void
    {
        $d = ExceptionTrace::describe([
            'file' => '/app/a.php',
            'line' => 1,
            'traceAsString' => "#0 /app/src/Foo.php(12): App\\Foo->bar()\n#1 [internal function]: App\\Foo->{closure}()\n#2 /app/vendor/x/Y.php(3): call_user_func()\n#3 {main}",
        ]);

        self::assertSame(
            ['thrown here', 'App\\Foo->bar()', 'App\\Foo->{closure}()', 'call_user_func()', '{main}'],
            array_column($d['frames'], 'call'),
        );
        self::assertSame('12', $d['frames'][1]['line']);
        self::assertTrue($d['frames'][3]['vendor']);
    }

    public function testToleratesGarbage(): void
    {
        $d = ExceptionTrace::describe(['trace' => 'nope', 'file' => ['x'], 'message' => null]);

        self::assertSame('Exception', $d['class']);
        self::assertSame([], $d['frames']);
        self::assertSame('', $d['message']);
    }

    public function testSegmentsGroupConsecutiveFramesByOrigin(): void
    {
        $frame = static fn(int $i, bool $vendor): array => ['index' => $i, 'file' => 'f', 'line' => '1', 'call' => 'c', 'vendor' => $vendor];

        $segments = ExceptionTrace::segments([$frame(0, false), $frame(1, true), $frame(2, true), $frame(3, false)]);

        self::assertSame([false, true, false], array_column($segments, 'vendor'));
        self::assertCount(2, $segments[1]['frames']);
    }

    public function testThrowSiteInVendorCodeStaysVisible(): void
    {
        $d = ExceptionTrace::describe([
            'file' => '/app/vendor/lib/Db.php',
            'line' => 3,
            'trace' => [
                ['file' => '/app/vendor/lib/Cmd.php', 'line' => 4, 'function' => 'run'],
                ['file' => '/app/src/Repo.php', 'line' => 5, 'function' => 'find'],
            ],
        ]);

        self::assertTrue($d['frames'][0]['vendor']);
        $segments = ExceptionTrace::segments($d['frames']);

        self::assertSame([false, true, false], array_column($segments, 'vendor'));
        self::assertSame([0], array_column($segments[0]['frames'], 'index'));
        self::assertSame([1], array_column($segments[1]['frames'], 'index'));
    }

    public function testFramesWithoutAFileAreNotApplicationCode(): void
    {
        $d = ExceptionTrace::describe(['trace' => [['function' => '{closure}']], 'traceAsString' => '']);
        self::assertTrue($d['frames'][0]['vendor']);

        $d = ExceptionTrace::describe(['traceAsString' => '#0 {main}']);
        self::assertTrue($d['frames'][0]['vendor']);
    }

    public function testWindowsStylePathsCountAsVendor(): void
    {
        self::assertTrue(ExceptionTrace::isVendor('C:\\app\\vendor\\yiisoft\\x.php'));
        self::assertFalse(ExceptionTrace::isVendor('/app/src/vendors.php'));
    }
}
