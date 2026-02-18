<?php

namespace App\EntryPoint\Controller;

use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class RedirectController
{
    public function redirect(Request $request): RedirectResponse
    {
        $qs = $request->getQueryString();
        if ($qs) {
            $qs = '?'.$qs;
        }
        $url = 'https://www.invoiced.com'.$request->getPathInfo().$qs;

        return new RedirectResponse($url, 301);
    }

    #[Route(path: '/robots.txt', name: 'robots_txt', methods: ['GET'])]
    public function robotsTxt(string $environment): Response
    {
        // pages are not indexable in staging environments
        if (in_array($environment, ['dev', 'staging', 'sandbox', 'test'])) {
            return new Response("User-agent: *\nDisallow: /", 200, ['Content-Type' => 'text/plain']);
        }

        // all pages are allowed on the main domain
        return new Response("User-agent: *\nAllow: /", 200, ['Content-Type' => 'text/plain']);
    }
}
