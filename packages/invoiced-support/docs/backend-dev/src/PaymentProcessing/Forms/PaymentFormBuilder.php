<?php

namespace App\PaymentProcessing\Forms;

use App\AccountsReceivable\Models\CreditNote;
use App\AccountsReceivable\Models\Customer;
use App\AccountsReceivable\Models\Estimate;
use App\AccountsReceivable\Models\Invoice;
use App\AccountsReceivable\Models\ReceivableDocument;
use App\Core\I18n\ValueObjects\Money;
use App\Core\Utils\Enums\ObjectType;
use App\CustomerPortal\Libs\CustomerPortal;
use App\PaymentProcessing\Enums\PaymentAmountOption;
use App\PaymentProcessing\Exceptions\FormException;
use App\PaymentProcessing\Libs\PaymentAmountCalculator;
use App\PaymentProcessing\Libs\PaymentGatewayMetadata;
use App\PaymentProcessing\Libs\PaymentRouter;
use App\PaymentProcessing\Models\BankAccount;
use App\PaymentProcessing\Models\Card;
use App\PaymentProcessing\Models\DisabledPaymentMethod;
use App\PaymentProcessing\Models\PaymentMethod;
use App\PaymentProcessing\Models\PaymentSource;
use App\PaymentProcessing\Traits\PaymentFormTrait;
use App\PaymentProcessing\ValueObjects\PaymentForm;
use App\PaymentProcessing\ValueObjects\PaymentFormItem;
use App\PaymentProcessing\ValueObjects\PaymentFormSettings;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;

/**
 * This class builds payment forms.
 */
final class PaymentFormBuilder
{
    use PaymentFormTrait;

    private PaymentFormSettings $settings;
    private ?PaymentMethod $method = null;
    private Money $totalAmount;
    private ?PaymentSource $paymentSource = null;
    private ?string $selectedPaymentMethod = null;
    private array $methods;
    /** @var PaymentFormItem[] */
    private array $paymentItems = [];
    private Customer $customer;

    public function __construct(private readonly CustomerPortal $portal)
    {
        $this->settings = $this->portal->getPaymentFormSettings();
        if (!$customer = $this->portal->getSignedInCustomer()) {
            throw new UnauthorizedHttpException('');
        }
        $this->customer = $customer;
    }

    /**
     * @throws FormException when no items exist on the form
     */
    public function build(): PaymentForm
    {
        // If no items were added to the form or no customer was selected
        // then we cannot proceed with building the form.
        if (!isset($this->customer) || 0 === count($this->paymentItems)) {
            throw new FormException('No items were added to the form');
        }

        return new PaymentForm(
            company: $this->settings->company,
            customer: $this->customer,
            totalAmount: $this->totalAmount,
            method: $this->method,
            paymentSource: $this->paymentSource,
            selectedPaymentMethod: $this->selectedPaymentMethod,
            methods: $this->buildPaymentMethods(),
            allowAutoPayEnrollment: $this->allowAutoPayEnrollment(),
            shouldCapturePaymentInfo: $this->shouldCapturePaymentInfo(),
            paymentItems: $this->paymentItems,
            locale: $this->portal->getLocale(),
        );
    }

    public function setMethod(PaymentMethod $method): void
    {
        $this->method = $method;
    }

    /**
     * Adds an invoice to the payment form given a client ID.
     *
     * @throws FormException
     */
    public function addInvoiceFromClientId(string $clientId, ?PaymentAmountOption $amountOption, ?Money $amount): void
    {
        $invoice = Invoice::findClientId($clientId);
        if (!$invoice) {
            throw new FormException('Could not find invoice: '.$clientId);
        }

        if (!$amountOption) {
            $amountOption = $invoice->payment_plan_id ? PaymentAmountOption::PaymentPlan : PaymentAmountOption::PayInFull;
        }

        $this->addInvoice($invoice, $amountOption, $amount);
    }

    /**
     * Adds an invoice to the payment form.
     *
     * @throws FormException
     */
    public function addInvoice(Invoice $invoice, PaymentAmountOption $amountOption = PaymentAmountOption::PayInFull, ?Money $amount = null): void
    {
        $this->checkIfCanAddDocument($invoice);

        // Determine amount
        $amount = $this->getItemPaymentAmount($invoice, null, $amountOption, $amount);
        $this->validateAmount($invoice, null, $amount, PaymentAmountOption::PayInFull);

        $this->addItem(new PaymentFormItem(
            amount: $amount,
            description: $invoice->number,
            document: $invoice,
            amountOption: $amountOption,
        ));
    }

    /**
     * Adds a credit note to the payment form given a client ID.
     *
     * @throws FormException
     */
    public function addCreditNoteFromClientId(string $clientId, ?PaymentAmountOption $amountOption, ?Money $amount): void
    {
        if (!$this->settings->allowApplyingCredits) {
            throw new FormException('Credit notes are not allowed to be added to this form');
        }

        $creditNote = CreditNote::findClientId($clientId);
        if (!$creditNote) {
            throw new FormException('Could not find credit note: '.$clientId);
        }

        $amountOption ??= PaymentAmountOption::ApplyCredit;

        $this->addCreditNote($creditNote, $amountOption, $amount);
    }

    /**
     * Adds a credit note to the payment form.
     *
     * @throws FormException
     */
    public function addCreditNote(CreditNote $creditNote, PaymentAmountOption $amountOption = PaymentAmountOption::ApplyCredit, ?Money $amount = null): void
    {
        $this->checkIfCanAddDocument($creditNote);

        // Determine amount
        $amount = $this->getItemPaymentAmount($creditNote, null, $amountOption, $amount);
        $this->validateAmount($creditNote, null, $amount, PaymentAmountOption::ApplyCredit);

        $this->addItem(new PaymentFormItem(
            amount: $amount,
            description: $creditNote->number,
            document: $creditNote,
            amountOption: $amountOption,
        ));
    }

    /**
     * Adds an estimate to the payment form given a client ID.
     *
     * @throws FormException
     */
    public function addEstimateFromClientId(string $clientId, ?PaymentAmountOption $amountOption, ?Money $amount): void
    {
        $estimate = Estimate::findClientId($clientId);
        if (!$estimate) {
            throw new FormException('Could not find estimate: '.$clientId);
        }

        $amountOption ??= PaymentAmountOption::PayInFull;

        $this->addEstimate($estimate, $amountOption, $amount);
    }

    /**
     * Adds an estimate to the payment form.
     *
     * @throws FormException
     */
    public function addEstimate(Estimate $estimate, PaymentAmountOption $amountOption = PaymentAmountOption::PayInFull, ?Money $amount = null): void
    {
        $this->checkIfCanAddDocument($estimate);

        // Determine amount
        $amount = $this->getItemPaymentAmount($estimate, null, $amountOption, $amount);
        $this->validateAmount($estimate, null, $amount, PaymentAmountOption::PayInFull);

        $this->addItem(new PaymentFormItem(
            amount: $amount,
            description: $estimate->number,
            document: $estimate,
            amountOption: $amountOption,
        ));
    }

    /**
     * Adds a credit balance to the payment form given a client ID.
     *
     * @throws FormException
     */
    public function addCreditBalanceFromClientId(string $clientId, ?PaymentAmountOption $amountOption, ?Money $amount): void
    {
        if (!$this->settings->allowApplyingCredits) {
            throw new FormException('Credit balances are not allowed to be added to this form');
        }

        $customer = Customer::findClientId($clientId);
        if (!$customer) {
            throw new FormException('Could not find customer: '.$clientId);
        }

        $amountOption ??= PaymentAmountOption::ApplyCredit;

        $this->addCreditBalance($customer, $amountOption, $amount);
    }

    /**
     * Adds a credit note to the payment form.
     *
     * @throws FormException
     */
    public function addCreditBalance(Customer $customer, PaymentAmountOption $amountOption = PaymentAmountOption::ApplyCredit, ?Money $amount = null): void
    {
        // must match any existing customer
        if (isset($this->customer) && $customer->id != $this->customer->id) {
            throw new FormException('Line item customer does not match payment customer.');
        }

        // must match any existing currency
        if (isset($this->totalAmount) && $amount && $amount->currency != $this->totalAmount->currency) {
            throw new FormException('Line item currency does not match payment currency.');
        }

        // Set the currency if this is the first item added to the form
        if (!isset($this->totalAmount)) {
            $this->setCurrency($this->settings->company->currency);
        }

        // Determine amount
        $amount = $this->getItemPaymentAmount(null, $this->customer, $amountOption, $amount);
        $this->validateAmount(null, $this->customer, $amount, PaymentAmountOption::ApplyCredit);

        $this->addItem(new PaymentFormItem(
            amount: $amount,
            description: 'Credit Balance',
            document: null,
            amountOption: $amountOption,
        ));
    }

    /**
     * Adds an advance payment to the payment form given a client ID.
     *
     * @throws FormException
     */
    public function addAdvancePaymentFromClientId(string $clientId, ?PaymentAmountOption $amountOption, ?Money $amount): void
    {
        if (!$this->settings->allowAdvancePayments) {
            throw new FormException('Advance payments are not allowed to be added to this form');
        }

        $customer = Customer::findClientId($clientId);
        if (!$customer) {
            throw new FormException('Could not find customer: '.$clientId);
        }

        $amountOption ??= PaymentAmountOption::AdvancePayment;

        $this->addAdvancePayment($customer, $amountOption, $amount);
    }

    /**
     * Adds an advance payment to the payment form.
     *
     * @throws FormException
     */
    public function addAdvancePayment(Customer $customer, PaymentAmountOption $amountOption = PaymentAmountOption::AdvancePayment, ?Money $amount = null): void
    {
        // must match any existing customer
        if (!$this->portal->match($customer->id)) {
            throw new FormException('Line item customer does not match payment customer.');
        }

        // must match any existing currency
        if (isset($this->totalAmount) && $amount && $amount->currency != $this->totalAmount->currency) {
            throw new FormException('Line item currency does not match payment currency.');
        }

        // Set the currency if this is the first item added to the form
        if (!isset($this->totalAmount)) {
            $this->setCurrency($amount->currency ?? $customer->currency ?? $this->settings->company->currency);
        }

        // Determine amount
        $amount = $this->getItemPaymentAmount(null, null, $amountOption, $amount);
        $this->validateAmount(null, null, $amount, PaymentAmountOption::AdvancePayment);

        $this->addItem(new PaymentFormItem(
            amount: $amount,
            description: 'Advance Payment',
            document: null,
            amountOption: $amountOption,
        ));
    }

    /**
     * @throws FormException
     */
    private function checkIfCanAddDocument(ReceivableDocument $document): void
    {
        // must match the company
        if ($document->tenant_id != $this->settings->company->id()) {
            throw new FormException('Document cannot be added.');
        }

        // cannot be voided
        if ($document->voided) {
            throw new FormException('Document cannot be paid because it has been voided.');
        }

        // must match any existing customer
        if (!$this->portal->match($document->customer)) {
            throw new FormException('Line item customer does not match payment customer.');
        }

        // must match any existing currency
        if (isset($this->totalAmount) && $document->currency != $this->totalAmount->currency) {
            throw new FormException('Line item currency does not match payment currency.');
        }

        // Set the currency if this is the first item added to the form
        if (!isset($this->totalAmount)) {
            $this->setCurrency($document->currency);
        }
    }

    private function setCurrency(string $currency): void
    {
        $this->totalAmount = Money::zero($currency);
    }

    private function buildPaymentMethods(): array
    {
        if (isset($this->methods)) {
            return $this->methods;
        }

        // start with all enabled methods
        $methods = $this->getDefaultMethods($this->settings->company, $this->customer);

        $documents = [];
        foreach ($this->paymentItems as $item) {
            if ($item->document) {
                $documents[] = $item->document;
            }
        }

        // then collect all methods disabled by business rules
        $disabled = array_unique(array_merge(
            $this->getDisabledCustomerMethods(),
            $this->getDisabledDocumentMethods($documents, $methods),
            $this->getMethodsNotSupported($documents, $methods)
        ));

        // and remove them
        foreach ($disabled as $method) {
            if (isset($methods[$method])) {
                unset($methods[$method]);
            }
        }

        $this->methods = $methods;

        return $this->methods;
    }

    /**
     * Gets payment methods disabled by the customers in this
     * form.
     */
    private function getDisabledCustomerMethods(): array
    {
        $disabled = [];
        $disabledMethods = DisabledPaymentMethod::where('object_type', ObjectType::Customer->typeName())
            ->where('object_id', $this->customer)
            ->all();
        foreach ($disabledMethods as $model) {
            $disabled[] = $model->method;
        }

        return array_unique($disabled);
    }

    /**
     * Gets payment methods disabled by the invoices and estimates in this
     * form. This function looks for the greatest common
     * denominator amongst invoices, that is it finds all of the
     * methods that are supported by ALL of the invoices and estimates.
     *
     * @param ReceivableDocument[] $documents
     * @param PaymentMethod[]      $methods
     */
    private function getDisabledDocumentMethods(array $documents, array $methods): array
    {
        $disabled = [];
        foreach ($documents as $document) {
            $disabledMethods = DisabledPaymentMethod::where('object_type', $document->object)
                ->where('object_id', $document)
                ->all();
            foreach ($disabledMethods as $model) {
                $disabled[] = $model->method;
            }

            // AutoPay invoices do not support
            // any payment methods that do not support saving
            // payment sources
            if ($document instanceof Invoice && $document->autopay) {
                foreach ($methods as $method) {
                    if (!in_array($method->id, $disabled) &&
                        !$method->supportsAutoPay()) {
                        $disabled[] = $method->id;
                    }
                }
            }
        }

        return array_unique($disabled);
    }

    /**
     * Gets payment methods not supported by this form. This
     * can be due to a payment gateway that doesn't support the
     * form's currency or else an amount restriction.
     *
     * @param ReceivableDocument[] $documents
     * @param array                $methods   list of method objects
     */
    private function getMethodsNotSupported(array $documents, array $methods): array
    {
        $hasEstimates = false;
        foreach ($documents as $document) {
            if ($document instanceof Estimate) {
                $hasEstimates = true;
                break;
            }
        }

        $disabled = [];
        $router = new PaymentRouter();
        foreach ($methods as $method) {
            $gateway = $router->getGateway($method, $this->customer, $documents);
            if (!$gateway) {
                // When estimates are present then only processed payments
                // can be made. Promise-to-pay is not supported.
                // A special case is made for PayPal because it does not use a gateway.
                if ($hasEstimates && PaymentMethod::PAYPAL != $method->id) {
                    $disabled[] = $method->id;
                }

                continue;
            }

            // Check if the method's gateway supports
            // this form's currency. If '*' is returned
            // then the gateway supports all currencies.
            $currencies = PaymentGatewayMetadata::get()->getSupportedCurrencies($gateway, $method->id);
            if ('*' !== $currencies && !in_array($this->totalAmount->currency, (array) $currencies)) {
                $disabled[] = $method->id;
            }
        }

        // Check the amounts fit within the allowed
        // minimum / maximum of the payment methods
        foreach ($methods as $method) {
            if ($method->min > 0) {
                $min = new Money($this->totalAmount->currency, $method->min);
                if ($this->totalAmount->lessThan($min)) {
                    $disabled[] = $method->id;
                }
            }

            if ($method->max > 0) {
                $max = new Money($this->totalAmount->currency, $method->max);
                if ($this->totalAmount->greaterThan($max)) {
                    $disabled[] = $method->id;
                }
            }
        }

        return array_unique($disabled);
    }

    /**
     * Sets the payment source.
     */
    public function setPaymentSource(string $sourceType, string $sourceId): void
    {
        $source = null;
        if ('card' == $sourceType) {
            $source = Card::where('id', $sourceId)
                ->where('customer_id', $this->customer)
                ->where('chargeable', true)
                ->oneOrNull();
        } elseif ('bank_account' == $sourceType) {
            $source = BankAccount::where('id', $sourceId)
                ->where('customer_id', $this->customer)
                ->where('chargeable', true)
                ->oneOrNull();
        }

        if (!$source instanceof PaymentSource) {
            return;
        }

        $this->paymentSource = $source;
        $this->setMethod($source->getPaymentMethod());
    }

    /**
     * Sets the selected payment method.
     */
    public function setSelectedPaymentMethod(?string $id): void
    {
        $this->selectedPaymentMethod = $id;
    }

    /**
     * Checks if this form allows AutoPay enrollment.
     */
    private function allowAutoPayEnrollment(): bool
    {
        return !$this->customer->autopay && $this->settings->allowAutoPayEnrollment;
    }

    /**
     * Checks if we should store the payment info
     * the customer enters into the payment form.
     *
     * This happens when the customer does not have
     * payment info AND has zero invoices selected or
     * one of the invoices requires AutoPay.
     */
    private function shouldCapturePaymentInfo(): bool
    {
        if ($this->customer->payment_source) {
            return false;
        }

        $numInvoices = 0;
        foreach ($this->paymentItems as $item) {
            if ($item->document instanceof Invoice) {
                if ($item->document->autopay) {
                    return true;
                }
                ++$numInvoices;
            }
        }

        // Capture payment information if the customer is AutoPay
        // and there was NOT a single invoice that had AutoPay disabled.
        return 0 == $numInvoices && $this->customer->autopay;
    }

    /**
     * @throws FormException
     */
    private function getItemPaymentAmount(?ReceivableDocument $document, ?Customer $customer, PaymentAmountOption $option, ?Money $amount): Money
    {
        $presetAmount = (new PaymentAmountCalculator())->calculate($document, $option, $customer);
        if ($presetAmount) {
            return $presetAmount;
        }

        if (!$amount) {
            if ($document) {
                throw new FormException('Missing payment amount for '.$document->object.' # '.$document->number);
            }
            throw new FormException('Missing payment amount for line item');
        }

        return $amount;
    }

    /**
     * @throws FormException
     */
    private function validateAmount(?ReceivableDocument $document, ?Customer $customer, Money $amount, PaymentAmountOption $maxOption): void
    {
        // Validate the payment amount based on the maximum amount that could be paid.
        $maxAmount = (new PaymentAmountCalculator())->calculate($document, $maxOption, $customer);

        // Credit note amounts are negative
        if ($document instanceof CreditNote) {
            if ($amount->isPositive()) {
                throw new FormException('The payment amount for '.$document->object.' # '.$document->number.' cannot be greater than zero');
            }

            if ($maxAmount && $amount->lessThan($maxAmount)) {
                throw new FormException('The payment amount for '.$document->object.' # '.$document->number.' cannot exceed '.$maxAmount);
            }

            return;
        }

        // Credit balances are negative
        if (!$document && $customer) {
            if ($amount->isPositive()) {
                throw new FormException('The credit balance amount cannot be greater than zero');
            }

            if ($maxAmount && $amount->lessThan($maxAmount)) {
                throw new FormException('The credit balance amount cannot exceed '.$maxAmount);
            }

            return;
        }

        if ($amount->isNegative()) {
            throw new FormException('The payment amount for '.$document?->object.' # '.$document?->number.' cannot be less than zero');
        }

        if ($maxAmount && $amount->greaterThan($maxAmount)) {
            throw new FormException('The payment amount for '.$document?->object.' # '.$document?->number.' cannot exceed '.$maxAmount);
        }
    }

    private function addItem(PaymentFormItem $item): void
    {
        // it should not be possible for the total to go negative
        $this->totalAmount = Money::zero($this->totalAmount->currency)->max($this->totalAmount->add($item->amount));
        $this->paymentItems[] = $item;
    }
}
