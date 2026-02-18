<?php

namespace App\EntryPoint\Controller\CustomerPortal;

use App\Themes\Models\Template;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;

#[Route(
    name: 'customer_portal_',
    requirements: ['subdomain' => '^(?!api|tknz).*$'],
    host: '{subdomain}.%app.domain%',
    schemes: '%app.protocol%',
)]
class IndexCustomerPortalController extends AbstractCustomerPortalController
{
    #[Route(path: '/', name: 'index', methods: ['GET'])]
    public function index(): Response
    {
        $portal = $this->customerPortalContext->getOrFail();
        if ($portal->getSignedInCustomer()) {
            return $this->redirectToRoute(
                'customer_portal_account',
                [
                    'subdomain' => $portal->company()->getSubdomainUsername(),
                ]
            );
        }

        if (!$portal->enabled()) {
            throw new NotFoundHttpException();
        }

        return $this->redirectToRoute(
            'customer_portal_login_form',
            [
                'subdomain' => $portal->company()->getSubdomainUsername(),
            ]
        );
    }

    #[Route(path: '/_bootstrap', name: 'js_bootstrap', methods: ['GET'])]
    public function jsBootstrap(string $environment): Response
    {
        $portal = $this->customerPortalContext->getOrFail();
        $company = $portal->company();

        return new JsonResponse([
            'name' => $company->name,
            'email' => $company->email,
            'highlight_color' => $company->highlight_color,
            'payments_environment' => $environment,
            'payments_publishable_key' => $this->getParameter('app.payments_publishable_key'),
            'heap_project_id' => 'production' == $environment ? '4014677697' : '2962553331',
        ]);
    }

    #[Route(path: '/_css', name: 'custom_css', methods: ['GET'])]
    public function customCss(): Response
    {
        $content = Template::getContent('billing_portal/styles.css');

        return new Response($content, 200, [
            'Content-Type' => 'text/css',
        ]);
    }

    #[Route(path: '/_js', name: 'custom_js', methods: ['GET'])]
    public function customJs(): Response
    {
        $content = Template::getContent('billing_portal/index.js');

        return new Response($content, 200, [
            'Content-Type' => 'application/javascript',
        ]);
    }

    #[Route(path: '/robots.txt', name: 'robots_txt', methods: ['GET'])]
    public function robotsTxt(): Response
    {
        // customer portals are disallowed for robots
        return new Response("User-agent: *\nDisallow: /", 200, ['Content-Type' => 'text/plain']);
    }

    #[Route(path: '/faq', name: 'faq', methods: ['GET'])]
    public function faq(): Response
    {
        $portal = $this->customerPortalContext->getOrFail();

        return $this->render('customerPortal/content/faq.twig', [
            'address' => $portal->company()->address(true, true),
        ]);
    }

    #[Route(path: '/terms', name: 'terms', methods: ['GET'])]
    public function terms(): Response
    {
        return $this->render('customerPortal/content/terms.twig');
    }

    #[Route(path: '/privacy', name: 'privacy', methods: ['GET'])]
    public function privacy(): Response
    {
        return $this->render('customerPortal/content/privacy.twig');
    }
}
