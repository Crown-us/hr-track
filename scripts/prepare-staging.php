<?php

// CLI only. No credentials, environment contents, SQL, or exception traces are printed.
if (PHP_SAPI !== 'cli') {
    exit(1);
}
try {
    if (version_compare(PHP_VERSION, '8.3.36', '<')) {
        throw new RuntimeException;
    }
    $base = dirname(__DIR__);
    $local = $base.'/.local';
    $profile = $local.'/staging-secrets.json';
    $exists = is_file($profile);
    $secrets = $exists ? json_decode(file_get_contents($profile), true, 512, JSON_THROW_ON_ERROR) : ['profile' => 'hris-tunnel-staging-v1', 'database' => 'hris_lite_staging', 'db_password' => bin2hex(random_bytes(32)), 'app_key' => 'base64:'.base64_encode(random_bytes(32)), 'accounts' => []];
    if (($secrets['profile'] ?? '') !== 'hris-tunnel-staging-v1' || ($secrets['database'] ?? '') !== 'hris_lite_staging') {
        throw new RuntimeException;
    }
    foreach (['admin', 'hr_staff', 'viewer', 'supervisor', 'employee'] as $role) {
        $secrets['accounts'][$role] ??= ['email' => $role.'@hris.example.test', 'password' => bin2hex(random_bytes(16))];
    }
    $credentials = json_decode(file_get_contents($local.'/database-credentials.json'), true, 512, JSON_THROW_ON_ERROR);
    $candidates = [];
    $collect = function ($value, $path = '') use (&$collect, &$candidates) {
        if (is_array($value)) {
            foreach ($value as $key => $item) {
                $collect($item, $path.'.'.strtolower((string) $key));
            }
        } elseif (is_string($value) && str_contains($path, 'root') && (str_contains($path, 'pass') || str_ends_with($path, '.root'))) {
            $candidates[] = $value;
        }
    };
    $collect($credentials);
    $pdo = null;
    foreach (array_unique($candidates) as $password) {
        try {
            $pdo = new PDO('mysql:host=127.0.0.1;port=3307;charset=utf8mb4', 'root', $password, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
            break;
        } catch (Throwable) {
        }
    }
    if (! $pdo) {
        throw new RuntimeException;
    }
    $found = $pdo->query("SELECT SCHEMA_NAME FROM INFORMATION_SCHEMA.SCHEMATA WHERE SCHEMA_NAME='hris_lite_staging'")->fetchColumn();
    if ($found && ! $exists) {
        throw new RuntimeException;
    }
    $pdo->exec('CREATE DATABASE IF NOT EXISTS hris_lite_staging CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
    $pdo->exec("CREATE USER IF NOT EXISTS 'hris_staging'@'127.0.0.1' IDENTIFIED BY ".$pdo->quote($secrets['db_password']));
    $pdo->exec("GRANT ALL PRIVILEGES ON hris_lite_staging.* TO 'hris_staging'@'127.0.0.1'");
    $check = new PDO('mysql:host=127.0.0.1;port=3307;dbname=hris_lite_staging;charset=utf8mb4', 'hris_staging', $secrets['db_password'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    foreach (['hris_lite', 'hris_lite_test'] as $other) {
        $denied = false;
        try {
            $check->query('SELECT id FROM '.$other.'.users LIMIT 1');
        } catch (PDOException $e) {
            $denied = (int) ($e->errorInfo[1] ?? 0) === 1142 || (int) ($e->errorInfo[1] ?? 0) === 1044;
        }
        if (! $denied) {
            throw new RuntimeException;
        }
    }
    foreach (['storage/app/private', 'storage/app/public', 'storage/framework/views', 'storage/framework/sessions', 'storage/framework/cache/data', 'storage/framework/cache/locks', 'storage/logs', 'storage/fonts', 'cache'] as $directory) {
        $path = $local.'/staging/'.$directory;
        if (! is_dir($path) && ! mkdir($path, 0700, true) && ! is_dir($path)) {
            throw new RuntimeException;
        }
    }
    file_put_contents($profile, json_encode($secrets, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
    $environment = ['APP_NAME' => '"HRIS Lite"', 'APP_ENV' => 'staging', 'APP_KEY' => $secrets['app_key'], 'APP_DEBUG' => 'false', 'APP_URL' => 'https://kvndwi.my.id', 'APP_MAINTENANCE_DRIVER' => 'file', 'DB_CONNECTION' => 'mysql', 'DB_HOST' => '127.0.0.1', 'DB_PORT' => '3307', 'DB_DATABASE' => 'hris_lite_staging', 'DB_USERNAME' => 'hris_staging', 'DB_PASSWORD' => $secrets['db_password'], 'DB_URL' => '', 'SESSION_DRIVER' => 'database', 'SESSION_COOKIE' => 'hris_staging_session', 'SESSION_DOMAIN' => 'null', 'SESSION_SECURE_COOKIE' => 'true', 'SESSION_HTTP_ONLY' => 'true', 'SESSION_SAME_SITE' => 'lax', 'CACHE_STORE' => 'database', 'QUEUE_CONNECTION' => 'database', 'MAIL_MAILER' => 'log', 'MAIL_FROM_ADDRESS' => 'noreply@hris.example.test', 'MAIL_FROM_NAME' => '"HRIS Lite"', 'LOG_CHANNEL' => 'single', 'LOG_LEVEL' => 'warning', 'BROADCAST_CONNECTION' => 'null'];
    $lines = [];
    foreach ($environment as $key => $value) {
        $lines[] = $key.'='.$value;
    }
    file_put_contents($base.'/.env.staging', implode(PHP_EOL, $lines).PHP_EOL);
    echo "Staging profile ready; database isolation verified. Secrets saved privately, not printed.\n";
} catch (Throwable) {
    fwrite(STDERR, "Staging preparation stopped safely. Check local administrator credentials and existing profile; no reset attempted.\n");
    exit(1);
}
