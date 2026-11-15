<?php

namespace App\Services\Download;

use App\Models\DownloadLog;
use App\Models\ResourcePackVersion;
use App\Services\Storage\StorageManager;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DownloadService
{
    public function __construct(
        private readonly StorageManager $storage,
    ) {
    }

    public function respond(Request $request, ResourcePackVersion $version): StreamedResponse|\Symfony\Component\HttpFoundation\Response
    {
        $size = (int) $version->file_size;
        $etag = '"'.$version->sha1.'"';
        $lastModified = $version->updated_at?->copy()->setTimezone('UTC');
        $filename = $this->headerFilename($version->original_filename);

        $headers = [
            'Content-Type' => 'application/zip',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"; filename*=UTF-8\'\''.rawurlencode($filename),
            'Accept-Ranges' => 'bytes',
            'ETag' => $etag,
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'public, max-age=3600',
        ];

        if ($lastModified) {
            $headers['Last-Modified'] = $lastModified->format('D, d M Y H:i:s').' GMT';
        }

        $isHead = $request->isMethod('HEAD');

        if ($this->etagMatches((string) $request->header('If-None-Match', ''), $etag)) {
            return response('', 304, $headers);
        }

        $rangeHeader = trim((string) $request->header('Range', ''));
        $range = null;

        if ($rangeHeader !== '' && $this->ifRangeAllows($request, $etag, $lastModified)) {
            $range = $this->parseRange($rangeHeader, $size);
        }

        if ($range === false) {
            $headers['Content-Range'] = 'bytes */'.$size;

            return response('', 416, $headers);
        }

        $adapter = $this->storage->adapter($version->storage_driver);

        if (! $adapter->exists($version->storage_path)) {
            return response(__('The stored file is missing. Please contact the administrator.'), 404, [
                'Content-Type' => 'text/plain; charset=UTF-8',
            ]);
        }

        if ($range === null && $isHead) {
            $headers['Content-Length'] = (string) $size;

            return response('', 200, $headers);
        }

        if ($range === null) {
            $status = 200;
            $start = 0;
            $length = $size;
            $headers['Content-Length'] = (string) $size;
        } else {
            [$start, $end] = $range;
            $length = $end - $start + 1;
            $status = 206;
            $headers['Content-Range'] = 'bytes '.$start.'-'.$end.'/'.$size;
            $headers['Content-Length'] = (string) $length;
        }

        $this->record($request, $version, $status, $rangeHeader, $length, $range !== null);

        if ($status === 206 && $isHead) {
            return response('', 206, $headers);
        }

        $accel = $this->accelPath($version, $adapter, $start, $length, $headers);
        if ($accel !== null) {
            return response('', $status, $headers);
        }

        $chunk = (int) config('nextrespack.download_stream_chunk', 8192);

        return response()->stream(function () use ($adapter, $version, $start, $length, $chunk) {
            if (function_exists('set_time_limit')) {
                @set_time_limit(0);
            }

            if (session_status() === PHP_SESSION_ACTIVE) {
                session_write_close();
            }

            while (ob_get_level() > 0 && ! app()->runningUnitTests()) {
                @ob_end_clean();
            }

            $stream = $adapter->readStream($version->storage_path, $start, $length);
            $sent = 0;

            try {
                if (is_resource($stream)) {
                    while ($sent < $length && ! feof($stream)) {
                        $buffer = fread($stream, min($chunk, $length - $sent));
                        if ($buffer === false) {
                            break;
                        }
                        echo $buffer;
                        $sent += strlen($buffer);
                        flush();
                    }
                    fclose($stream);
                } else {
                    while ($sent < $length && ! $stream->eof()) {
                        $buffer = $stream->read(min($chunk, $length - $sent));
                        if ($buffer === '') {
                            break;
                        }
                        echo $buffer;
                        $sent += strlen($buffer);
                        flush();
                    }
                }
            } catch (\Throwable) {
                // The client may have disconnected. The download is still counted.
            }
        }, $status, $headers);
    }

    private function accelPath(ResourcePackVersion $version, $adapter, int $start, int $length, array &$headers): ?string
    {
        if (nr_setting('download_mode', 'php') !== 'x-accel') {
            return null;
        }

        $path = $adapter->accelPath($version->storage_path);
        if ($path === null) {
            return null;
        }

        $prefix = rtrim((string) nr_setting('x_accel_prefix', '/_protected/respack'), '/');
        $headers['X-Accel-Redirect'] = $prefix.'/'.ltrim(str_replace('\\', '/', $version->storage_path), '/');

        return $headers['X-Accel-Redirect'];
    }

    /** @return array{0:int,1:int}|false|null */
    private function parseRange(string $header, int $size): array|false|null
    {
        if ($size <= 0) {
            return false;
        }

        if (str_contains($header, ',')) {
            // Multipart ranges are not required. Serving the whole file is safe.
            return null;
        }

        if (! preg_match('/^bytes=(\d*)-(\d*)$/i', $header, $m)) {
            return false;
        }

        $startRaw = $m[1];
        $endRaw = $m[2];

        if ($startRaw === '' && $endRaw === '') {
            return false;
        }

        if ($startRaw === '') {
            $suffix = (int) $endRaw;
            if ($suffix <= 0) {
                return false;
            }
            $start = max(0, $size - $suffix);
            $end = $size - 1;
        } else {
            $start = (int) $startRaw;
            $end = $endRaw === '' ? $size - 1 : (int) $endRaw;

            if ($start >= $size || $start > $end) {
                return false;
            }
            $end = min($end, $size - 1);
        }

        return [$start, $end];
    }

    private function etagMatches(string $header, string $etag): bool
    {
        if ($header === '') {
            return false;
        }

        if (trim($header) === '*') {
            return true;
        }

        foreach (explode(',', $header) as $candidate) {
            if (trim($candidate) === $etag || trim($candidate) === 'W/'.$etag) {
                return true;
            }
        }

        return false;
    }

    private function ifRangeAllows(Request $request, string $etag, $lastModified): bool
    {
        $ifRange = trim((string) $request->header('If-Range', ''));
        if ($ifRange === '') {
            return true;
        }

        if ($ifRange === $etag) {
            return true;
        }

        if ($lastModified) {
            $time = strtotime($ifRange);

            return $time !== false && $time === $lastModified->getTimestamp();
        }

        return false;
    }

    private function record(Request $request, ResourcePackVersion $version, int $status, string $rangeHeader, int $bytes, bool $isRange): void
    {
        if ($request->isMethod('HEAD')) {
            return;
        }

        DB::transaction(function () use ($request, $version, $status, $rangeHeader, $bytes, $isRange) {
            $version->increment('downloads_count');

            DownloadLog::create([
                'resource_pack_id' => $version->resource_pack_id,
                'resource_pack_version_id' => $version->id,
                'method' => $request->method(),
                'status_code' => $status,
                'range_header' => $rangeHeader !== '' ? mb_substr($rangeHeader, 0, 180) : null,
                'bytes_sent' => $bytes,
                'is_range' => $isRange,
                'ip_hash' => $this->ipHash($request),
            ]);
        });
    }

    private function ipHash(Request $request): ?string
    {
        if (! nr_setting_bool('download_ip_logging', true)) {
            return null;
        }

        return substr(hash('sha256', $request->ip().'|'.config('app.key')), 0, 40);
    }

    private function headerFilename(string $name): string
    {
        $name = basename(str_replace(['\\', '/', '"', "\r", "\n"], '', $name));
        $name = preg_replace('/[^\x20-\x7E]/', '_', $name);

        return $name !== '' ? $name : 'resource-pack.zip';
    }
}
