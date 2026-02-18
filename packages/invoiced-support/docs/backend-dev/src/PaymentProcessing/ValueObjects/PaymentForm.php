<?php

namespace App\PaymentProcessing\ValueObjects;

use App\AccountsReceivable\Models\CreditNote;
use App\AccountsReceivable\Models\Customer;
use App\AccountsReceivable\Models\ReceivableDocument;
use App\Companies\Models\Company;
use App\Core\I18n\ValueObjects\Money;
use App\PaymentProcessing\Models\PaymentMethod;
use App\PaymentProcessing\Models\PaymentSource;
use Symfony\Contracts\Translation\TranslatorInterface;

final readonly class PaymentForm
{
    public string $currency;
    /** @var ReceivableDocument[] */
    public array $documents;

    /**
     * @param PaymentMethod[]   $methods
     * @param PaymentFormItem[] $paymentItems
     */
    public function __construct(
        public Company $company,
        public Customer $customer,
        public Money $totalAmount,
        public ?PaymentMethod $method = null,
        public ?PaymentSource $paymentSource = null,
        public ?string $selectedPaymentMethod = null,
        public array $methods = [],
        public bool $allowAutoPayEnrollment = false,
        public bool $shouldCapturePaymentInfo = false,
        public bool $allowPartialPayments = false,
        public array $paymentItems = [],
        public string $locale = '',
    ) {
        $this->currency = $this->totalAmount->currency;
        $documents = [];
        foreach ($paymentItems as $item) {
            if ($document = $item->document) {
                $documents[] = $document;
            }
        }
        $this->documents = $documents;
    }

    public function hasCredit(): bool
    {
        foreach ($this->paymentItems as $item) {
            if (($item->document instanceof CreditNote)
                || (null === $item->document && 'Credit Balance' === $item->description)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Generates a one-line description for this payment. This should not
     * exceed 255 characters.
     */
    public function getPaymentDescription(TranslatorInterface $translator): string
    {
        $parts = [];
        foreach ($this->paymentItems as $item) {
            $parts[] = $item->description;
        }

        if (0 == count($this->paymentItems)) {
            return $translator->trans('messages.payment_form_invoice_description', ['%invoiceNumber%' => '', '%count%' => 0], 'customer_portal');
        }

        return substr(implode(', ', $parts), 0, 255);
    }

    /**
     * Gets the saved payment sources allowed for use with this
     * payment form.
     *
     * @return PaymentSource[]
     */
    public function getSavedPaymentSources(): array
    {
        if (!$this->customer->persisted()) {
            return [];
        }

        $paymentSources = $this->customer->paymentSources();

        // only show saved payment sources that match enabled payment methods
        $methods = array_keys($this->methods);
        $result = [];
        foreach ($paymentSources as $paymentSource) {
            if (in_array($paymentSource->getMethod(), $methods)) {
                $result[] = $paymentSource;
            }
        }

        return $result;
    }
}
