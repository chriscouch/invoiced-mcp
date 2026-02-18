<?php

namespace App\Statements\Api;

use App\AccountsReceivable\Models\Customer;
use App\Core\RestApi\ValueObjects\ApiCallContext;
use App\Core\RestApi\ValueObjects\ApiRouteDefinition;
use App\Sending\Sms\Api\SendTextMessageRoute;
use App\Sending\Sms\Libs\TextMessageSender;
use App\Statements\Libs\StatementBuilder;
use App\Statements\Traits\SendStatementTrait;

/**
 * API route to send letter statements.
 */
class StatementSendTextMessageRoute extends SendTextMessageRoute
{
    use SendStatementTrait;

    public function __construct(
        StatementBuilder $builder,
        TextMessageSender $sender
    ) {
        parent::__construct($sender);
        $this->builder = $builder;
    }

    public function getDefinition(): ApiRouteDefinition
    {
        return new ApiRouteDefinition(
            queryParameters: null,
            requestParameters: null,
            requiredPermissions: ['text_messages.send'],
            modelClass: Customer::class,
            features: ['accounts_receivable', 'sms'],
        );
    }

    public function buildResponse(ApiCallContext $context): mixed
    {
        $this->parseRequestStatement($context);

        return parent::buildResponse($context);
    }
}
