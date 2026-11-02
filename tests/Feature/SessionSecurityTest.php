<?php

namespace Tests\Feature;

use Tests\TestCase;

class SessionSecurityTest extends TestCase
{
    public function test_missing_environment_defaults_to_secure_cookies_like_laravel_production(): void
    {
        $original = [];
        foreach (['APP_ENV', 'SESSION_SECURE_COOKIE'] as $key) {
            $original[$key] = [$_ENV[$key] ?? null, $_SERVER[$key] ?? null, getenv($key)];
            unset($_ENV[$key], $_SERVER[$key]);
            putenv($key);
        }

        try {
            $session = require config_path('session.php');
            $this->assertTrue($session['secure']);
        } finally {
            foreach ($original as $key => [$envValue, $serverValue, $processValue]) {
                if ($envValue !== null) {
                    $_ENV[$key] = $envValue;
                }
                if ($serverValue !== null) {
                    $_SERVER[$key] = $serverValue;
                }
                if ($processValue !== false) {
                    putenv($key.'='.$processValue);
                }
            }
        }
    }
}
