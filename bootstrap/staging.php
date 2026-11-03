<?php

use App\Support\StagingProfile;
use Illuminate\Foundation\Bootstrap\BootProviders;
use Illuminate\Foundation\Bootstrap\LoadConfiguration;
use Illuminate\Foundation\Vite;

$base = dirname(__DIR__);
require_once $base.'/vendor/autoload.php';
if (! is_file($base.'/.env.staging')) {
    throw new RuntimeException('Profil staging belum disiapkan.');
}
// These paths are forced before Laravel loads configuration, independently of development caches.
foreach (['APP_ENV' => 'staging', 'APP_CONFIG_CACHE' => $base.'/.local/staging/cache/config.php', 'APP_ROUTES_CACHE' => $base.'/.local/staging/cache/routes.php', 'APP_EVENTS_CACHE' => $base.'/.local/staging/cache/events.php'] as $key => $value) {
    putenv($key.'='.$value);
    $_ENV[$key] = $_SERVER[$key] = $value;
}
$app = require $base.'/bootstrap/app.php';
$app->useStoragePath($base.'/.local/staging/storage');
$app->loadEnvironmentFrom('.env.staging');
$app->afterBootstrapping(LoadConfiguration::class, fn ($app) => StagingProfile::assertSafe($app));
$app->afterBootstrapping(BootProviders::class, fn ($app) => $app->make(Vite::class)->useHotFile($app->storagePath('framework/staging-hot')));

return $app;
