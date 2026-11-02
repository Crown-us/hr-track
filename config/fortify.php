<?php

return [
    'guard' => 'web',
    'middleware' => ['web'],
    'auth_middleware' => 'auth',
    'passwords' => 'users',
    'username' => 'email',
    'email' => 'email',
    'views' => true,
    'home' => '/hr',
    'prefix' => '',
    'domain' => null,
    'lowercase_usernames' => true,
    'limiters' => ['login' => 'login'],
    'redirects' => ['logout' => '/'],
    'features' => [],
];
