<?php

namespace App\AccountsReceivable\Api;

use App\AccountsReceivable\Exception\InvoiceGenerationException;
use App\AccountsReceivable\Models\Estimate;
use App\AccountsReceivable\Operations\GenerateEstimateInvoice;
use App\Core\RestApi\Exception\InvalidRequest;
use App\Core\RestApi\Routes\AbstractRetrieveModelApiRoute;
use App\Core\RestApi\ValueObjects\ApiCallContext;
use App\Core\RestApi\ValueObjects\ApiRouteDefinition;
use Symfony\Component\HttpFoundation\Response;

class GenerateInvoiceRoute extends AbstractRetrieveModelApiRoute
{
    public function __construct(private GenerateEstimateInvoice $generateEstimateInvoice)
    {
    }

    public function getDefinition(): ApiRouteDefinition
    {
        return new ApiRouteDefinition(
            queryParameters: null,
            requestParameters: null,
            requiredPermissions: ['invoices.create'],
            modelClass: Estimate::class,
            features: ['accounts_receivable'],
        );
    }

    public function buildResponse(ApiCallContext $context): mixed
    {
        /** @var Estimate $estimate */
        $estimate = parent::buildResponse($context);

        try {
            return $this->generateEstimateInvoice->generateInvoice($estimate);
        } catch (InvoiceGenerationException $e) {
            throw new InvalidRequest($e->getMessage());
        }
    }

    public function getSuccessfulResponse(): Response
    {
        return new Response('', 201);
    }
}
