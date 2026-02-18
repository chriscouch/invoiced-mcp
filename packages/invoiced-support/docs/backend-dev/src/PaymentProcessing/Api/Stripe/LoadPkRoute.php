<?php

namespace App\PaymentProcessing\Api\Stripe;

use App\AccountsReceivable\Models\Customer;
use App\Core\Multitenant\TenantContext;
use App\Core\Orm\Exception\ModelNotFoundException;
use App\Core\RestApi\Routes\AbstractModelApiRoute;
use App\Core\RestApi\ValueObjects\ApiCallContext;
use App\Core\RestApi\ValueObjects\ApiRouteDefinition;
use App\PaymentProcessing\Exceptions\PaymentSourceException;
use App\PaymentProcessing\Gateways\PaymentGatewayFactory;
use App\PaymentProcessing\Libs\PaymentRouter;
use App\PaymentProcessing\Models\PaymentMethod;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;

class LoadPkRoute extends AbstractModelApiRoute implements LoggerAwareInterface
{
    use LoggerAwareTrait;

    public function __construct(
        private TenantContext $tenant,
        private PaymentRouter $paymentRouter,
    ) {
    }

    public function getDefinition(): ApiRouteDefinition
    {
        return new ApiRouteDefinition(
            queryParameters: null,
            requestParameters: null,
            requiredPermissions: [],
            modelClass: Customer::class,
            features: ['accounts_receivable'],
        );
    }

    /**
     * @throws PaymentSourceException
     * @throws ModelNotFoundException
     */
    public function buildResponse(ApiCallContext $context): array
    {
        $customerId = $context->request->get('customer_id');
        $paymentMethod = $context->request->get('payment_method');

        parent::setModelId($customerId);
        /** @var Customer $customer */
        $customer = parent::retrieveModel($context);

        $company = $this->tenant->get();
        $method = PaymentMethod::queryWithTenant($company)
            ->where('id', $paymentMethod === 'credit_card' ? PaymentMethod::CREDIT_CARD : PaymentMethod::ACH)
            ->where('enabled', true)
            ->oneOrNull();

        if (!$method) {
            throw new PaymentSourceException('Could not retrieve payment method');
        }

        $merchantAccount = $this->paymentRouter->getMerchantAccount($method, $customer);

        if (!$merchantAccount) {
            throw new PaymentSourceException('Could not retrieve merchant account');
        }

        return ['publishable_key' => $merchantAccount->toGatewayConfiguration()->credentials->publishable_key ?? null];
    }
}