<?php

namespace App\AccountsReceivable\Api;

use App\AccountsReceivable\Models\Customer;
use App\Core\RestApi\Routes\AbstractEditModelApiRoute;
use App\Core\RestApi\ValueObjects\ApiCallContext;
use App\Core\RestApi\ValueObjects\ApiRouteDefinition;
use App\Core\RestApi\ValueObjects\RequestParameter;
use App\Integrations\AccountingSync\Traits\AccountingApiParametersTrait;
use App\PaymentProcessing\Models\DisabledPaymentMethod;
use Doctrine\DBAL\Connection;

/**
 * @extends AbstractEditModelApiRoute<Customer>
 */
class EditCustomerRoute extends AbstractEditModelApiRoute
{
    use AccountingApiParametersTrait;
    private ?array $disabledPaymentMethods = null;

    public function __construct(private Connection $database)
    {
    }

    public function getDefinition(): ApiRouteDefinition
    {
        return new ApiRouteDefinition(
            queryParameters: $this->getBaseQueryParameters(),
            requestParameters: [
                'name' => new RequestParameter(),
                'number' => new RequestParameter(),
                'email' => new RequestParameter(),
                'type' => new RequestParameter(),
                'language' => new RequestParameter(),
                'owner' => new RequestParameter(),
                'active' => new RequestParameter(),
                'currency' => new RequestParameter(),
                'payment_terms' => new RequestParameter(),
                'collection_mode' => new RequestParameter(),
                'autopay' => new RequestParameter(),
                'autopay_delay_days' => new RequestParameter(),
                'default_source_id' => new RequestParameter(),
                'default_source_type' => new RequestParameter(),
                'sign_up_page' => new RequestParameter(),
                'parent_customer' => new RequestParameter(),
                'ach_gateway' => new RequestParameter(),
                'cc_gateway' => new RequestParameter(),
                'convenience_fee' => new RequestParameter(),
                'chase' => new RequestParameter(),
                'chasing_cadence' => new RequestParameter(),
                'next_chase_step' => new RequestParameter(),
                'credit_hold' => new RequestParameter(),
                'credit_limit' => new RequestParameter(),
                'consolidated' => new RequestParameter(),
                'bill_to_parent' => new RequestParameter(),
                'attention_to' => new RequestParameter(),
                'address1' => new RequestParameter(),
                'address2' => new RequestParameter(),
                'city' => new RequestParameter(),
                'state' => new RequestParameter(),
                'postal_code' => new RequestParameter(),
                'country' => new RequestParameter(),
                'phone' => new RequestParameter(),
                'notes' => new RequestParameter(),
                'taxable' => new RequestParameter(),
                'taxes' => new RequestParameter(),
                'tax_id' => new RequestParameter(),
                'avalara_exemption_number' => new RequestParameter(),
                'avalara_entity_use_code' => new RequestParameter(),
                'late_fee_schedule' => new RequestParameter(),
                'network_connection' => new RequestParameter(),
                'metadata' => new RequestParameter(),
                'payment_source' => new RequestParameter(),
                'disabled_payment_methods' => new RequestParameter(),
                'accounting_system' => new RequestParameter(),
                'accounting_id' => new RequestParameter(),
            ],
            requiredPermissions: ['customers.edit'],
            modelClass: Customer::class,
            features: ['accounts_receivable'],
            warn: true,
        );
    }

    public function retrieveModel(ApiCallContext $context): Customer
    {
        $customer = parent::retrieveModel($context);

        // load the existing payment source
        // NOTE even if not it will not be deleted this is
        // necessary in order to generate the correct previous
        // value for the customer.updated event
        $oldSource = $customer->payment_source;

        return $customer;
    }

    public function buildResponse(ApiCallContext $context): mixed
    {
        $requestParameters = $context->requestParameters;

        // backward compatibility after we remove owner_id as parameter
        if (array_key_exists('owner_id', $requestParameters)) {
            if (!isset($requestParameters['owner']) && !is_null($requestParameters['owner'])) {
                $requestParameters['owner'] = $requestParameters['owner_id'];
            }
            unset($requestParameters['owner_id']);
        }

        if (isset($requestParameters['disabled_payment_methods'])) {
            $this->disabledPaymentMethods = (array) $requestParameters['disabled_payment_methods'];
            unset($requestParameters['disabled_payment_methods']);
        }

        $context = $context->withRequestParameters($requestParameters);

        $this->parseRequestAccountingParameters($context);

        $customer = parent::buildResponse($context);

        $this->saveDisabledPaymentMethods($customer);
        $this->createAccountingMapping($customer);

        return $customer;
    }

    private function saveDisabledPaymentMethods(Customer $customer): void
    {
        if (!is_array($this->disabledPaymentMethods)) {
            return;
        }

        // clear out existing disabled methods
        $this->database->delete('DisabledPaymentMethods', [
            'tenant_id' => $customer->tenant_id,
            'object_type' => $customer->object,
            'object_id' => $customer->id(),
        ]);

        // save any disabled methods
        foreach ($this->disabledPaymentMethods as $method) {
            $disabled = new DisabledPaymentMethod();
            $disabled->method = $method;
            $disabled->object_type = $customer->object;
            $disabled->object_id = (string) $customer->id();
            $disabled->saveOrFail();
        }
    }
}
