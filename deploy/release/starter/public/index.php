<?php

/*
 * NPO.WorkDash web entry point for the release layout. This folder is the web
 * root: either npo-workdash/public itself, or its contents copied into a fixed
 * public_html. No application code, .env or storage lives here.
 */

use Illuminate\Foundation\Application;
use Illuminate\Http\Request;

define('LARAVEL_START', microtime(true));

$root = require __DIR__.'/workdash-path.php';

foreach ([$root, __DIR__.'/../npo-workdash', dirname(__DIR__, 2).'/npo-workdash'] as $candidate) {
    if (is_string($candidate) && is_file($candidate.'/current.php')) {
        $root = $candidate;
        break;
    }
}

if (! is_string($root) || ! is_file($root.'/current.php')) {
    http_response_code(500);
    header('Content-Type: text/plain');
    exit('NPO.WorkDash cannot find its application folder. Edit workdash-path.php in the web folder so it returns the full path of the npo-workdash folder.');
}

$release = require $root.'/current.php';

if (file_exists($maintenance = $root.'/shared/storage/framework/maintenance.php')) {
    require $maintenance;
}

require $release.'/vendor/autoload.php';

/** @var Application $app */
$app = require_once $release.'/bootstrap/app.php';

$app->usePublicPath(__DIR__);

$app->handleRequest(Request::capture());
