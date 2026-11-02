<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class PrivateAuthResponse
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);
        if ($request->is('login', 'register', 'hr', 'user/*', 'employees', 'employees/*', 'vacancies', 'vacancies/*', 'applications', 'applications/*', 'interviews', 'interviews/*', 'karier/*', 'trainings', 'trainings/*')) {
            $response->headers->set('Cache-Control', 'no-store, private');
            $response->headers->set('Referrer-Policy', 'no-referrer');
        }

        return $response;
    }
}
