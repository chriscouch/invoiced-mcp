<?php

namespace App\SubscriptionBilling\Api;

use App\Core\RestApi\Exception\InvalidRequest;
use App\Core\RestApi\Routes\AbstractApiRoute;
use App\Core\RestApi\ValueObjects\ApiCallContext;
use App\Core\RestApi\ValueObjects\ApiRouteDefinition;
use App\Core\RestApi\ValueObjects\RequestParameter;
use App\Core\Multitenant\TenantContext;
use App\SubscriptionBilling\Exception\OperationException;
use App\SubscriptionBilling\Libs\SubscriptionPreview;

class SubscriptionPreviewRoute extends AbstractApiRoute
{
    public function __construct(private TenantContext $tenant)
    {
    }

    public function getDefinition(): ApiRouteDefinition
    {
        return new ApiRouteDefinition(
            queryParameters: [],
            requestParameters: [
                'plan' => new RequestParameter(
                    required: true,
                ),
                'customer' => new RequestParameter(
                    types: ['null', 'numeric'],
                    default: null,
                ),
                'amount' => new RequestParameter(
                    default: null,
                ),
                'tiers' => new RequestParameter(
                    default: [],
                ),
                'quantity' => new RequestParameter(
                    types: ['numeric'],
                    default: 0,
                ),
                'addons' => new RequestParameter(
                    default: [],
                ),
                'pending_line_items' => new RequestParameter(
                    default: [],
                ),
                'discounts' => new RequestParameter(
                    default: [],
                ),
                'taxes' => new RequestParameter(
                    default: [],
                ),
            ],
            requiredPermissions: [],
            features: ['subscription_billing'],
        );
    }

    public function buildResponse(ApiCallContext $context): mixed
    {
        // build the preview object
        $preview = $this->buildPreview($context);

        // generate the preview
        try {
            $preview->generate();
        } catch (OperationException $e) {
            throw new InvalidRequest($e->getMessage());
        }

        // return the response
        return [
            'first_invoice' => $preview->getFirstInvoice()->toArray(),
            'recurring_total' => $preview->getRecurringTotal(),
            'mrr' => $preview->getMrr(),
        ];
    }

    private function buildPreview(ApiCallContext $context): SubscriptionPreview
    {
        $preview = new SubscriptionPreview($this->tenant->get());

        $preview->setPlan($context->requestParameters['plan'])
            ->setAmount($context->requestParameters['amount'])
            ->setTiers($context->requestParameters['tiers'])
            ->setAddons($context->requestParameters['addons'])
            ->setDiscounts($context->requestParameters['discounts'])
            ->setTaxes($context->requestParameters['taxes'])
            ->setCustomer($context->requestParameters['customer'])
            ->setPendingLineItems($context->requestParameters['pending_line_items']);

        if ($context->requestParameters['quantity'] > 0) {
            $preview->setQuantity($context->requestParameters['quantity']);
        }

        return $preview;
    }
}
