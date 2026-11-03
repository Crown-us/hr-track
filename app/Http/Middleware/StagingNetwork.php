<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class StagingNetwork
{
    public function handle(Request $request, Closure $next)
    {
        if (! app()->environment('staging')) {
            return $next($request);
        }
        $proxies = Request::getTrustedProxies();
        $headers = Request::getTrustedHeaderSet();
        $hosts = Request::getTrustedHosts();
        try {
            Request::setTrustedProxies(['127.0.0.1', '::1'], Request::HEADER_X_FORWARDED_FOR | Request::HEADER_X_FORWARDED_PROTO);
            Request::setTrustedHosts(['^kvndwi\\.my\\.id$', '^127\\.0\\.0\\.1$']);
            $request->getHost();

            return $next($request);
        } finally {
            Request::setTrustedProxies($proxies, $headers);
            Request::setTrustedHosts($hosts);
        }
    }
}
