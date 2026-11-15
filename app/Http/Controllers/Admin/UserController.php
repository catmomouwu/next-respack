<?php

namespace App\Http\Controllers\Admin;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\DownloadLog;
use App\Models\ResourcePack;
use App\Models\ResourcePackVersion;
use App\Models\User;
use App\Services\Security\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class UserController extends Controller
{
    public function index(Request $request): View
    {
        $users = User::query()
            ->with('quota')
            ->when($request->filled('q'), function ($q) use ($request) {
                $term = '%'.trim((string) $request->q).'%';
                $q->where(fn ($w) => $w->where('username', 'like', $term)->orWhere('email', 'like', $term));
            })
            ->when($request->filled('role'), fn ($q) => $q->where('role', $request->role))
            ->when($request->filled('status'), fn ($q) => $q->where('is_active', $request->status === 'active'))
            ->latest()
            ->paginate(20)
            ->withQueryString();

        return view('admin.users.index', compact('users'));
    }

    public function show(User $user): View
    {
        $user->load('quota');
        $packs = ResourcePack::query()->where('user_id', $user->id)->with('currentVersion')->latest()->get();
        $downloads = DownloadLog::query()
            ->whereHas('pack', fn ($q) => $q->withoutTrashed()->where('user_id', $user->id))
            ->latest()
            ->limit(20)
            ->get();

        return view('admin.users.show', compact('user', 'packs', 'downloads'));
    }

    public function update(Request $request, User $user, AuditLogger $audit): RedirectResponse
    {
        $data = $request->validate([
            'role' => ['required', 'in:user,admin,super_admin'],
            'is_active' => ['required', 'boolean'],
            'storage_quota_mb' => ['required', 'integer', 'min:1', 'max:1048576'],
        ]);

        $role = UserRole::from($data['role']);

        if ($user->isSuperAdmin() && $role !== UserRole::SuperAdmin) {
            $superAdmins = User::query()->where('role', UserRole::SuperAdmin)->count();
            if ($superAdmins <= 1) {
                return back()->withErrors(['role' => __('You cannot demote the last super administrator.')]);
            }
        }

        if ($user->isSuperAdmin() && ! $data['is_active']) {
            $activeSupers = User::query()->where('role', UserRole::SuperAdmin)->where('is_active', true)->count();
            if ($activeSupers <= 1) {
                return back()->withErrors(['is_active' => __('You cannot disable the last active super administrator.')]);
            }
        }

        if (! $data['is_active'] && $user->id === $request->user()->id) {
            return back()->withErrors(['is_active' => __('You cannot disable your own account.')]);
        }

        $oldRole = $user->role;
        $user->role = $role;
        $user->is_active = (bool) $data['is_active'];
        $user->storage_quota = (int) $data['storage_quota_mb'] * 1024 * 1024;
        $user->save();

        $audit->log('admin.user_updated', $user, __('User account updated.'), [
            'role_from' => $oldRole->value,
            'role_to' => $role->value,
            'active' => $user->is_active,
        ]);

        return back()->with('status', __('User updated.'));
    }

    public function destroy(Request $request, User $user, AuditLogger $audit): RedirectResponse
    {
        if ($user->id === $request->user()->id) {
            return back()->withErrors(['user' => __('You cannot delete your own account here.')]);
        }

        if ($user->isSuperAdmin()) {
            $supers = User::query()->where('role', UserRole::SuperAdmin)->count();
            if ($supers <= 1) {
                return back()->withErrors(['user' => __('You cannot delete the last super administrator.')]);
            }
        }

        ResourcePack::query()->where('user_id', $user->id)->update(['status' => ResourcePack::STATUS_DISABLED]);
        $audit->log('admin.user_deleted', $user, __('User deleted and their resource packs disabled.'));
        $user->delete();

        return redirect()->route('admin.users.index')->with('status', __('User deleted.'));
    }
}
