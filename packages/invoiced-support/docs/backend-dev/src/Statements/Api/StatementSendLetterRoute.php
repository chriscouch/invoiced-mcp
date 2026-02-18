<?php

namespace App\Statements\Api;

use App\AccountsReceivable\Models\Customer;
use App\Core\RestApi\ValueObjects\ApiCallContext;
use App\Core\RestApi\ValueObjects\ApiRouteDefinition;
use App\Core\Multitenant\TenantContext;
use App\Sending\Mail\Api\SendLetterRoute;
use App\Sending\Mail\Libs\LetterSender;
use App\Statements\Libs\StatementBuilder;
use App\Statements\Traits\SendStatementTrait;

/**
 * API route to send letter statements.
 */
class StatementSendLetterRoute extends SendLetterRoute
{
    use SendStatementTrait;

    public function __construct(
        LetterSender $sender,
        StatementBuilder $builder,
        TenantContext $tenant
    ) {
        parent::__construct($tenant, $sender);

        $this->builder = $builder;
    }

    public function getDefinition(): ApiRouteDefinition
    {
        return new ApiRouteDefinition(
            queryParameters: null,
            requestParameters: null,
            requiredPermissions: ['letters.send'],
            modelClass: Customer::class,
            features: ['accounts_receivable', 'letters'],
        );
    }

    public function buildResponse(ApiCallContext $context): mixed
    {
        $this->parseRequestStatement($context);

        return parent::buildResponse($context);
    }
}
