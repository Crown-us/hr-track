<?php

namespace App\Support;

use Illuminate\Foundation\Application;
use RuntimeException;

class StagingProfile
{
    public static function assertSafe(Application $app): void
    {
        $path = static fn (string $p) => rtrim(str_replace('\\', '/', $p), '/');
        $safe = $app->environment('staging') && config('app.debug') === false
            && config('app.url') === 'https://kvndwi.my.id'
            && config('database.default') === 'mysql'
            && config('database.connections.mysql.database') === 'hris_lite_staging'
            && config('database.connections.mysql.username') === 'hris_staging'
            && config('database.connections.mysql.host') === '127.0.0.1'
            && (string) config('database.connections.mysql.port') === '3307'
            && ! config('database.connections.mysql.url')
            && config('session.secure') === true && config('session.http_only') === true
            && config('session.same_site') === 'lax' && config('session.domain') === null
            && config('session.cookie') === 'hris_staging_session' && config('mail.default') === 'log'
            && $path($app->storagePath()) === $path($app->basePath('.local/staging/storage'));
        if (! $safe) {
            throw new RuntimeException('Profil staging tidak aman. Periksa konfigurasi lokal tanpa membuka akses publik.');
        }
    }
}
