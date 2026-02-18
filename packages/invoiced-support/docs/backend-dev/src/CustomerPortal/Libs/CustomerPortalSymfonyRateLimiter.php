<?php

namespace App\CustomerPortal\Libs;

use App\Core\Statsd\Interfaces\StatsdAwareInterface;
use App\Core\Statsd\StatsdAwareTrait;
use App\Core\Utils\AppUrl;
use Jaybizzle\CrawlerDetect\CrawlerDetect;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\RateLimiter\RateLimiterFactory;

class CustomerPortalSymfonyRateLimiter implements StatsdAwareInterface
{
    use StatsdAwareTrait;

    public function __construct(
        private readonly CustomerPortalRateLimiter $legacyLimiter,
        private readonly RateLimiterFactory $customerPortalInvoiceViewLimiter,
    ) {
    }

    public function shouldApplyCaptcha(Request $request): ?RedirectResponse
    {
        $crawlerDetect = new CrawlerDetect();

        $redirectUrl = $this->legacyLimiter->encryptRedirectUrlParameter($request->getUri());
        $redirect = new RedirectResponse(AppUrl::get()->build().'/captcha?r='.$redirectUrl);

        if ($crawlerDetect->isCrawler()) {
            $this->statsd->increment('security.rate_limit_customer_portal_crawler');

            return $redirect;
        }
        $limiter = $this->customerPortalInvoiceViewLimiter->create($request->getClientIp());
        if (!$limiter->consume()->isAccepted()) {
            $this->statsd->increment('security.rate_limit_customer_portal_document_view');

            return $redirect;
        }

        return null;
    }

    public function reset(Request $request): void
    {
        $limiter = $this->customerPortalInvoiceViewLimiter->create($request->getClientIp());
        $limiter->reset();
    }
}
