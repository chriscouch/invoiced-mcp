<?php

use App\Kernel;
use Symfony\Component\Dotenv\Dotenv;
use Symfony\Component\ErrorHandler\Debug;
use Symfony\Component\HttpFoundation\Request;

require dirname(__DIR__).'/vendor/autoload.php';

if (!in_array($_SERVER['APP_ENV'] ?? $_ENV['APP_ENV'] ?? null, ['prod'])) {
    (new Dotenv())->usePutenv()->bootEnv(dirname(__DIR__).'/.env');
}

$_SERVER['APP_DEBUG'] = $_SERVER['APP_DEBUG'] ?? false;
if ($_SERVER['APP_DEBUG']) {
    umask(0000);

    Debug::enable();
}

$kernel = new Kernel($_SERVER['APP_ENV'], (bool) $_SERVER['APP_DEBUG']);
$request = Request::createFromGlobals();
// This allows the X-Forwarded-For header to set the IP address
// when it comes from an IP address on our VPC CIDR block.
Request::setTrustedProxies(
    // the IP address (or range) of your proxy
    ['10.0.0.0/16'],
    // trust the "X-Forwarded-*" headers
    Request::HEADER_X_FORWARDED_AWS_ELB
);
$response = $kernel->handle($request);
$response->send();
$kernel->terminate($request, $response);
