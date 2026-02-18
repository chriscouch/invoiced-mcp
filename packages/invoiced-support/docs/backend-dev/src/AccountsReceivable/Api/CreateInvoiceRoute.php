<?php

namespace App\AccountsReceivable\Api;

use App\AccountsReceivable\Models\Invoice;
use App\Core\RestApi\Exception\InvalidRequest;
use App\Core\RestApi\Routes\AbstractCreateModelApiRoute;
use App\Core\RestApi\ValueObjects\ApiCallContext;
use App\Core\RestApi\ValueObjects\ApiRouteDefinition;
use App\Integrations\AccountingSync\Traits\AccountingApiParametersTrait;
use App\PaymentProcessing\Models\DisabledPaymentMethod;
use App\PaymentProcessing\Models\MerchantAccountRouting;
use Symfony\Component\OptionsResolver\Exception\ExceptionInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * API endpoint to create invoices.
 */
class CreateInvoiceRoute extends AbstractCreateModelApiRoute
{
    use AccountingApiParametersTrait;

    private ?array $merchantAccountRouting = null;
    private ?array $disabledPaymentMethods = null;
    private ?array $delivery = null;

    public function getDefinition(): ApiRouteDefinition
    {
        return new ApiRouteDefinition(
            queryParameters: $this->getBaseQueryParameters(),
            requestParameters: null,
            requiredPermissions: ['invoices.create'],
            modelClass: Invoice::class,
            features: ['accounts_receivable'],
        );
    }

    public function buildResponse(ApiCallContext $context): mixed
    {
        $requestParameters = $context->requestParameters;

        // merchant account routing rules
        if (isset($requestParameters['merchant_account_routing'])) {
            $this->merchantAccountRouting = (array) $requestParameters['merchant_account_routing'];
            unset($requestParameters['merchant_account_routing']);
        }

        // disabled payment methods
        if (isset($requestParameters['disabled_payment_methods'])) {
            $this->disabledPaymentMethods = (array) $requestParameters['disabled_payment_methods'];
            unset($requestParameters['disabled_payment_methods']);
        }

        if (isset($requestParameters['delivery'])) {
            $this->validateInput($this->getDeliveryResolver(), (array) $requestParameters['delivery'], 'request');
            $this->delivery = (array) $requestParameters['delivery'];
            unset($requestParameters['delivery']);
        }

        $context = $context->withRequestParameters($requestParameters);

        $this->parseRequestAccountingParameters($context);

        /** @var Invoice $invoice */
        $invoice = parent::buildResponse($context);

        $this->createMerchantAccountRouting($invoice);
        $this->createDisabledPaymentMethods($invoice);
        $invoice->createInvoiceDelivery($this->delivery);
        $this->createAccountingMapping($invoice);

        return $invoice;
    }

    /**
     * @throws InvalidRequest
     */
    protected function validateInput(OptionsResolver $resolver, array $input, string $param): array
    {
        try {
            return $resolver->resolve($input);
        } catch (ExceptionInterface $e) {
            // Replace double quotation marks with single for more clean output.
            $message = str_replace('"', "'", $e->getMessage());

            throw new InvalidRequest($message, 400, $param);
        }
    }

    private function createMerchantAccountRouting(Invoice $invoice): void
    {
        if (!is_array($this->merchantAccountRouting)) {
            return;
        }

        foreach ($this->merchantAccountRouting as $rule) {
            $routing = new MerchantAccountRouting();
            $routing->method = $rule['method'];
            $routing->invoice_id = (int) $invoice->id();
            $routing->merchant_account_id = $rule['merchant_account'];
            $routing->saveOrFail();
        }
    }

    private function createDisabledPaymentMethods(Invoice $invoice): void
    {
        if (!is_array($this->disabledPaymentMethods)) {
            return;
        }

        foreach ($this->disabledPaymentMethods as $method) {
            $disabled = new DisabledPaymentMethod();
            $disabled->method = $method;
            $disabled->object_type = $invoice->object;
            $disabled->object_id = (string) $invoice->id();
            $disabled->saveOrFail();
        }
    }

    private function getDeliveryResolver(): OptionsResolver
    {
        $deliveryResolver = new OptionsResolver();
        $deliveryResolver->setDefined(['emails', 'chase_schedule']);
        $deliveryResolver->setAllowedTypes('emails', ['string', 'null']);
        $deliveryResolver->setAllowedTypes('chase_schedule', 'array');

        return $deliveryResolver;
    }
}
