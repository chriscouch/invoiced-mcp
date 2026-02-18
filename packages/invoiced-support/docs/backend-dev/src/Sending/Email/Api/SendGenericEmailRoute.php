<?php

namespace App\Sending\Email\Api;

use App\AccountsReceivable\Models\Customer;
use App\AccountsReceivable\ValueObjects\CustomerSignInLinkEmail;
use App\Core\RestApi\ValueObjects\ApiRouteDefinition;
use App\Core\RestApi\ValueObjects\RequestParameter;
use App\Sending\Email\Models\EmailTemplate;

class SendGenericEmailRoute extends SendDocumentEmailRoute
{
    public function getDefinition(): ApiRouteDefinition
    {
        return new ApiRouteDefinition(
            queryParameters: [],
            requestParameters: [
                'to' => new RequestParameter(
                    types: ['array'],
                    default: [],
                ),
                'bcc' => new RequestParameter(
                    types: ['string'],
                    default: [],
                ),
                'message' => new RequestParameter(
                    required: true,
                    types: ['string'],
                ),
                'subject' => new RequestParameter(
                    required: true,
                    types: ['string'],
                ),
            ],
            requiredPermissions: ['emails.send'],
            modelClass: Customer::class,
            features: ['accounts_receivable', 'email_sending'],
        );
    }

    /**
     * do nothing, we hardcode email id.
     */
    public function setEmailTemplateId(?string $template): void
    {
    }

    public function getEmailTemplateId(): ?string
    {
        return EmailTemplate::SIGN_IN_LINK;
    }

    public function getSendModel(): object
    {
        return new CustomerSignInLinkEmail($this->model);
    }
}
