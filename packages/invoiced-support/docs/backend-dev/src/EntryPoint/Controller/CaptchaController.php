<?php

namespace App\EntryPoint\Controller;

use App\CustomerPortal\Libs\CustomerPortalRateLimiter;
use App\Core\Statsd\Interfaces\StatsdAwareInterface;
use App\Core\Statsd\StatsdAwareTrait;
use App\CustomerPortal\Libs\CustomerPortalSymfonyRateLimiter;
use ReCaptcha\ReCaptcha;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;
use Symfony\Component\Routing\Attribute\Route;

#[Route(schemes: '%app.protocol%', host: '%app.domain%')]
class CaptchaController extends AbstractController implements StatsdAwareInterface
{
    use StatsdAwareTrait;

    #[Route(path: '/captcha', name: 'ask_captcha', methods: ['GET'])]
    public function askCaptcha(bool $failed = false): Response
    {
        $this->statsd->increment('security.captcha_prompt');

        return $this->render('auth/captcha.twig', [
            'error' => $failed,
        ]);
    }

    #[Route(path: '/captcha', name: 'verify_captcha', methods: ['POST'], defaults: ['no_database_transaction' => true])]
    public function verifyCaptcha(Request $request, ReCaptcha $recaptcha, CustomerPortalRateLimiter $rateLimiter, CustomerPortalSymfonyRateLimiter $customerPortalSymfonyRateLimiter): Response
    {
        // csrf protection
        if (!$this->isCsrfTokenValid('captcha', (string) $request->request->get('_csrf_token'))) {
            throw new UnauthorizedHttpException('');
        }

        // verify recaptcha
        $captchaResp = (string) $request->request->get('g-recaptcha-response');
        if (!$captchaResp) {
            throw new NotFoundHttpException();
        }

        $resp = $recaptcha->verify($captchaResp, (string) $request->getClientIp());
        if (!$resp->isSuccess()) {
            $this->statsd->increment('security.captcha_failed');

            return $this->askCaptcha(true);
        }

        // record verification attempt
        $rateLimiter->verifiedCaptcha((string) $request->getClientIp());
        $customerPortalSymfonyRateLimiter->reset($request);

        $this->statsd->increment('security.captcha_verified');

        // successfully verified, now send the user back
        if ($url = $rateLimiter->decryptRedirectUrlParameter((string) $request->request->get('redirect'))) {
            return new RedirectResponse($url);
        }

        return new RedirectResponse('/');
    }
}
