<?php

namespace App\EntryPoint\Controller\Api;

use App\Integrations\Flywire\Api\ListFlywireDisbursementsRoute;
use App\Integrations\Flywire\Api\ListFlywirePaymentsRoute;
use App\Integrations\Flywire\Api\ListFlywirePayoutsRoute;
use App\Integrations\Flywire\Api\ListFlywireRefundBundlesRoute;
use App\Integrations\Flywire\Api\ListFlywireRefundsRoute;
use App\Integrations\Flywire\Api\RetrieveFlywireDisbursementRoute;
use App\Integrations\Flywire\Api\RetrieveFlywirePaymentRoute;
use App\Integrations\Flywire\Api\RetrieveFlywireRefundBundleRoute;
use App\Integrations\Flywire\Api\RetrieveFlywireRefundRoute;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route(host: '%app.api_domain%', name: 'api_')]
class FlywireApiController extends AbstractApiController
{
    #[Route(path: '/flywire/disbursements', name: 'list_flywire_disbursements', methods: ['GET'])]
    public function listDisbursements(ListFlywireDisbursementsRoute $route): Response
    {
        return $this->runRoute($route);
    }

    #[Route(path: '/flywire/disbursements/{model_id}', name: 'retrieve_flywire_disbursements', methods: ['GET'])]
    public function retrieveDisbursement(RetrieveFlywireDisbursementRoute $route): Response
    {
        return $this->runRoute($route);
    }

    #[Route(path: '/flywire/disbursements/{id}/payouts', name: 'retrieve_flywire_disbursements_payouts', methods: ['GET'])]
    public function listDisbursementPayouts(ListFlywirePayoutsRoute $route, Request $request, string $id): Response
    {
        $filter = $request->query->all('filter');
        $filter['disbursement'] = $id;
        $request->query->set('filter', $filter);

        return $this->runRoute($route);
    }

    #[Route(path: '/flywire/payments', name: 'list_flywire_payments', methods: ['GET'])]
    public function listPayments(ListFlywirePaymentsRoute $route): Response
    {
        return $this->runRoute($route);
    }

    #[Route(path: '/flywire/refund_bundles', name: 'list_flywire_refund_bundles', methods: ['GET'])]
    public function listRefundBundles(ListFlywireRefundBundlesRoute $route): Response
    {
        return $this->runRoute($route);
    }

    #[Route(path: '/flywire/refund_bundles/{model_id}', name: 'retrieve_flywire_refund_bundle', methods: ['GET'])]
    public function retrieveRefundBundle(RetrieveFlywireRefundBundleRoute $route): Response
    {
        return $this->runRoute($route);
    }

    #[Route(path: '/flywire/payments/{model_id}', name: 'retrieve_flywire_payment', methods: ['GET'])]
    public function retrievePayment(RetrieveFlywirePaymentRoute $route): Response
    {
        return $this->runRoute($route);
    }

    #[Route(path: '/flywire/payments/{id}/payouts', name: 'retrieve_flywire_payments_payouts', methods: ['GET'])]
    public function listPaymentPayouts(ListFlywirePayoutsRoute $route, Request $request, string $id): Response
    {
        $filter = $request->query->all('filter');
        $filter['payment'] = $id;
        $request->query->set('filter', $filter);

        return $this->runRoute($route);
    }

    #[Route(path: '/flywire/refunds', name: 'list_flywire_refunds', methods: ['GET'])]
    public function listRefunds(ListFlywireRefundsRoute $route): Response
    {
        return $this->runRoute($route);
    }

    #[Route(path: '/flywire/refunds/{model_id}', name: 'retrieve_flywire_refund', methods: ['GET'])]
    public function retrieveRefund(RetrieveFlywireRefundRoute $route): Response
    {
        return $this->runRoute($route);
    }
}
