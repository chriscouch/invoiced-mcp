<?php

namespace App\EntryPoint\Controller\Api;

use App\PaymentProcessing\Api\Stripe\LoadPkRoute;
use App\PaymentProcessing\Api\Stripe\SetupIntentRoute;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route(name: 'api_', host: '%app.api_domain%')]
class StripeApiController extends AbstractApiController
{
    #[Route(path: '/stripe/load_pk', name: 'load_pk', methods: ['POST'])]
    public function loadPk(LoadPkRoute $route): Response
    {
        return $this->runRoute($route);
    }

    #[Route(path: '/stripe/setup_intent', name: 'setup_intent', methods: ['POST'])]
    public function setupIntent(SetupIntentRoute $route): Response
    {
        return $this->runRoute($route);
    }
}