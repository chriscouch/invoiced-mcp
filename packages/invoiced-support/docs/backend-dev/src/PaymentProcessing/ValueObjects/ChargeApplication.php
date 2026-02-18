<?php

namespace App\PaymentProcessing\ValueObjects;

use App\AccountsReceivable\Enums\InvoiceStatus;
use App\AccountsReceivable\Models\Customer;
use App\AccountsReceivable\Models\Invoice;
use App\AccountsReceivable\Models\ReceivableDocument;
use App\Core\I18n\ValueObjects\Money;
use App\PaymentProcessing\Enums\PaymentFlowSource;
use App\PaymentProcessing\Exceptions\ChargeException;
use App\PaymentProcessing\Interfaces\ChargeApplicationItemInterface;
use App\PaymentProcessing\Interfaces\CreditChargeApplicationItemInterface;
use App\PaymentProcessing\Libs\ConvenienceFeeHelper;
use App\PaymentProcessing\Libs\PaymentGatewayMetadata;
use App\PaymentProcessing\Models\PaymentMethod;
use RuntimeException;

/**
 * Describes how a charge is going to be applied and
 * contains the logic to perform the application once
 * the charge has been processed.
 */
final class ChargeApplication
{
    /**
     * @param ChargeApplicationItemInterface[] $items
     */
    public function __construct(
        private array $items,
        private PaymentFlowSource $source,
        public readonly array $paymentValues = [],
    ) {
    }

    /**
     * Validates the charge application.
     *
     * @throws ChargeException
     */
    public function validate(Customer $customer, string $gatewayId, string $method): void
    {
        $this->validateAmount($gatewayId, $method);
        $this->validateDocuments($customer);
    }

    /**
     * Validates the requested payment amount.
     *
     * @throws ChargeException if the payment amount is not within an acceptable range
     */
    public function validateAmount(string $gatewayId, string $method): void
    {
        if (0 === count($this->items)) {
            throw new ChargeException('Payment cannot be empty');
        }

        $amount = $this->getPaymentAmount();

        // validate the currency
        // NOTE: '*' means all currencies are supported
        $currencies = PaymentGatewayMetadata::get()->getSupportedCurrencies($gatewayId, $method);
        if (is_array($currencies) && !in_array($amount->currency, $currencies)) {
            throw new ChargeException("The $gatewayId payment gateway / $method payment method does not support the '{$amount->currency}' currency.");
        }

        // the payment amount can only be zero if there is a credit applied
        if ($amount->isZero() && $this->getCreditAmount()?->isPositive()) {
            return;
        }

        // validate the payment amount
        $minAmount = PaymentGatewayMetadata::get()->getMinPaymentAmount($gatewayId, $amount->currency);
        if ($amount->lessThan($minAmount)) {
            throw new ChargeException("Payment amount cannot be less than $minAmount");
        }

        // validate the amount of each line item
        foreach ($this->items as $item) {
            if ($item instanceof CreditChargeApplicationItemInterface) {
                if (!$item->getCredit()->isPositive()) {
                    throw new ChargeException('Payment line item amount must be greater than zero.'.($item->getDocument() ? ' Document: '.$item->getDocument()->number : ''));
                }
            } elseif (!$item->getAmount()->isPositive()) {
                throw new ChargeException('Payment line item amount must be greater than zero.'.($item->getDocument() ? ' Document: '.$item->getDocument()->number : ''));
            }
        }
    }

    /**
     * Validates that all documents are in a valid state for payment processing.
     *
     * @throws ChargeException if a document is in an invalid state
     */
    public function validateDocuments(Customer $customer): void
    {
        foreach ($this->getDocuments() as $document) {
            // validate invoices
            if ($document instanceof Invoice) {
                if (InvoiceStatus::Pending->value == $document->status) {
                    throw new ChargeException("Payment cannot be processed because it's applied to an invoice with a pending payment");
                }
            }

            // validate that the customer matches
            if ($document->customer != $customer->id()) {
                $invoiceCustomer = $document->customer();
                if (!$customer->isParentOf($invoiceCustomer)) {
                    throw new ChargeException('The '.strtolower($document::modelName()).' provided ('.$document->number.') does not belong to the customer that was selected to apply payments for: '.$customer->number);
                }
            }
        }
    }

    /**
     * @return ChargeApplicationItemInterface[]
     */
    public function getItems(): array
    {
        return $this->items;
    }

    /**
     * Gets the source of the charge that is set on the
     * created payment.
     * Examples: `charge` or `customer_portal`.
     */
    public function getPaymentSource(): PaymentFlowSource
    {
        return $this->source;
    }

    /**
     * Gets the documents included in this charge application.
     *
     * @return ReceivableDocument[]
     */
    public function getDocuments(): array
    {
        $result = [];
        foreach ($this->items as $item) {
            if ($document = $item->getDocument()) {
                $result[$document->object.'-'.$document->id] = $document;
            }
        }

        return array_values($result);
    }

    /**
     * Gets the unique documents included in this charge application
     * that are NOT part of a credit application item.
     *
     * @return ReceivableDocument[]
     */
    public function getNonCreditDocuments(): array
    {
        $result = [];
        foreach ($this->items as $item) {
            $document = $item->getDocument();
            if (!$item instanceof CreditChargeApplicationItemInterface && $document) {
                $result[$document->object.'-'.$document->id] = $document;
            }
        }

        return array_values($result);
    }

    /**
     * Amount covered by credits.
     */
    public function getCreditAmount(): ?Money
    {
        return array_reduce(
            $this->items,
            function (?Money $carry, ChargeApplicationItemInterface $item): ?Money {
                if (!$item instanceof CreditChargeApplicationItemInterface) {
                    return $carry;
                }

                return $carry ? $item->getCredit()->add($carry) : $item->getCredit();
            }
        );
    }

    /**
     * Total payment amount.
     */
    public function getPaymentAmount(): Money
    {
        $amount = array_reduce(
            $this->items,
            fn (?Money $carry, ChargeApplicationItemInterface $item) => $carry ? $item->getAmount()->add($carry) : $item->getAmount()
        );

        if (!$amount) {
            throw new RuntimeException('No items in charge application');
        }

        return $amount;
    }

    /**
     * Adds a convenience fee to the payment if it is configured.
     */
    public function applyConvenienceFee(PaymentMethod $method, Customer $customer): array
    {
        $convenienceFee = ConvenienceFeeHelper::calculate($method, $customer, $this->getPaymentAmount());
        if ($convenienceFee['amount']?->isPositive()) {
            $this->items[] = new ConvenienceFeeChargeApplicationItem($convenienceFee['amount']);
        }

        return $convenienceFee;
    }
}
