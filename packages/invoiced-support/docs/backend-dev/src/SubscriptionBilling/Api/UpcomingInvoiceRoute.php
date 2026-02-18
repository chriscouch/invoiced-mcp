<?php

namespace App\SubscriptionBilling\Api;

use App\AccountsReceivable\Exception\InvoiceCalculationException;
use App\AccountsReceivable\Models\Customer;
use App\Core\RestApi\Exception\InvalidRequest;
use App\Core\RestApi\Routes\AbstractRetrieveModelApiRoute;
use App\Core\RestApi\ValueObjects\ApiCallContext;
use App\Core\RestApi\ValueObjects\ApiRouteDefinition;
use App\SalesTax\Exception\TaxCalculationException;
use App\SubscriptionBilling\Exception\PricingException;
use App\SubscriptionBilling\Libs\UpcomingInvoice;
use App\SubscriptionBilling\Models\Subscription;

class UpcomingInvoiceRoute extends AbstractRetrieveModelApiRoute
{
    public function getDefinition(): ApiRouteDefinition
    {
        return new ApiRouteDefinition(
            queryParameters: null,
            requestParameters: null,
            requiredPermissions: [],
            modelClass: Customer::class,
        );
    }

    public function buildResponse(ApiCallContext $context): mixed
    {
        $subscriptionId = $context->request->query->get('subscription');

        $customer = parent::buildResponse($context);

        $upcoming = new UpcomingInvoice($customer);

        // look up subscription
        if ($id = $subscriptionId) {
            $subscription = Subscription::where('id', $id)
                ->where('customer', $customer)
                ->oneOrNull();

            if (!$subscription) {
                throw new InvalidRequest("Customer does not have subscription: $id");
            }

            $upcoming->setSubscription($subscription);
        }

        try {
            return $upcoming->build();
        } catch (InvoiceCalculationException|TaxCalculationException|PricingException $e) {
            throw new InvalidRequest('The upcoming invoice could not be generated. '.$e->getMessage());
        }
    }
}
