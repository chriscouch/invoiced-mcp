<?php

namespace App\PaymentProcessing\Api\Stripe;

use App\AccountsReceivable\Models\Customer;
use App\Core\Multitenant\TenantContext;
use App\Core\Orm\Exception\ModelNotFoundException;
use App\Core\RestApi\Routes\AbstractModelApiRoute;
use App\Core\RestApi\ValueObjects\ApiCallContext;
use App\Core\RestApi\ValueObjects\ApiRouteDefinition;
use App\PaymentProcessing\Exceptions\InvalidGatewayConfigurationException;
use App\PaymentProcessing\Exceptions\PaymentSourceException;
use App\PaymentProcessing\Gateways\PaymentGatewayFactory;
use App\PaymentProcessing\Gateways\StripeGateway;
use App\PaymentProcessing\Libs\PaymentRouter;
use App\PaymentProcessing\Models\MerchantAccount;
use App\PaymentProcessing\Models\PaymentMethod;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Stripe\Exception\ExceptionInterface as StripeError;

class SetupIntentRoute extends AbstractModelApiRoute implements LoggerAwareInterface
{
    use LoggerAwareTrait;

    public function __construct(
        private TenantContext $tenant,
        private PaymentGatewayFactory $paymentGatewayFactory,
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
     * @throws InvalidGatewayConfigurationException
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

        /** @var StripeGateway $stripeGateway */
        $stripeGateway = $this->paymentGatewayFactory->get(StripeGateway::ID);

        $intent = $this->createMotoSetupIntent($stripeGateway, $merchantAccount, $customer, $paymentMethod);

        return ['setup_intent' => $intent];
    }

    /**
     * @throws PaymentSourceException
     */
    private function createMotoSetupIntent(StripeGateway $stripeGateway, MerchantAccount $merchantAccount, Customer $customer, string $paymentMethod): string
    {
        $params = [
            'usage' => 'off_session',
            'payment_method_types' => ['card'],
            'payment_method_options' => [
                'card' => [
                    'request_three_d_secure' => 'any',
                ],
            ],
        ];

        $stripe = $stripeGateway->getStripe($merchantAccount->toGatewayConfiguration());
        $stripeCustomer = $stripeGateway->findOrCreateStripeCustomer($customer, $stripe);
        if ($stripeCustomer) {
            $params['customer'] = $stripeCustomer->id;
        }

        try {
            $setupIntent = $stripe->setupIntents->create($params);
        } catch (StripeError $e) {
            $this->logger->error('Unable to create setup intent on Stripe', ['exception' => $e]);

            throw new PaymentSourceException($e->getMessage());
        }

        return (string) $setupIntent->client_secret;
    }
}