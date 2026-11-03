<?php

namespace Tests\Unit;

use App\Support\StagingProfile;
use Tests\TestCase;

class StagingProfileTest extends TestCase
{
    private function profile(): void
    {
        $this->app->instance('env', 'staging');
        $this->app->useStoragePath(base_path('.local/staging/storage'));
        config(['app.debug' => false, 'app.url' => 'https://kvndwi.my.id', 'database.default' => 'mysql', 'database.connections.mysql.database' => 'hris_lite_staging', 'database.connections.mysql.username' => 'hris_staging', 'database.connections.mysql.host' => '127.0.0.1', 'database.connections.mysql.port' => '3307', 'database.connections.mysql.url' => null, 'session.secure' => true, 'session.http_only' => true, 'session.same_site' => 'lax', 'session.domain' => null, 'session.cookie' => 'hris_staging_session', 'mail.default' => 'log']);
    }

    public function test_safe_profile_is_accepted_without_database_access(): void
    {
        $this->profile();
        StagingProfile::assertSafe($this->app);
        $this->addToAssertionCount(1);
    }

    public function test_development_database_debug_cookie_or_external_mail_fail_closed(): void
    {
        foreach (['database.connections.mysql.database' => 'hris_lite', 'database.connections.mysql.username' => 'root', 'app.debug' => true, 'session.secure' => false, 'session.domain' => '.kvndwi.my.id', 'mail.default' => 'smtp', 'database.connections.mysql.url' => 'mysql://unsafe'] as $key => $value) {
            $this->profile();
            config([$key => $value]);
            try {
                StagingProfile::assertSafe($this->app);
                $this->fail('Unsafe profile accepted: '.$key);
            } catch (\RuntimeException $e) {
                $this->assertSame('Profil staging tidak aman. Periksa konfigurasi lokal tanpa membuka akses publik.', $e->getMessage());
            }
        }
    }

    public function test_wrong_storage_and_environment_are_rejected(): void
    {
        $this->profile();
        $this->app->useStoragePath(base_path('storage'));
        $this->expectException(\RuntimeException::class);
        StagingProfile::assertSafe($this->app);
    }
}
