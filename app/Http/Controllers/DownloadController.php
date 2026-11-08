<?php

namespace App\Http\Controllers;

use App\Services\Download\DownloadService;
use App\Services\Download\DownloadTokenService;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class DownloadController extends Controller
{
    public function __invoke(Request $request, string $token, DownloadTokenService $tokens, DownloadService $downloads): Response
    {
        $version = $tokens->find($token);

        if (! $version) {
            abort(404);
        }

        $pack = $version->pack;

        if (! $pack || ! $version->download_enabled || $pack->status !== \App\Models\ResourcePack::STATUS_ACTIVE) {
            abort(403, __('This download link is disabled.'));
        }

        if ($pack->isExpired()) {
            abort(410, __('This guest upload has expired and was deleted.'));
        }

        return $downloads->respond($request, $version);
    }
}
