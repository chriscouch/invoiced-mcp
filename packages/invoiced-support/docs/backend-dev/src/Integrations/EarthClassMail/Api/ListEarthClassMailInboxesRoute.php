<?php

namespace App\Integrations\EarthClassMail\Api;

use App\Core\RestApi\Exception\InvalidRequest;
use App\Core\RestApi\Routes\AbstractApiRoute;
use App\Core\RestApi\ValueObjects\ApiCallContext;
use App\Core\RestApi\ValueObjects\ApiRouteDefinition;
use App\Integrations\EarthClassMail\EarthClassMailClient;
use App\Integrations\EarthClassMail\Models\EarthClassMailAccount;
use App\Integrations\Exceptions\IntegrationApiException;

class ListEarthClassMailInboxesRoute extends AbstractApiRoute
{
    public function __construct(private EarthClassMailClient $client)
    {
    }

    public function getDefinition(): ApiRouteDefinition
    {
        return new ApiRouteDefinition(
            queryParameters: null,
            requestParameters: null,
            requiredPermissions: ['settings.edit'],
        );
    }

    public function buildResponse(ApiCallContext $context): mixed
    {
        $accountId = $context->request->request->get('account_id');

        try {
            $account = EarthClassMailAccount::find($accountId);
            if (!$account) {
                throw new InvalidRequest('Could not find Earth Class Mail account: '.$accountId, 404);
            }

            return $this->client->getInboxes($account);
        } catch (IntegrationApiException $e) {
            throw new InvalidRequest($e->getMessage());
        }
    }
}
