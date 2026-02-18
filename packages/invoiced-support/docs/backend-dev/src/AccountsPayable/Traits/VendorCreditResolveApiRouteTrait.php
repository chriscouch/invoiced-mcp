<?php

namespace App\AccountsPayable\Traits;

use App\AccountsPayable\Libs\VendorDocumentResolver;
use App\AccountsPayable\Models\VendorCredit;
use App\AccountsPayable\Operations\EditVendorCredit;
use App\Core\RestApi\ValueObjects\ApiRouteDefinition;
use App\Core\RestApi\ValueObjects\RequestParameter;
use App\Network\Command\TransitionDocumentStatus;

trait VendorCreditResolveApiRouteTrait
{
    public function __construct(EditVendorCredit $operation, TransitionDocumentStatus $transitionDocumentStatus, VendorDocumentResolver $resolver)
    {
        parent::__construct($operation, $transitionDocumentStatus, $resolver);
    }

    public function getDefinition(): ApiRouteDefinition
    {
        return new ApiRouteDefinition(
            queryParameters: null,
            requestParameters: [
                'description' => new RequestParameter(
                    types: ['string', 'null'],
                    default: null,
                ),
            ],
            requiredPermissions: [],
            requiresMember: true,
            modelClass: VendorCredit::class,
            features: ['accounts_payable'],
        );
    }
}
