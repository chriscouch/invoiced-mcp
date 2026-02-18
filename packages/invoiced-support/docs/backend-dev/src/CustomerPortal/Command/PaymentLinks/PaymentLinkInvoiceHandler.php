<?php

namespace App\CustomerPortal\Command\PaymentLinks;

use App\AccountsReceivable\Libs\PaymentLinkHelper;
use App\AccountsReceivable\Models\Invoice;
use App\AccountsReceivable\Models\PaymentLinkField;
use App\AccountsReceivable\Models\ShippingDetail;
use App\CashApplication\Enums\PaymentItemIntType;
use App\Core\I18n\ValueObjects\Money;
use App\Core\Orm\Exception\ModelException;
use App\Core\Utils\Enums\ObjectType;
use App\CustomerPortal\Exceptions\PaymentLinkException;
use App\CustomerPortal\ValueObjects\PaymentLinkResult;
use App\PaymentProcessing\Models\PaymentFlowApplication;
use stdClass;
use Symfony\Contracts\Translation\TranslatorInterface;

class PaymentLinkInvoiceHandler
{
    public function __construct(
        private TranslatorInterface $translator,
    ) {
    }

    /**
     * Determines the default name for line items that do not have a name.
     */
    public function getDefaultLineItemName(array $parameters): string
    {
        if (isset($parameters['description']) && $parameters['description']) {
            return $parameters['description'];
        }

        if (isset($parameters['booking_reference']) && $parameters['booking_reference']) {
            return $parameters['booking_reference'];
        }

        if (isset($parameters['invoice_number']) && $parameters['invoice_number']) {
            return $parameters['invoice_number'];
        }

        return $this->translator->trans('payment_link_line_item', [], 'customer_portal');
    }

    /**
     * Creates an invoice for a payment link submission.
     *
     * @throws PaymentLinkException|ModelException
     */
    public function handle(PaymentLinkResult $result, Money $amount, array $parameters): void
    {
        $this->createInvoice($result, $amount, $parameters);
        $this->updatePaymentFlow($result);
    }

    private function createInvoice(PaymentLinkResult $result, Money $amount, array $parameters): void
    {
        $defaultLineItemName = $this->getDefaultLineItemName($parameters);

        $invoice = new Invoice();
        $invoice->setCustomer($result->getCustomer());
        $invoice->currency = $amount->currency;
        if (isset($parameters['invoice_number'])) {
            $invoice->number = $parameters['invoice_number'];
        }

        $invoice->items = PaymentLinkHelper::getLineItems($result->paymentLink, $amount, $defaultLineItemName);
        $invoice->calculate_taxes = false;

        // Shipping Address
        if ($result->paymentLink->collect_shipping_address) {
            $address = $parameters['shipping'] ?? [];

            $shipTo = new ShippingDetail();
            $shipTo->name = $address['name'] ?? null;
            $shipTo->address1 = $address['address1'] ?? null;
            $shipTo->address2 = $address['address2'] ?? null;
            $shipTo->city = $address['city'] ?? null;
            $shipTo->state = $address['state'] ?? null;
            $shipTo->postal_code = $address['postal_code'] ?? null;
            $shipTo->country = $address['country'] ?? '';
            $invoice->ship_to = $shipTo;

            if (!$shipTo->name) {
                throw new PaymentLinkException('Missing shipping address');
            }
        }

        // Custom Fields
        $fields = PaymentLinkField::getForObjectType($result->paymentLink, ObjectType::Invoice);
        $metadata = new stdClass();
        foreach ($fields as $field) {
            $formId = $field->getFormId();
            $value = $parameters[$formId] ?? null;
            if ($value) {
                $metadata->{$field->custom_field_id} = $value;
            } elseif ($field->required) {
                throw new PaymentLinkException('Missing required invoice field "'.$field->custom_field_id.'"');
            }
        }
        $invoice->metadata = $metadata;

        $invoice->saveOrFail();
        $result->setInvoice($invoice);
    }

    private function updatePaymentFlow(PaymentLinkResult $result): void
    {
        $paymentFlow = $result->getPaymentFlow();
        $paymentFlow->customer = $result->getCustomer();
        $paymentFlow->saveOrFail();

        $application = new PaymentFlowApplication();
        $application->payment_flow = $paymentFlow;
        $application->type = PaymentItemIntType::Invoice;
        $application->invoice = $result->getInvoice();
        $application->amount = $result->getInvoice()->total;
        $application->saveOrFail();
    }
}
