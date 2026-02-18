<?php

namespace App\AccountsPayable\Api;

use App\AccountsPayable\Models\PayableDocumentResolution;
use App\Core\RestApi\Libs\ApiCache;
use App\Core\RestApi\Routes\AbstractListModelsApiRoute;
use App\Core\RestApi\ValueObjects\ApiCallContext;
use App\Core\RestApi\ValueObjects\ApiRouteDefinition;
use App\Core\RestApi\ValueObjects\ListFilter;

/**
 * @template T
 *
 * @extends AbstractListModelsApiRoute<T>
 */
abstract class AbstractListVendorDocumentApprovalsApiRoute extends AbstractListModelsApiRoute
{
    /**
     * @param class-string<PayableDocumentResolution> $modelClass
     */
    public function __construct(
        private readonly string $modelClass,
        private readonly string $filteredProperty,
        ApiCache $apiCache,
    ) {
        parent::__construct($apiCache);
    }

    public function getDefinition(): ApiRouteDefinition
    {
        return new ApiRouteDefinition(
            queryParameters: $this->getBaseQueryParameters(),
            requestParameters: [],
            requiredPermissions: [],
            modelClass: $this->modelClass,
            filterableProperties: [$this->filteredProperty],
            features: ['accounts_payable'],
        );
    }

    public function parseFilterInput(ApiCallContext $context, array $input): ListFilter
    {
        $key = $this->filteredProperty;

        return parent::parseFilterInput($context, [
            $key => $context->request->attributes->get($key),
        ]);
    }
}
