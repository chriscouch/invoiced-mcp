<?php

namespace App\CashApplication\Api;

use App\CashApplication\Models\CashApplicationBankAccount;
use App\Core\RestApi\Routes\AbstractModelApiRoute;
use App\Core\RestApi\ValueObjects\ApiCallContext;
use App\Core\RestApi\ValueObjects\ApiRouteDefinition;
use App\Core\RestApi\ValueObjects\RequestParameter;
use App\Core\Queue\Queue;
use App\EntryPoint\QueueJob\PlaidTransactionJob;
use Symfony\Component\HttpFoundation\Response;

/**
 * @extends AbstractModelApiRoute<CashApplicationBankAccount>
 */
class GetCashApplicationBankAccountTransactionsRoute extends AbstractModelApiRoute
{
    public function __construct(private Queue $queue)
    {
    }

    public function getDefinition(): ApiRouteDefinition
    {
        return new ApiRouteDefinition(
            queryParameters: [],
            requestParameters: [
                'start_date' => new RequestParameter(
                    required: true,
                    types: ['string'],
                ),
                'end_date' => new RequestParameter(
                    required: true,
                    types: ['string'],
                ),
            ],
            requiredPermissions: ['settings.edit'],
            modelClass: CashApplicationBankAccount::class,
        );
    }

    public function buildResponse(ApiCallContext $context): Response
    {
        $this->setModelId($context->request->attributes->get('model_id'));

        /** @var CashApplicationBankAccount $bankAccount */
        $bankAccount = $this->retrieveModel($context);

        // Kick off a transaction loading job
        $this->queue->enqueue(PlaidTransactionJob::class, [
            'bank_account' => $bankAccount->id,
            'start_date' => $context->requestParameters['start_date'],
            'end_date' => $context->requestParameters['end_date'],
        ]);

        return new Response('', 204);
    }
}
