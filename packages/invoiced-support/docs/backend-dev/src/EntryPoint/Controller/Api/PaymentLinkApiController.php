<?php

namespace App\EntryPoint\Controller\Api;

use App\AccountsReceivable\Api\CreatePaymentLinkRoute;
use App\AccountsReceivable\Api\DeletePaymentLinkRoute;
use App\AccountsReceivable\Api\EditPaymentLinkRoute;
use App\AccountsReceivable\Api\ListPaymentLinkSessionsRoute;
use App\AccountsReceivable\Api\ListPaymentLinksRoute;
use App\AccountsReceivable\Api\RetrievePaymentLinkRoute;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route(host: '%app.api_domain%', name: 'api_')]
class PaymentLinkApiController extends AbstractApiController
{
    #[Route(path: '/payment_links', name: 'list_payment_links', methods: ['GET'])]
    public function listAll(ListPaymentLinksRoute $route): Response
    {
        return $this->runRoute($route);
    }

    #[Route(path: '/payment_links', name: 'create_payment_link', methods: ['POST'])]
    public function create(CreatePaymentLinkRoute $route): Response
    {
        return $this->runRoute($route);
    }

    #[Route(path: '/payment_links/{model_id}', name: 'retrieve_payment_link', methods: ['GET'])]
    public function retrieve(RetrievePaymentLinkRoute $route): Response
    {
        return $this->runRoute($route);
    }

    #[Route(path: '/payment_links/{model_id}', name: 'edit_payment_link', methods: ['PATCH'])]
    public function edit(EditPaymentLinkRoute $route): Response
    {
        return $this->runRoute($route);
    }

    #[Route(path: '/payment_links/{model_id}', name: 'delete_payment_link', methods: ['DELETE'])]
    public function delete(DeletePaymentLinkRoute $route): Response
    {
        return $this->runRoute($route);
    }

    #[Route(path: '/payment_links/{payment_link_id}/sessions', name: 'list_payment_link_sessions', methods: ['GET'])]
    public function listSessions(ListPaymentLinkSessionsRoute $route): Response
    {
        return $this->runRoute($route);
    }
}
