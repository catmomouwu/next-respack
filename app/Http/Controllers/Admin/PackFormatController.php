<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\MinecraftPackFormat;
use App\Models\MinecraftPackFormatHistory;
use App\Models\MinecraftPackFormatSyncLog;
use App\Models\MinecraftPackFormatVersion;
use App\Models\SystemSetting;
use App\Services\Minecraft\PackFormatLookup;
use App\Services\Minecraft\PackFormatSyncService;
use App\Services\Security\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class PackFormatController extends Controller
{
    public function index(Request $request, PackFormatSyncService $sync): View
    {
        $logs = MinecraftPackFormatSyncLog::query()->latest('id')->paginate(15, ['*'], 'logs_page')->withQueryString();

        $history = MinecraftPackFormatHistory::query()->latest('id')->limit(10)->get();

        $stats = [
            'formats' => MinecraftPackFormat::query()->count(),
            'versions' => MinecraftPackFormatVersion::query()->count(),
            'releases' => MinecraftPackFormatVersion::query()->where('version_type', 'release')->count(),
            'snapshots' => MinecraftPackFormatVersion::query()->where('version_type', 'snapshot')->count(),
            'manual' => MinecraftPackFormatVersion::query()->where('is_manual', true)->count(),
        ];

        $datasetVersion = $sync->currentDatasetVersion();
        $lastSync = $sync->lastSuccessfulSync();
        $isDue = $sync->isDue();

        $recent = MinecraftPackFormatVersion::query()
            ->with('format')
            ->orderByDesc('id')
            ->limit(25)
            ->get();

        return view('admin.pack-formats.index', compact('logs', 'history', 'stats', 'datasetVersion', 'lastSync', 'isDue', 'recent'));
    }

    public function sync(PackFormatSyncService $sync, AuditLogger $audit): RedirectResponse
    {
        $log = $sync->syncFromWiki('manual');

        if ($log->status === 'success') {
            $audit->log('admin.pack_formats_synced', $log, __('Pack format data synchronized.'), [
                'added' => $log->records_added,
                'updated' => $log->records_updated,
                'removed' => $log->records_removed,
            ]);

            return back()->with('status', __('Synchronization complete: :added added, :updated updated, :removed removed.', [
                'added' => $log->records_added,
                'updated' => $log->records_updated,
                'removed' => $log->records_removed,
            ]));
        }

        return back()->withErrors(['sync' => __('Synchronization failed: :message', ['message' => $log->error ?? __('unknown error')])]);
    }

    public function import(Request $request, PackFormatSyncService $sync, AuditLogger $audit): RedirectResponse
    {
        $request->validate([
            'format_type' => ['required', 'in:json,csv'],
            'payload' => ['nullable', 'string'],
            'file' => ['nullable', 'file', 'max:2048'],
        ]);

        $type = (string) $request->input('format_type');
        $payload = (string) $request->input('payload', '');

        if ($request->hasFile('file')) {
            $payload = (string) file_get_contents($request->file('file')->getRealPath());
        }

        if (trim($payload) === '') {
            return back()->withErrors(['payload' => __('Provide data to import (paste it or upload a file).')]);
        }

        $log = $type === 'json'
            ? $sync->importJson($payload, 'import-json')
            : $sync->importCsv($payload, 'import-csv');

        if ($log->status === 'success') {
            $audit->log('admin.pack_formats_imported', $log, __('Pack format data imported.'), ['type' => $type]);

            return back()->with('status', __('Import complete. :added records added, :updated updated.', [
                'added' => $log->records_added,
                'updated' => $log->records_updated,
            ]));
        }

        return back()->withErrors(['payload' => __('Import failed: :message', ['message' => $log->error ?? __('unknown error')])]);
    }

    public function history(PackFormatSyncService $sync): View
    {
        $history = MinecraftPackFormatHistory::query()
            ->with('syncLog')
            ->latest('id')
            ->paginate(30);

        $live = $sync->snapshot();

        return view('admin.pack-formats.history', compact('history', 'live'));
    }

    public function rollback(MinecraftPackFormatHistory $history, PackFormatSyncService $sync, AuditLogger $audit): RedirectResponse
    {
        $log = $sync->rollback($history);

        if ($log->status === 'success') {
            $audit->log('admin.pack_formats_rolled_back', $history, __('Pack format data rolled back to a previous snapshot.'), ['history_id' => $history->id]);

            return redirect()->route('admin.pack-formats.index')->with('status', __('Rollback completed. The selected snapshot is now live.'));
        }

        return back()->withErrors(['rollback' => __('Rollback failed: :message', ['message' => $log->error ?? __('unknown error')])]);
    }

    public function edit(MinecraftPackFormatVersion $version): View
    {
        $version->load('format');
        $versions = MinecraftPackFormatVersion::query()->where('is_manual', true)->with('format')->latest()->limit(50)->get();

        return view('admin.pack-formats.edit', compact('version', 'versions'));
    }

    public function update(Request $request, MinecraftPackFormatVersion $version, AuditLogger $audit): RedirectResponse
    {
        $data = $request->validate([
            'version_name' => ['required', 'string', 'max:191'],
            'version_type' => ['required', 'in:release,snapshot,prerelease,release_candidate,experimental'],
            'format_value' => ['required', 'regex:/^\d+(\.\d+)?$/'],
            'release_date' => ['nullable', 'date'],
        ]);

        $normalized = $this->normalizeFormat($data['format_value']);
        if ($normalized === null) {
            return back()->withInput()->withErrors(['format_value' => __('Enter a format number such as 69 or 69.1.')]);
        }

        [$major, $minor] = $normalized;

        DB::transaction(function () use ($version, $data, $major, $minor) {
            $key = MinecraftPackFormat::makeKey(PackFormatLookup::EDITION, $major, $minor);
            $format = MinecraftPackFormat::query()->firstOrCreate(
                ['format_key' => $key],
                [
                    'edition' => PackFormatLookup::EDITION,
                    'format_major' => $major,
                    'format_minor' => $minor,
                    'format_value' => $data['format_value'],
                    'source_url' => config('nextrespack.pack_format_source_url'),
                    'is_manual' => true,
                ]
            );

            $format->format_value = $data['format_value'];
            $format->is_manual = true;
            $format->save();

            $type = $data['version_type'];
            $version->fill([
                'minecraft_pack_format_id' => $format->id,
                'version_name' => $data['version_name'],
                'version_type' => $type,
                'is_snapshot' => $type === 'snapshot',
                'is_prerelease' => $type === 'prerelease',
                'is_release_candidate' => $type === 'release_candidate',
                'release_date' => $data['release_date'] ?? null,
                'is_manual' => true,
            ])->save();
        });

        $audit->log('admin.pack_format_manually_edited', $version, __('Pack format record manually corrected.'), $data);

        return back()->with('status', __('Pack format record saved as a manual correction.'));
    }

    public function destroy(MinecraftPackFormatVersion $version, AuditLogger $audit): RedirectResponse
    {
        if (! $version->is_manual) {
            return back()->withErrors(['version' => __('Only manual records can be deleted here. Synchronized records are managed by the data source.')]);
        }

        $audit->log('admin.pack_format_manual_deleted', $version, __('Manual pack format record deleted.'));
        $version->delete();

        return redirect()->route('admin.pack-formats.index')->with('status', __('Manual record deleted. The next synchronization will restore official data if the version exists upstream.'));
    }

    /** @return array{0:int,1:?int}|null */
    private function normalizeFormat(string $value): ?array
    {
        if (! preg_match('/^(\d+)(?:\.(\d+))?$/', trim($value), $m)) {
            return null;
        }

        return [(int) $m[1], isset($m[2]) ? (int) $m[2] : null];
    }
}
