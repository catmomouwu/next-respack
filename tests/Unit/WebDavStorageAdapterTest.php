<?php

namespace Tests\Unit;

use App\Services\Storage\WebDavStorageAdapter;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class WebDavStorageAdapterTest extends TestCase
{
    private function adapter(string $prefix = 'next-respack'): WebDavStorageAdapter
    {
        return new WebDavStorageAdapter('https://dav.example.com/files/user', 'alice', 'secret', $prefix);
    }

    public function test_put_stream_creates_parent_collections_and_uploads(): void
    {
        Http::fake(['*' => Http::response('', 201)]);

        $stream = fopen('php://temp', 'r+');
        fwrite($stream, 'zip-bytes');
        rewind($stream);

        $this->adapter()->putStream('packs/abc.zip', $stream);

        Http::assertSent(fn ($request) => $request->method() === 'MKCOL'
            && $request->url() === 'https://dav.example.com/files/user/next-respack/packs');

        Http::assertSent(fn ($request) => $request->method() === 'PUT'
            && $request->url() === 'https://dav.example.com/files/user/next-respack/packs/abc.zip'
            && $request->hasHeader('Authorization'));
    }

    public function test_exists_and_size_use_head_metadata(): void
    {
        Http::fake([
            '*' => Http::response('', 200, ['Content-Length' => '4096']),
        ]);

        $adapter = $this->adapter();

        $this->assertTrue($adapter->exists('packs/abc.zip'));
        $this->assertSame(4096, $adapter->size('packs/abc.zip'));
    }

    public function test_exists_returns_false_when_the_file_is_missing(): void
    {
        Http::fake(['*' => Http::response('', 404)]);

        $this->assertFalse($this->adapter()->exists('packs/missing.zip'));
    }

    public function test_exists_falls_back_to_propfind_when_head_is_refused(): void
    {
        Http::fake(function ($request) {
            if ($request->method() === 'HEAD') {
                return Http::response('', 405);
            }

            return Http::response('<?xml version="1.0"?><d:multistatus xmlns:d="DAV:"></d:multistatus>', 207);
        });

        $this->assertTrue($this->adapter()->exists('packs/abc.zip'));
    }

    public function test_read_stream_applies_a_range_and_returns_the_body(): void
    {
        Http::fake(['*' => Http::response('partial', 206)]);

        $stream = $this->adapter()->readStream('packs/abc.zip', 10, 20);
        $content = stream_get_contents($stream);
        fclose($stream);

        $this->assertSame('partial', $content);

        Http::assertSent(fn ($request) => $request->hasHeader('Range', 'bytes=10-29'));
    }

    public function test_delete_ignores_a_missing_file(): void
    {
        Http::fake(['*' => Http::response('', 404)]);

        $this->adapter()->delete('packs/missing.zip');

        Http::assertSent(fn ($request) => $request->method() === 'DELETE');
    }

    public function test_connection_test_round_trips_a_probe_file(): void
    {
        Http::fake(function ($request) {
            return match ($request->method()) {
                'GET' => Http::response('next-respack', 200),
                'HEAD' => Http::response('', 200, ['Content-Length' => '12']),
                'DELETE' => Http::response('', 204),
                default => Http::response('', 201),
            };
        });

        $result = $this->adapter()->test();

        $this->assertTrue($result['ok']);
        $this->assertSame([], $result['errors']);
        Http::assertSent(fn ($request) => $request->method() === 'DELETE');
    }

    public function test_connection_test_reports_errors(): void
    {
        Http::fake(['*' => Http::response('denied', 403)]);

        $result = $this->adapter()->test();

        $this->assertFalse($result['ok']);
        $this->assertNotEmpty($result['errors']);
    }
}
