<?php

namespace App\PaymentProcessing\Api;

use App\AccountsReceivable\Models\Customer;
use App\Core\RestApi\Routes\AbstractListModelsApiRoute;
use App\Core\RestApi\ValueObjects\ApiCallContext;
use App\Core\RestApi\ValueObjects\ApiRouteDefinition;
use App\Core\RestApi\ValueObjects\QueryParameter;
use App\PaymentProcessing\Models\PaymentSource;
use Symfony\Component\HttpFoundation\Response;

/**
 * @extends AbstractListModelsApiRoute<Customer>
 */
class ListPaymentSourcesRoute extends AbstractListModelsApiRoute
{
    public function getDefinition(): ApiRouteDefinition
    {
        return new ApiRouteDefinition(
            queryParameters: array_merge(
                $this->getBaseQueryParameters(),
                [
                    'include_hidden' => new QueryParameter(
                        default: false,
                    ),
                ],
            ),
            requestParameters: [],
            requiredPermissions: [],
            modelClass: Customer::class,
            features: ['accounts_receivable'],
        );
    }

    /**
     * @return PaymentSource[]
     */
    public function buildResponse(ApiCallContext $context): array
    {
        $this->parseListParameters($context->request);

        $this->setModelId($context->request->attributes->get('model_id'));

        $customer = $this->retrieveModel($context);

        $sources = $customer->paymentSources((bool) $context->queryParameters['include_hidden']);

        $this->response = new Response();
        $this->paginate($context, $this->response, $this->page, $this->perPage, null, $sources, count($sources));

        return $sources;
    }
}
