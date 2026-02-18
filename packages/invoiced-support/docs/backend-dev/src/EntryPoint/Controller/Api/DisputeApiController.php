<?php

namespace App\EntryPoint\Controller\Api;

use App\PaymentProcessing\Api\Disputes\DisputeAcceptRoute;
use App\PaymentProcessing\Api\Disputes\DisputeDefendRoute;
use App\PaymentProcessing\Api\Disputes\DisputeReasonsRoute;
use App\PaymentProcessing\Api\Disputes\DisputeRemoveFileRoute;
use App\PaymentProcessing\Api\Disputes\DisputeUploadFilesRoute;
use App\PaymentProcessing\Api\Disputes\ListDisputesRoute;
use App\PaymentProcessing\Api\Disputes\RetrieveDisputeRoute;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route(name: 'api_', host: '%app.api_domain%')]
class DisputeApiController extends AbstractApiController
{
    #[Route(path: '/disputes', name: 'list_disputes', methods: ['GET'])]
    public function listAll(ListDisputesRoute $route): Response
    {
        return $this->runRoute($route);
    }

    #[Route(path: '/disputes/{model_id}', name: 'retrieve_dispute', methods: ['GET'])]
    public function retrieve(RetrieveDisputeRoute $route): Response
    {
        return $this->runRoute($route);
    }

    #[Route(path: '/disputes/{model_id}', name: 'defend_dispute', methods: ['POST'])]
    public function defendDispute(DisputeDefendRoute $route): Response
    {
        return $this->runRoute($route);
    }

    #[Route(path: '/disputes/{model_id}', name: 'accept_dispute', methods: ['DELETE'])]
    public function acceptDispute(DisputeAcceptRoute $route): Response
    {
        return $this->runRoute($route);
    }

    #[Route(path: '/disputes/{model_id}/files', name: 'add_dispute_files', methods: ['POST'])]
    public function uploadDisputeFile(DisputeUploadFilesRoute $route): Response
    {
        return $this->runRoute($route);
    }

    #[Route(path: '/disputes/{model_id}/files/{file_type}', name: 'remove_dispute_file', methods: ['DELETE'])]
    public function removeDisputeFile(DisputeRemoveFileRoute $route): Response
    {
        return $this->runRoute($route);
    }

    #[Route(path: '/disputes/{model_id}/reasons', name: 'get_dispute_reasons', methods: ['GET'])]
    public function disputeReasons(DisputeReasonsRoute $route): Response
    {
        return $this->runRoute($route);
    }
}
