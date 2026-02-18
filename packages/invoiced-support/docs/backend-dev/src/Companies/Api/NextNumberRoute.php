<?php

namespace App\Companies\Api;

use App\Companies\Exception\NumberingException;
use App\Companies\Libs\NumberingSequence;
use App\Core\RestApi\Exception\ApiError;
use App\Core\RestApi\Exception\InvalidRequest;
use App\Core\RestApi\Routes\AbstractApiRoute;
use App\Core\RestApi\ValueObjects\ApiCallContext;
use App\Core\RestApi\ValueObjects\ApiRouteDefinition;
use App\Core\Multitenant\TenantContext;
use App\Core\Utils\Enums\ObjectType;
use Doctrine\DBAL\Connection;
use RuntimeException;
use Symfony\Component\Lock\LockFactory;

class NextNumberRoute extends AbstractApiRoute
{
    public function __construct(
        private TenantContext $tenant,
        private Connection $database,
        private LockFactory $lockFactory,
    ) {
    }

    public function getDefinition(): ApiRouteDefinition
    {
        return new ApiRouteDefinition(
            queryParameters: null,
            requestParameters: [],
            requiredPermissions: [],
        );
    }

    public function buildResponse(ApiCallContext $context): mixed
    {
        $type = (string) $context->request->attributes->get('model_id');
        $tenant = $this->tenant->get();

        try {
            $objectType = ObjectType::fromTypeName($type);
            $sequence = new NumberingSequence($tenant, $objectType, $this->lockFactory, $this->database);
        } catch (RuntimeException $e) {
            throw new InvalidRequest($e->getMessage());
        }

        try {
            $next = $sequence->nextNumber();
        } catch (NumberingException $e) {
            throw new ApiError($e->getMessage());
        }

        return [
            'next' => $sequence->applyTemplate($next),
            'next_no' => $next,
            'template' => $sequence->getModel()->template,
        ];
    }
}
