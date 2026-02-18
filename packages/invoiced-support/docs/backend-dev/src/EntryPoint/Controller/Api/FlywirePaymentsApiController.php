<?php

namespace App\EntryPoint\Controller\Api;

use App\Integrations\Adyen\Api\FlywirePaymentsEligibilityRoute;
use App\Integrations\Adyen\Api\RetrieveFlywirePaymentsAccountRoute;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route(name: 'api_', host: '%app.api_domain%')]
class FlywirePaymentsApiController extends AbstractApiController
{
    #[Route(path: '/flywire/eligibility', name: 'flywire_payments_eligibility', methods: ['GET'])]
    public function eligibility(FlywirePaymentsEligibilityRoute $route): Response
    {
        return $this->runRoute($route);
    }

    #[Route(path: '/flywire/account', name: 'retrieve_flywire_payments_account', methods: ['GET'])]
    public function retrieveAccount(RetrieveFlywirePaymentsAccountRoute $route): Response
    {
        return $this->runRoute($route);
    }
}
