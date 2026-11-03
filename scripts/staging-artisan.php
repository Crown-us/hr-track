<?php

use Symfony\Component\Console\Input\ArgvInput;

define('LARAVEL_START', microtime(true));
$app = require __DIR__.'/../bootstrap/staging.php';
exit($app->handleCommand(new ArgvInput));
