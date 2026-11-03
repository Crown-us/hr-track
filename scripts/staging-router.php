<?php

use Illuminate\Http\Request;

define('LARAVEL_START', microtime(true));
$public = realpath(__DIR__.'/../public');
$path = rawurldecode(parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?? '/');
$file = realpath($public.$path);
$extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));
// Never delegate index.php to the normal entry point or serve a development storage symlink.
if ($file && str_starts_with($file, $public.DIRECTORY_SEPARATOR) && is_file($file)
    && ! str_starts_with($path, '/storage/') && $path !== '/storage'
    && in_array($extension, ['css', 'js', 'png', 'jpg', 'jpeg', 'gif', 'svg', 'ico', 'woff', 'woff2', 'ttf', 'webp', 'txt'], true)) {
    return false;
}
$app = require __DIR__.'/../bootstrap/staging.php';
$app->handleRequest(Request::capture());
