<?php

use App\Kernel;

require_once dirname(__DIR__) . '/vendor/autoload_runtime.php';

// Définir le fuseau horaire global en UTC
date_default_timezone_set('UTC');

return function (array $context) {
    return new Kernel($context[ 'APP_ENV' ], (bool) $context[ 'APP_DEBUG' ]);
};