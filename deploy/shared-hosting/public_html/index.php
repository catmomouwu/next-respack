<?php

use Illuminate\Foundation\Application;
use Illuminate\Http\Request;

define('LARAVEL_START', microtime(true));

/*
|--------------------------------------------------------------------------
| Shared hosting front controller
|--------------------------------------------------------------------------
|
| The Next Respack application must live outside the public web root.
| This wrapper automatically locates the application directory, so the
| package works after extraction without editing any path.
|
| Expected layout:
|
|   /home/account/
|   ├── next-respack/     <- application (not web accessible)
|   └── public_html/      <- this file, .htaccess and build/
|
*/

$appPath = null;

$candidates = [
    dirname(__DIR__).'/next-respack',
    dirname(__DIR__, 2).'/next-respack',
];

foreach ($candidates as $candidate) {
    if (is_file($candidate.'/bootstrap/app.php') && is_file($candidate.'/vendor/autoload.php')) {
        $appPath = $candidate;
        break;
    }
}

if ($appPath === null) {
    // Last resort: scan sibling directories for an installed application.
    foreach (glob(dirname(__DIR__).'/*', GLOB_ONLYDIR) ?: [] as $directory) {
        if (is_file($directory.'/bootstrap/app.php') && is_file($directory.'/vendor/autoload.php')) {
            $appPath = $directory;
            break;
        }
    }
}

if ($appPath === null) {
    http_response_code(500);
    header('Content-Type: text/plain; charset=UTF-8');
    exit(
        "Next Respack application directory not found.\n".
        "找不到 Next Respack 应用目录。\n\n".
        "Expected / 期望的结构:\n".
        "  ".dirname(__DIR__)."/next-respack\n".
        "  ".dirname(__DIR__)."/public_html  (当前目录)\n\n".
        "请把压缩包解压到「包含 public_html 的那个目录」，\n".
        "使 next-respack/ 与 public_html/ 成为同级目录。\n".
        "Upload the whole package so that 'next-respack/' and 'public_html/' are siblings."
    );
}

$appPath = realpath($appPath) ?: $appPath;

if (file_exists($maintenance = $appPath.'/storage/framework/maintenance.php')) {
    require $maintenance;
}

require $appPath.'/vendor/autoload.php';

/** @var Application $app */
$app = require_once $appPath.'/bootstrap/app.php';

// Serve assets from the public directory while the application stays outside it.
$app->usePublicPath(__DIR__);

$app->handleRequest(Request::capture());
