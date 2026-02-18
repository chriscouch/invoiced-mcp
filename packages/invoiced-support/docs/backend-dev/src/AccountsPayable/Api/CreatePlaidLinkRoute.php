<?php

namespace App\AccountsPayable\Api;

use App\CashApplication\Models\CashApplicationBankAccount;
use App\Core\RestApi\Exception\InvalidRequest;
use App\Core\RestApi\Routes\AbstractModelApiRoute;
use App\Core\RestApi\ValueObjects\ApiCallContext;
use App\Core\RestApi\ValueObjects\ApiRouteDefinition;
use App\Core\RestApi\ValueObjects\RequestParameter;
use App\Core\Multitenant\TenantContext;
use App\Integrations\Plaid\Libs\AddPlaidItem;
use App\Integrations\Plaid\Libs\PlaidApi;
use RuntimeException;
use stdClass;
use Throwable;

class CreatePlaidLinkRoute extends AbstractModelApiRoute
{
    public function __construct(
        private readonly PlaidApi $plaidClient,
        private readonly TenantContext $tenant,
        private readonly AddPlaidItem $addPlaidBankAccount,
    ) {
    }

    public function getDefinition(): ApiRouteDefinition
    {
        return new ApiRouteDefinition(
            queryParameters: [],
            requestParameters: [
                'token' => new RequestParameter(),
                'metadata' => new RequestParameter(),
            ],
            requiredPermissions: ['settings.edit'],
            modelClass: CashApplicationBankAccount::class,
        );
    }

    public function buildResponse(ApiCallContext $context): mixed
    {
        $token = $context->requestParameters['token'];
        $metadata = (array) $context->requestParameters['metadata'];

        try {
            $result = $this->plaidClient->exchangePublicToken($this->tenant->get(), (string) $token);
        } catch (Throwable $e) {
            throw new InvalidRequest('There was a problem exchanging the public token: '.$e->getMessage());
        }

        $accountLinks = [];
        foreach ($metadata['accounts'] as $account) {
            try {
                $accountLinks[] = $this->addPlaidBankAccount->saveAccount($account, $metadata, $result);
            } catch (RuntimeException $e) {
                $error = new stdClass();
                $error->message = $e->getMessage();
                $accountLinks[] = $error;
            }
        }

        return $accountLinks;
    }
}
