<?php

namespace App\CashApplication\Api;

use App\CashApplication\Models\Payment;
use App\Core\RestApi\Routes\AbstractEditModelApiRoute;
use App\Core\RestApi\ValueObjects\ApiCallContext;
use App\Core\RestApi\ValueObjects\ApiRouteDefinition;
use App\Core\RestApi\ValueObjects\QueryParameter;
use App\Core\RestApi\ValueObjects\RequestParameter;
use App\Integrations\AccountingSync\Traits\AccountingApiParametersTrait;

/**
 * @extends AbstractEditModelApiRoute<Payment>
 */
class EditPaymentRoute extends AbstractEditModelApiRoute
{
    use AccountingApiParametersTrait;

    public function getDefinition(): ApiRouteDefinition
    {
        return new ApiRouteDefinition(
            queryParameters: array_merge(
                $this->getBaseQueryParameters(),
                [
                    'reconcile' => new QueryParameter(
                        default: 0,
                    ),
                ],
            ),
            requestParameters: [
                'amount' => new RequestParameter(
                    types: ['numeric'],
                ),
                'currency' => new RequestParameter(
                    types: ['string', 'null'],
                ),
                'customer' => new RequestParameter(
                    types: ['integer', 'null'],
                ),
                'date' => new RequestParameter(
                    types: ['integer', 'null'],
                ),
                'method' => new RequestParameter(
                    types: ['string', 'null'],
                ),
                'reference' => new RequestParameter(
                    types: ['string', 'null'],
                ),
                'source' => new RequestParameter(
                    types: ['string', 'null'],
                ),
                'notes' => new RequestParameter(
                    types: ['string', 'null'],
                ),
                'ach_sender_id' => new RequestParameter(
                    types: ['string', 'null'],
                ),
                'attachments' => new RequestParameter(
                    types: ['array', 'null'],
                ),
                'applied_to' => new RequestParameter(
                    types: ['array', 'null'],
                ),
                'metadata' => new RequestParameter(
                    types: ['array', 'null'],
                ),
                'accounting_id' => new RequestParameter(
                    types: ['string', 'null'],
                ),
                'accounting_system' => new RequestParameter(
                    types: ['string', 'null'],
                ),
            ],
            requiredPermissions: ['payments.edit'],
            modelClass: Payment::class,
            features: ['accounts_receivable'],
        );
    }

    public function buildResponse(ApiCallContext $context): Payment
    {
        $userAgent = (string) $context->request->headers->get('User-Agent');
        if ($context->queryParameters['reconcile'] || preg_match('/netsuite/i', $userAgent)) {
            /** @var Payment $model */
            $model = $this->model;
            $model->skipReconciliation();
        }
        $this->parseRequestAccountingParameters($context);

        $payment = parent::buildResponse($context);
        $this->createAccountingMapping($payment);

        return $payment;
    }
}
