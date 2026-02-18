<?php

namespace App\Network\Api;

use App\AccountsPayable\Models\Vendor;
use App\AccountsReceivable\Models\Customer;
use App\Companies\Models\Member;
use App\Core\RestApi\Exception\InvalidRequest;
use App\Core\RestApi\Routes\AbstractApiRoute;
use App\Core\RestApi\ValueObjects\ApiCallContext;
use App\Core\RestApi\ValueObjects\ApiRouteDefinition;
use App\Core\RestApi\ValueObjects\RequestParameter;
use App\Core\Multitenant\TenantContext;
use App\Core\Orm\ACLModelRequester;
use App\Network\Command\InviteToNetwork;
use App\Network\Exception\NetworkInviteException;
use Symfony\Component\HttpFoundation\Response;

class SendNetworkInvitationApiRoute extends AbstractApiRoute
{
    public function __construct(
        private TenantContext $tenant,
        private InviteToNetwork $inviteToNetwork,
    ) {
    }

    public function getDefinition(): ApiRouteDefinition
    {
        return new ApiRouteDefinition(
            queryParameters: null,
            requestParameters: [
                'to' => new RequestParameter(
                    required: true,
                    types: ['string'],
                ),
                'customer' => new RequestParameter(
                    types: ['integer', 'null'],
                    default: null,
                ),
                'vendor' => new RequestParameter(
                    types: ['integer', 'null'],
                    default: null,
                ),
            ],
            requiredPermissions: ['settings.edit'],
            features: ['network_invitations'],
        );
    }

    public function buildResponse(ApiCallContext $context): Response
    {
        $from = $this->tenant->get();

        try {
            $member = ACLModelRequester::get();
            if (!$member instanceof Member) {
                $member = null;
            }

            if ($customerId = $context->requestParameters['customer']) {
                $customer = Customer::find($customerId);
                if (!$customer) {
                    throw new InvalidRequest('Customer was not found: '.$customerId, 404);
                }

                $this->inviteToNetwork->inviteCustomer($from, $member, $context->requestParameters['to'], $customer);
            } elseif ($vendorId = $context->requestParameters['vendor']) {
                $vendor = Vendor::find($vendorId);
                if (!$vendor) {
                    throw new InvalidRequest('Vendor was not found: '.$vendor, 404);
                }

                $this->inviteToNetwork->inviteVendor($from, $member, $context->requestParameters['to'], $vendor);
            } else {
                throw new InvalidRequest('Must specify `customer` or `vendor` to invite.');
            }

            return new Response('', 204);
        } catch (NetworkInviteException $e) {
            throw new InvalidRequest($e->getMessage());
        }
    }
}
