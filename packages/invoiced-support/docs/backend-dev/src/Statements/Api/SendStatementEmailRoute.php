<?php

namespace App\Statements\Api;

use App\AccountsReceivable\Models\Customer;
use App\Core\RestApi\ValueObjects\ApiCallContext;
use App\Core\RestApi\ValueObjects\ApiRouteDefinition;
use App\Core\Mailer\EmailBlockList;
use App\Core\Multitenant\TenantContext;
use App\Sending\Email\Api\SendDocumentEmailRoute;
use App\Sending\Email\EmailFactory\DocumentEmailFactory;
use App\Sending\Email\Libs\EmailSpool;
use App\Statements\Libs\StatementBuilder;
use App\Statements\Traits\SendStatementTrait;

/**
 * API route to send email statements.
 */
class SendStatementEmailRoute extends SendDocumentEmailRoute
{
    use SendStatementTrait;

    public function __construct(
        TenantContext $tenant,
        DocumentEmailFactory $factory,
        EmailSpool $emailSpool,
        EmailBlockList $blockList,
        StatementBuilder $builder
    ) {
        parent::__construct($tenant, $factory, $emailSpool, $blockList);
        $this->builder = $builder;
    }

    public function getDefinition(): ApiRouteDefinition
    {
        return new ApiRouteDefinition(
            queryParameters: null,
            requestParameters: null,
            requiredPermissions: ['emails.send'],
            modelClass: Customer::class,
            features: ['accounts_receivable', 'email_sending'],
        );
    }

    public function buildResponse(ApiCallContext $context): array
    {
        $this->parseRequestStatement($context);

        return parent::buildResponse($context);
    }
}
