<?php

namespace App\Integrations\Plaid\Api;

use App\Core\RestApi\Exception\InvalidRequest;
use App\Core\RestApi\Routes\AbstractRetrieveModelApiRoute;
use App\Core\RestApi\ValueObjects\ApiCallContext;
use App\Core\RestApi\ValueObjects\ApiRouteDefinition;
use App\Integrations\Plaid\Libs\PlaidApi;
use App\Integrations\Plaid\Models\PlaidItem;
use Throwable;

/**
 * @extends AbstractRetrieveModelApiRoute<PlaidItem>
 */
class FinishVerifyPlaidLinkRoute extends AbstractRetrieveModelApiRoute
{
    public function __construct(
        private readonly PlaidApi $plaidClient
    ) {
    }

    public function getDefinition(): ApiRouteDefinition
    {
        return new ApiRouteDefinition(
            queryParameters: [],
            requestParameters: [],
            requiredPermissions: ['settings.edit'],
            modelClass: PlaidItem::class,
        );
    }

    public function buildResponse(ApiCallContext $context): PlaidItem
    {
        /** @var PlaidItem $plaidItem */
        $plaidItem = parent::buildResponse($context);
        try {
            $this->plaidClient->getAccount($plaidItem);
        } catch (Throwable $e) {
            throw new InvalidRequest('There was a problem exchanging the public token: '.$e->getMessage());
        }

        $plaidItem->verified = true;
        $plaidItem->saveOrFail();

        return $plaidItem;
    }
}
