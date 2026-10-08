<?php

declare(strict_types=1);

namespace Dxn\DebugViewer\Tests;

use Dxn\DebugViewer\BodyView;
use Dxn\DebugViewer\RequestParams;
use PHPUnit\Framework\TestCase;

final class RequestParamsTest extends TestCase
{
    public function testGetComesFromTheRequestTargetAndNestsLikePhp(): void
    {
        $get = RequestParams::get('GET /p?page=2&f[a]=1&f[b]=x%20y#frag HTTP/1.1');

        self::assertSame(['page' => '2', 'f' => ['a' => '1', 'b' => 'x y']], $get);
    }

    public function testGetFallsBackToTheRecordedQuery(): void
    {
        self::assertSame(['a' => '1'], RequestParams::get('', 'a=1'));
        self::assertSame([], RequestParams::get('GET /p HTTP/1.1'));
    }

    public function testPostReadsAUrlencodedForm(): void
    {
        $post = RequestParams::post(['Content-Type' => ['application/x-www-form-urlencoded; charset=UTF-8']], 'user=bob&tags[]=a&tags[]=b');

        self::assertSame(['data' => ['user' => 'bob', 'tags' => ['a', 'b']], 'source' => 'form'], $post);
    }

    public function testPostReadsMultipartFieldsAndListsFilesWithoutReadingThem(): void
    {
        $body = "--XX\r\nContent-Disposition: form-data; name=\"title\"\r\n\r\nhello\r\n"
            . "--XX\r\nContent-Disposition: form-data; name=\"meta[k]\"\r\n\r\nv\r\n"
            . "--XX\r\nContent-Disposition: form-data; name=\"doc\"; filename=\"a.txt\"\r\nContent-Type: text/plain\r\n\r\nSECRETCONTENT\r\n"
            . "--XX--\r\n";
        $post = RequestParams::post(['content-type' => ['multipart/form-data; boundary=XX']], $body);

        self::assertNotNull($post);
        self::assertSame('multipart', $post['source']);
        self::assertSame('hello', $post['data']['title']);
        self::assertSame(['k' => 'v'], $post['data']['meta']);
        self::assertStringStartsWith('[file] a.txt', $post['data']['doc']);
        self::assertStringNotContainsString('SECRETCONTENT', $post['data']['doc']);
    }

    public function testPostReadsAJsonObjectButNotAScalarOrABinaryBody(): void
    {
        $json = ['Content-Type' => ['application/json']];

        self::assertSame(['data' => ['a' => 1], 'source' => 'json'], RequestParams::post($json, '{"a":1}'));
        self::assertNull(RequestParams::post($json, '"text"'));
        self::assertNull(RequestParams::post(['Content-Type' => ['image/png']], "\x89PNG"));
        self::assertNull(RequestParams::post($json, ''));
    }

    public function testSessionComesFromACollectorNamedLikeOneAndIsUnwrapped(): void
    {
        $collectors = [
            'App\\Other' => ['x' => 1],
            'App\\Debug\\SessionCollector' => ['data' => ['user_id' => 7, 'flash' => ['ok']]],
        ];

        self::assertSame(['user_id' => 7, 'flash' => ['ok']], RequestParams::session($collectors));
        self::assertNull(RequestParams::session(['App\\Other' => ['x' => 1]]));
    }

    public function testParamsTableEscapesAndOpensNestedValuesAsATree(): void
    {
        $html = BodyView::params(['<b>' => '<script>x</script>', 'n' => 3, 'f' => ['a' => '1']]);

        self::assertStringNotContainsString('<script>', $html);
        self::assertStringContainsString('&lt;script&gt;', $html);
        self::assertStringContainsString('class="jt-num">3', $html);
        self::assertStringContainsString('1 key', $html, 'an associative array is a keyed node, not a list');
        self::assertSame('', BodyView::params([]));
    }
}
