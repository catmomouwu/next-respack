<?php

use Illuminate\Foundation\Application;
use Illuminate\Http\Request;

define('LARAVEL_START', microtime(true));

/*
|--------------------------------------------------------------------------
| Shared hosting front controller
|--------------------------------------------------------------------------
|
| The Next Respack application lives outside the public web root. Adjust
| $appPath if your directory layout is different. All public assets must be
| copied into this directory (for example build/).
|
*/

$appPath = __DIR__.'/../next-respack';

if (! is_dir($appPath)) {
    http_response_code(500);
    exit('Next Respack application directory not found.');
}

if (file_exists($maintenance = $appPath.'/storage/framework/maintenance.php')) {
    require $maintenance;
}

require $appPath.'/vendor/autoload.php';

/** @var Application $app */
$app = require_once $appPath.'/bootstrap/app.php';

// Serve assets from public_html while keeping the application outside it.
$app->usePublicPath(__DIR__);

$app->handleRequest(Request::capture());
