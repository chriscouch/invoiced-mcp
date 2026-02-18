<?php

namespace App\EntryPoint\Controller\Api;

use App\AccountsPayable\Api\ListECheckApiRoute;
use App\AccountsPayable\Api\ResendECheckApiRoute;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route(host: '%app.api_domain%', name: 'api_')]
class ECheckApiController extends AbstractApiController
{
    #[Route(path: '/checks', name: 'list_e_checks', methods: ['GET'])]
    public function listAll(ListECheckApiRoute $route): Response
    {
        return $this->runRoute($route);
    }

    #[Route(path: '/checks/{model_id}', name: 'resend_e_check', methods: ['POST'])]
    public function resendECheck(ResendECheckApiRoute $route): Response
    {
        return $this->runRoute($route);
    }
}
