<?php

use App\Http\Controllers\AccountController;
use App\Http\Controllers\Admin;
use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\CaptchaController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DownloadController;
use App\Http\Controllers\GuestUploadController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\Install\InstallerController;
use App\Http\Controllers\LocaleController;
use App\Http\Controllers\PackFormatController;
use App\Http\Controllers\ResourcePackController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Installer (only available before installation)
|--------------------------------------------------------------------------
*/
Route::prefix('install')->name('install.')->middleware(['install.not', 'throttle:install'])->group(function () {
    Route::get('/', [InstallerController::class, 'welcome'])->name('welcome');
    Route::get('/requirements', [InstallerController::class, 'requirements'])->name('requirements');
    Route::post('/requirements', [InstallerController::class, 'saveRequirements'])->name('requirements.save');
    Route::get('/database', [InstallerController::class, 'database'])->name('database');
    Route::post('/database/test', [InstallerController::class, 'testDatabase'])->name('database.test');
    Route::post('/database', [InstallerController::class, 'saveDatabase'])->name('database.save');
    Route::get('/storage', [InstallerController::class, 'storage'])->name('storage');
    Route::post('/storage/test', [InstallerController::class, 'testStorage'])->name('storage.test');
    Route::post('/storage', [InstallerController::class, 'saveStorage'])->name('storage.save');
    Route::get('/site', [InstallerController::class, 'site'])->name('site');
    Route::post('/site', [InstallerController::class, 'saveSite'])->name('site.save');
    Route::get('/admin', [InstallerController::class, 'admin'])->name('admin');
    Route::post('/admin', [InstallerController::class, 'saveAdmin'])->name('admin.save');
    Route::get('/confirm', [InstallerController::class, 'confirm'])->name('confirm');
    Route::post('/run', [InstallerController::class, 'run'])->name('run');
});

/*
|--------------------------------------------------------------------------
| Public pages
|--------------------------------------------------------------------------
*/
Route::get('/', HomeController::class)->name('home');
Route::get('/minecraft/pack-formats', [PackFormatController::class, 'index'])->name('pack-formats.index');
Route::get('/api/pack-formats', [PackFormatController::class, 'api'])->name('pack-formats.api');
Route::get('/locale/{locale}', LocaleController::class)->name('locale');
Route::match(['get', 'head'], '/dl/{token}', DownloadController::class)->where('token', '[A-Za-z0-9_\-]+')->name('download');

/*
|--------------------------------------------------------------------------
| Guest uploads (no account required, 200 MB and 7-day retention)
|--------------------------------------------------------------------------
*/
Route::get('/captcha', CaptchaController::class)->middleware('throttle:captcha')->name('captcha');
Route::get('/upload', [GuestUploadController::class, 'create'])->name('guest.upload');
Route::post('/upload', [GuestUploadController::class, 'store'])->middleware('throttle:guest-uploads')->name('guest.upload.store');
Route::get('/upload/{pack}', [GuestUploadController::class, 'show'])
    ->middleware('signed')
    ->where('pack', '[0-9a-fA-F\-]{36}')
    ->name('guest.upload.show');

/*
|--------------------------------------------------------------------------
| Authentication
|--------------------------------------------------------------------------
*/
Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:login')->name('login.store');
    Route::get('/register', [AuthController::class, 'showRegister'])->name('register');
    Route::post('/register', [AuthController::class, 'register'])->middleware('throttle:register')->name('register.store');
    Route::get('/forgot-password', [AuthController::class, 'showForgotPassword'])->name('password.request');
    Route::post('/forgot-password', [AuthController::class, 'sendResetLink'])->middleware('throttle:forgot-password')->name('password.email');
    Route::get('/reset-password/{token}', [AuthController::class, 'showResetForm'])->name('password.reset');
    Route::post('/reset-password', [AuthController::class, 'resetPassword'])->name('password.store');
});

Route::post('/logout', [AuthController::class, 'logout'])->middleware('auth')->name('logout');

Route::middleware('auth')->group(function () {
    Route::get('/email/verify', [AuthController::class, 'verificationNotice'])->name('verification.notice');
    Route::get('/email/verify/{id}/{hash}', [AuthController::class, 'verifyEmail'])
        ->middleware('signed')
        ->name('verification.verify');
    Route::post('/email/verification-notification', [AuthController::class, 'resendVerification'])
        ->middleware('throttle:6,1')
        ->name('verification.send');
});

/*
|--------------------------------------------------------------------------
| User dashboard
|--------------------------------------------------------------------------
*/
Route::middleware(['auth', 'active', 'verified.custom'])->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    Route::get('/packs', [ResourcePackController::class, 'index'])->name('packs.index');
    Route::get('/packs/create', [ResourcePackController::class, 'create'])->name('packs.create');
    Route::post('/packs', [ResourcePackController::class, 'store'])->middleware('throttle:uploads')->name('packs.store');
    Route::get('/packs/{pack}', [ResourcePackController::class, 'show'])->name('packs.show');
    Route::patch('/packs/{pack}', [ResourcePackController::class, 'update'])->name('packs.update');
    Route::post('/packs/{pack}/toggle', [ResourcePackController::class, 'toggleStatus'])->name('packs.toggle');
    Route::delete('/packs/{pack}', [ResourcePackController::class, 'destroy'])->name('packs.destroy');
    Route::get('/packs/{pack}/versions/create', [ResourcePackController::class, 'createVersion'])->name('packs.versions.create');
    Route::post('/packs/{pack}/versions', [ResourcePackController::class, 'storeVersion'])->middleware('throttle:uploads')->name('packs.versions.store');
    Route::post('/packs/{pack}/versions/{version}/toggle', [ResourcePackController::class, 'toggleVersion'])->name('packs.versions.toggle');
    Route::post('/packs/{pack}/versions/{version}/reset-token', [ResourcePackController::class, 'resetToken'])->name('packs.versions.reset-token');
    Route::delete('/packs/{pack}/versions/{version}', [ResourcePackController::class, 'destroyVersion'])->name('packs.versions.destroy');
    Route::get('/packs/{pack}/server.properties', [ResourcePackController::class, 'serverProperties'])->name('packs.server-properties');

    Route::get('/account', [AccountController::class, 'edit'])->name('account.edit');
    Route::patch('/account/profile', [AccountController::class, 'updateProfile'])->name('account.profile');
    Route::patch('/account/password', [AccountController::class, 'updatePassword'])->name('account.password');
});

/*
|--------------------------------------------------------------------------
| Administration
|--------------------------------------------------------------------------
*/
Route::prefix('admin')->name('admin.')->middleware(['auth', 'active', 'admin'])->group(function () {
    Route::get('/', Admin\DashboardController::class)->name('dashboard');

    Route::get('/users', [Admin\UserController::class, 'index'])->name('users.index');
    Route::get('/users/{user}', [Admin\UserController::class, 'show'])->name('users.show');
    Route::patch('/users/{user}', [Admin\UserController::class, 'update'])->name('users.update');
    Route::delete('/users/{user}', [Admin\UserController::class, 'destroy'])->name('users.destroy');

    Route::get('/packs', [Admin\ResourcePackController::class, 'index'])->name('packs.index');
    Route::post('/packs/{pack}/toggle', [Admin\ResourcePackController::class, 'toggleStatus'])->name('packs.toggle');
    Route::delete('/packs/{pack}', [Admin\ResourcePackController::class, 'destroy'])->name('packs.destroy');

    Route::get('/pack-formats', [Admin\PackFormatController::class, 'index'])->name('pack-formats.index');
    Route::post('/pack-formats/sync', [Admin\PackFormatController::class, 'sync'])->name('pack-formats.sync');
    Route::post('/pack-formats/import', [Admin\PackFormatController::class, 'import'])->name('pack-formats.import');
    Route::get('/pack-formats/history', [Admin\PackFormatController::class, 'history'])->name('pack-formats.history');
    Route::post('/pack-formats/history/{history}/rollback', [Admin\PackFormatController::class, 'rollback'])->name('pack-formats.rollback');
    Route::get('/pack-formats/versions/{version}/edit', [Admin\PackFormatController::class, 'edit'])->name('pack-formats.edit');
    Route::patch('/pack-formats/versions/{version}', [Admin\PackFormatController::class, 'update'])->name('pack-formats.update');
    Route::delete('/pack-formats/versions/{version}', [Admin\PackFormatController::class, 'destroy'])->name('pack-formats.destroy');

    Route::get('/settings', [Admin\SettingsController::class, 'edit'])->name('settings.edit');
    Route::patch('/settings', [Admin\SettingsController::class, 'update'])->name('settings.update');

    Route::get('/logs', [Admin\AuditLogController::class, 'index'])->name('logs.index');

    Route::middleware('superadmin')->group(function () {
        Route::get('/storage', [Admin\StorageController::class, 'index'])->name('storage.index');
        Route::post('/storage/test', [Admin\StorageController::class, 'test'])->name('storage.test');
        Route::post('/storage/plan', [Admin\StorageController::class, 'plan'])->name('storage.plan');
        Route::post('/storage/migrations/{migration}/run', [Admin\StorageController::class, 'run'])->name('storage.run');
        Route::post('/storage/orphans', [Admin\StorageController::class, 'cleanOrphans'])->name('storage.orphans');
    });
});
