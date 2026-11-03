<?php

namespace Tests\Feature;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class StagingProxyTest extends TestCase
{
    public function test_loopback_connector_preserves_https_but_remote_clients_cannot_spoof_it(): void
    {
        $this->app->instance('env', 'staging');
        Route::get('/_test/proxy', fn (Request $r) => ['secure' => $r->isSecure(), 'ip' => $r->ip()]);
        $this->withServerVariables(['REMOTE_ADDR' => '127.0.0.1'])->withHeaders(['Host' => 'kvndwi.my.id', 'X-Forwarded-Proto' => 'https', 'X-Forwarded-For' => '198.51.100.20'])->get('http://kvndwi.my.id/_test/proxy')->assertOk()->assertJson(['secure' => true, 'ip' => '198.51.100.20']);
        $this->withServerVariables(['REMOTE_ADDR' => '192.0.2.20'])->get('http://kvndwi.my.id/_test/proxy')->assertOk()->assertJson(['secure' => false, 'ip' => '192.0.2.20']);
    }
}
