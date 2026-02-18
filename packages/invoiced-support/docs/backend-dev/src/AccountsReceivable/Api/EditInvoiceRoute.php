<?php

namespace App\AccountsReceivable\Api;

use App\AccountsReceivable\Models\Invoice;
use App\Core\RestApi\Routes\AbstractEditModelApiRoute;
use App\Core\RestApi\ValueObjects\ApiCallContext;
use App\Core\RestApi\ValueObjects\ApiRouteDefinition;
use App\Integrations\AccountingSync\Traits\AccountingApiParametersTrait;
use App\PaymentProcessing\Models\DisabledPaymentMethod;
use Doctrine\DBAL\Connection;

class EditInvoiceRoute extends AbstractEditModelApiRoute
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
            requestParameters: null,
            requiredPermissions: ['invoices.edit'],
            modelClass: Invoice::class,
            features: ['accounts_receivable'],
        );
    }

    public function buildResponse(ApiCallContext $context): mixed
    {
        $requestParameters = $context->requestParameters;

        // disabled payment methods
        if (isset($requestParameters['disabled_payment_methods'])) {
            $this->disabledPaymentMethods = (array) $requestParameters['disabled_payment_methods'];
            unset($requestParameters['disabled_payment_methods']);
        }

        $context = $context->withRequestParameters($requestParameters);

        $this->parseRequestAccountingParameters($context);

        /** @var Invoice $invoice */
        $invoice = parent::buildResponse($context);

        $this->saveDisabledPaymentMethods($invoice);
        $this->createAccountingMapping($invoice);

        return $invoice;
    }

    private function saveDisabledPaymentMethods(Invoice $invoice): void
    {
        if (!is_array($this->disabledPaymentMethods)) {
            return;
        }

        // clear out existing disabled methods
        $this->database->delete('DisabledPaymentMethods', [
            'tenant_id' => $invoice->tenant_id,
            'object_type' => $invoice->object,
            'object_id' => $invoice->id(),
        ]);

        // save any disabled methods
        foreach ($this->disabledPaymentMethods as $method) {
            $disabled = new DisabledPaymentMethod();
            $disabled->method = $method;
            $disabled->object_type = $invoice->object;
            $disabled->object_id = (string) $invoice->id();
            $disabled->saveOrFail();
        }
    }
}
