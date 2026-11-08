<?php

namespace App\Http\Controllers;

use App\Models\MinecraftPackFormat;
use App\Models\MinecraftPackFormatSyncLog;
use App\Models\MinecraftPackFormatVersion;
use App\Services\Minecraft\PackFormatLookup;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PackFormatController extends Controller
{
    public function index(Request $request): View
    {
        $q = trim((string) $request->query('q', ''));
        $type = (string) $request->query('type', '');
        $format = trim((string) $request->query('format', ''));

        $formats = MinecraftPackFormat::query()
            ->where('edition', PackFormatLookup::EDITION)
            ->when($format !== '', fn ($query) => $query->where('format_value', $format))
            ->when($q !== '', function ($query) use ($q) {
                $query->where(function ($w) use ($q) {
                    $w->where('format_value', 'like', '%'.$q.'%')
                        ->orWhereHas('versions', fn ($v) => $v->where('version_name', 'like', '%'.$q.'%'));
                });
            })
            ->when($type !== '', fn ($query) => $query->whereHas('versions', fn ($v) => $v->where('version_type', $type)))
            ->with(['versions' => function ($query) use ($q, $type) {
                if ($q !== '') {
                    $query->where('version_name', 'like', '%'.$q.'%');
                }
                if ($type !== '') {
                    $query->where('version_type', $type);
                }
                $query->orderByDesc('id');
            }])
            ->orderByDesc('format_major')
            ->orderByDesc('format_minor')
            ->paginate(20)
            ->withQueryString();

        $totalVersions = MinecraftPackFormatVersion::query()->count();
        $lastSync = MinecraftPackFormatSyncLog::query()->where('status', 'success')->latest('id')->first();
        $types = ['release', 'snapshot', 'prerelease', 'release_candidate', 'experimental'];

        return view('pack-formats.index', compact('formats', 'q', 'type', 'format', 'totalVersions', 'lastSync', 'types'));
    }

    public function show(string $format): View
    {
        $record = MinecraftPackFormat::query()
            ->where('edition', PackFormatLookup::EDITION)
            ->where('format_value', $format)
            ->first();

        abort_if($record === null, 404);

        $versions = $record->versions()
            ->orderByDesc('release_date')
            ->orderByDesc('id')
            ->get();

        $all = MinecraftPackFormat::query()
            ->where('edition', PackFormatLookup::EDITION)
            ->orderBy('format_major')
            ->orderBy('format_minor')
            ->orderBy('id')
            ->get(['id', 'format_value', 'format_major', 'format_minor']);

        $position = $all->search(fn (MinecraftPackFormat $item) => $item->is($record));

        return view('pack-formats.show', [
            'format' => $record,
            'versions' => $versions,
            'releaseCount' => $versions->where('version_type', 'release')->count(),
            'previous' => $position !== false && $position > 0 ? $all[$position - 1] : null,
            'next' => $position !== false && $position < $all->count() - 1 ? $all[$position + 1] : null,
        ]);
    }

    public function api(Request $request): JsonResponse
    {
        $versionQuery = trim((string) $request->query('version', ''));
        $formatQuery = trim((string) $request->query('format', ''));

        if ($versionQuery !== '') {
            $version = MinecraftPackFormatVersion::query()
                ->where('edition', PackFormatLookup::EDITION)
                ->where('version_name', $versionQuery)
                ->with('format')
                ->first();

            if (! $version) {
                return response()->json(['message' => 'Unknown Minecraft version.'], 404);
            }

            return response()->json([
                'data' => [
                    'version' => $version->version_name,
                    'type' => $version->version_type,
                    'release_date' => $version->release_date?->toDateString(),
                    'resource_pack_format' => $version->format?->format_value,
                    'format_major' => $version->format?->format_major,
                    'format_minor' => $version->format?->format_minor,
                ],
                'source' => 'https://minecraft.wiki/w/Pack_format',
                'updated_at' => $version->updated_at?->toIso8601String(),
            ]);
        }

        if ($formatQuery !== '') {
            $format = MinecraftPackFormat::query()->where('format_value', $formatQuery)->first();
            if (! $format) {
                return response()->json(['message' => 'Unknown resource pack format.'], 404);
            }

            $versions = $format->versions()->orderByDesc('id')->limit(100)->get(['version_name', 'version_type', 'release_date']);

            return response()->json([
                'data' => [
                    'resource_pack_format' => $format->format_value,
                    'format_major' => $format->format_major,
                    'format_minor' => $format->format_minor,
                    'versions' => $versions->map(fn ($v) => [
                        'version' => $v->version_name,
                        'type' => $v->version_type,
                        'release_date' => $v->release_date?->toDateString(),
                    ]),
                ],
                'source' => 'https://minecraft.wiki/w/Pack_format',
            ]);
        }

        $versions = MinecraftPackFormatVersion::query()
            ->with('format')
            ->orderByDesc('id')
            ->limit(100)
            ->get();

        return response()->json([
            'data' => $versions->map(fn ($v) => [
                'version' => $v->version_name,
                'type' => $v->version_type,
                'resource_pack_format' => $v->format?->format_value,
            ]),
            'source' => 'https://minecraft.wiki/w/Pack_format',
            'note' => 'Pass ?version=1.21.9 or ?format=69.0 for a focused lookup.',
        ]);
    }
}
