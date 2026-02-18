<?php

namespace App\AccountsPayable\Traits;

use App\AccountsPayable\Libs\VendorDocumentResolver;
use App\AccountsPayable\Models\Bill;
use App\AccountsPayable\Operations\EditBill;
use App\Core\RestApi\ValueObjects\ApiRouteDefinition;
use App\Core\RestApi\ValueObjects\RequestParameter;
use App\Network\Command\TransitionDocumentStatus;

trait BillResolveApiRouteTrait
{
    public function __construct(EditBill $operation, TransitionDocumentStatus $transitionDocumentStatus, VendorDocumentResolver $resolver)
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
            modelClass: Bill::class,
            features: ['accounts_payable'],
        );
    }
}
