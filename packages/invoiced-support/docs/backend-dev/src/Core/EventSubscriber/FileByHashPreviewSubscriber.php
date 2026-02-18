<?php

namespace App\Core\EventSubscriber;

use App\Core\DomainRouter;
use App\Core\Libs\FileByHashFetchRateLimiter;
use App\Core\Statsd\Interfaces\StatsdAwareInterface;
use App\Core\Statsd\StatsdAwareTrait;
use App\Core\Utils\AppUrl;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Event\ResponseEvent;

class FileByHashPreviewSubscriber implements EventSubscriberInterface, StatsdAwareInterface
{
    use StatsdAwareTrait;

    public function __construct(private FileByHashFetchRateLimiter $rateLimiter) {}

    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();

        $domain = $request->attributes->get('domain');

        // run only when files.* domain is used and it is not auth
        if (DomainRouter::DOMAIN_FILES !== $domain || $this->isAuthenticated($request)) {
            return;
        }

        // check if allowed to access, and if not,
        // then require CAPTCHA verification to continue
        if ($this->rateLimiter->needsCaptchaVerification($request->getSession()->getId(), (string) $request->getClientIp()) && !$this->rateLimiter->isAcmeRequest($request)) {
            $redirectUrl = $this->rateLimiter->encryptRedirectUrlParameter($request->getUri());
            $response = new RedirectResponse(AppUrl::get()->build().'/captcha?r='.$redirectUrl);
            $event->setResponse($response);
        }
    }

    public function onKernelResponse(ResponseEvent $event): void
    {

    }

    public function onKernelException(ExceptionEvent $event): void
    {

    }

    private function isAuthenticated(Request $request): bool
    {
        // Authentication can be skipped if another authentication method
        // was used, like an OAuth access token.
        if ($request->attributes->get('skip_api_authentication')) {
            return false;
        }

        // let's assume that username and password are good, we are checking are those present
        $username = $request->getUser();
        $password = $request->getPassword();

        if (!empty($username) && !empty($password))
            return true;

        return false;
    }

    public static function getSubscribedEvents(): array
    {
        return [
            'kernel.request' => ['onKernelRequest', 35], // should be above router listener but after session authentication
            'kernel.response' => 'onKernelResponse',
            'kernel.exception' => ['onKernelException', -1], // should be after the exception listener logging
        ];
    }
}
