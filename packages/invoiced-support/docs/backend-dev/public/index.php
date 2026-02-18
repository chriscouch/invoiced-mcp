<?php

use App\Kernel;
use Symfony\Component\HttpFoundation\Request;

@include 'assets/version.php';
$_SERVER['INVOICED_VERSION'] = $_ENV['INVOICED_VERSION'] = defined('INVOICED_VERSION') ? INVOICED_VERSION : '';

require_once dirname(__DIR__).'/vendor/autoload_runtime.php';

// Trust ngrok proxy in dev environment when enabled
if ('dev' == getenv('APP_ENV') && isset($_ENV['NGROK_DOMAIN']) && $_ENV['NGROK_DOMAIN']) {
    Request::setTrustedProxies(['0.0.0.0/0'], Request::HEADER_X_FORWARDED_FOR & Request::HEADER_X_FORWARDED_HOST & Request::HEADER_X_FORWARDED_PROTO & Request::HEADER_X_FORWARDED_PORT & Request::HEADER_X_FORWARDED_PREFIX);
}

if ($trustedProxies = $_SERVER['TRUSTED_PROXIES'] ?? false) {
    Request::setTrustedProxies(explode(',', $trustedProxies), Request::HEADER_X_FORWARDED_AWS_ELB);
}

if ($trustedHosts = $_SERVER['TRUSTED_HOSTS'] ?? false) {
    Request::setTrustedHosts([$trustedHosts]);
}

return function (array $context) {
    return new Kernel($context['APP_ENV'], (bool) $context['APP_DEBUG']);
};
