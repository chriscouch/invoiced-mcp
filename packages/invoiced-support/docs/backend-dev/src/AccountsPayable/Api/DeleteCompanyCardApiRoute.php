<?php

namespace App\AccountsPayable\Api;

use App\AccountsPayable\Models\CompanyCard;
use App\AccountsPayable\Operations\DeleteCompanyCard;
use App\Core\RestApi\Routes\AbstractModelApiRoute;
use App\Core\RestApi\ValueObjects\ApiCallContext;
use App\Core\RestApi\ValueObjects\ApiRouteDefinition;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * @extends AbstractModelApiRoute<CompanyCard>
 */
class DeleteCompanyCardApiRoute extends AbstractModelApiRoute
{
    public function __construct(private DeleteCompanyCard $delete)
    {
    }

    public function getDefinition(): ApiRouteDefinition
    {
        return new ApiRouteDefinition(
            queryParameters: [],
            requestParameters: [],
            requiredPermissions: ['settings.edit'],
            modelClass: CompanyCard::class,
            features: ['accounts_payable'],
        );
    }

    public function buildResponse(ApiCallContext $context): Response
    {
        $this->setModelId($context->request->attributes->get('model_id'));
        $card = $this->retrieveModel($context);

        try {
            $this->delete->delete($card);
        } catch (Throwable) {
            // ignore exceptions
        }

        return new Response('', 204);
    }
}
