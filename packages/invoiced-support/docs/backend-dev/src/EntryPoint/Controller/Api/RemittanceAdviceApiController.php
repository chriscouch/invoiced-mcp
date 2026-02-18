<?php

namespace App\EntryPoint\Controller\Api;

use App\CashApplication\Api\ListRemittanceAdviceRoute;
use App\CashApplication\Api\PostRemittanceAdvicePaymentRoute;
use App\CashApplication\Api\ResolveRemittanceAdviceLineRoute;
use App\CashApplication\Api\RetrieveRemittanceAdviceRoute;
use App\CashApplication\Api\UploadRemittanceAdviceRoute;
use App\Core\Files\Api\ListAttachmentsRoute;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route(name: 'api_', host: '%app.api_domain%')]
class RemittanceAdviceApiController extends AbstractApiController
{
    #[Route(path: '/remittance_advice/upload', name: 'upload_remittance_advice', methods: ['POST'])]
    public function upload(UploadRemittanceAdviceRoute $route): Response
    {
        return $this->runRoute($route);
    }

    #[Route(path: '/remittance_advice', name: 'list_remittance_advice', methods: ['GET'])]
    public function listAll(ListRemittanceAdviceRoute $route): Response
    {
        return $this->runRoute($route);
    }

    #[Route(path: '/remittance_advice/{model_id}', name: 'retrieve_remittance_advice', methods: ['GET'])]
    public function retrieve(RetrieveRemittanceAdviceRoute $route): Response
    {
        return $this->runRoute($route);
    }

    #[Route(path: '/remittance_advice/{model_id}/payment', name: 'post_remittance_advice_payment', methods: ['POST'])]
    public function postPayment(PostRemittanceAdvicePaymentRoute $route): Response
    {
        return $this->runRoute($route);
    }

    #[Route(path: '/remittance_advice/{parent_id}/attachments', name: 'list_remittance_advice_attachments', defaults: ['parent_type' => 'remittance_advice'], methods: ['GET'])]
    public function listAttachments(ListAttachmentsRoute $route): Response
    {
        return $this->runRoute($route);
    }

    #[Route(path: '/remittance_advice/{remittance_advice_id}/lines/{model_id}/resolve', name: 'resolve_remittance_advice_line', methods: ['POST'])]
    public function resolveLine(ResolveRemittanceAdviceLineRoute $route): Response
    {
        return $this->runRoute($route);
    }
}
