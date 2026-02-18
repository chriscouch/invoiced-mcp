<?php

namespace App\Statements\Api;

use App\AccountsReceivable\Models\Customer;
use App\Core\RestApi\ValueObjects\ApiCallContext;
use App\Core\RestApi\ValueObjects\ApiRouteDefinition;
use App\Core\Multitenant\TenantContext;
use App\Network\Api\SendNetworkDocumentApiRoute;
use App\Network\Command\SendDocument;
use App\Network\Models\NetworkDocument;
use App\Network\Models\NetworkQueuedSend;
use App\Statements\Libs\StatementBuilder;
use App\Statements\Traits\SendStatementTrait;

class StatementSendRoute extends SendNetworkDocumentApiRoute
{
    use SendStatementTrait;

    public function __construct(
        TenantContext $tenant,
        SendDocument $sendDocument,
        StatementBuilder $builder
    ) {
        parent::__construct($tenant, $sendDocument);
        $this->builder = $builder;
    }

    public function getDefinition(): ApiRouteDefinition
    {
        return new ApiRouteDefinition(
            queryParameters: null,
            requestParameters: $this->getStatementParameters(),
            requiredPermissions: [],
            modelClass: Customer::class,
            features: ['accounts_receivable', 'network'],
        );
    }

    public function buildResponse(ApiCallContext $context): NetworkDocument|NetworkQueuedSend
    {
        $this->parseRequestStatement($context);

        return parent::buildResponse($context);
    }
}
