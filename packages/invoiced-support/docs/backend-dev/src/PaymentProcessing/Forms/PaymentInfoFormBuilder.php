<?php

namespace App\PaymentProcessing\Forms;

use App\AccountsReceivable\Models\Customer;
use App\AccountsReceivable\Models\Invoice;
use App\CashApplication\Models\Transaction;
use App\Core\I18n\ValueObjects\Money;
use App\Core\Utils\Enums\ObjectType;
use App\PaymentProcessing\Models\DisabledPaymentMethod;
use App\PaymentProcessing\Models\PaymentMethod;
use App\PaymentProcessing\Traits\PaymentFormTrait;
use App\PaymentProcessing\ValueObjects\PaymentFormSettings;
use App\PaymentProcessing\ValueObjects\PaymentInfoForm;

/**
 * This form handles collecting and updating
 * payment information collected from customers, for AutoPay.
 */
final class PaymentInfoFormBuilder
{
    use PaymentFormTrait;

    private ?Customer $customer = null;
    private ?PaymentMethod $method = null;
    private ?string $selectedPaymentMethod = null;
    private bool $openModalFlag = false;
    private bool $forceAutoPay = false;
    private bool $makeDefault = true;

    public function __construct(private PaymentFormSettings $settings)
    {
    }

    public function build(): PaymentInfoForm
    {
        return new PaymentInfoForm(
            company: $this->settings->company,
            customer: $this->customer,
            method: $this->method,
            methods: $this->methods(),
            selectedPaymentMethod: $this->selectedPaymentMethod,
            openModalFlag: $this->openModalFlag,
            forceAutoPay: $this->forceAutoPay,
            makeDefault: $this->makeDefault,
            allowAutoPayEnrollment: $this->allowAutoPayEnrollment(),
            outstandingAutoPayInvoices: $this->getOutstandingAutoPayInvoices(),
            outstandingAutoPayBalance: $this->getOutstandingAutoPayBalance(),
        );
    }

    public function setCustomer(Customer $customer): void
    {
        $this->customer = $customer;
    }

    public function setMethod(PaymentMethod $method): void
    {
        $this->method = $method;
    }

    private function methods(): array
    {
        // start with all enabled methods
        $methods = $this->getDefaultMethods($this->settings->company, $this->customer);

        // then filter to methods that support vaulting
        $methods = $this->getSupportedMethods($methods);

        // now remove any methods disabled for this customer
        $disabled = $this->getDisabledCustomerMethods();
        foreach ($disabled as $method) {
            if (isset($methods[$method])) {
                unset($methods[$method]);
            }
        }

        return $methods;
    }

    /**
     * Returns any methods that support storing payment sources and
     * initiating charges.
     */
    private function getSupportedMethods(array $methods): array
    {
        $result = [];
        foreach ($methods as $method) {
            if ($method->supportsAutoPay()) {
                $result[$method->id] = $method;
            }
        }

        return $result;
    }

    /**
     * Gets payment methods disabled by the customers in this
     * form.
     */
    private function getDisabledCustomerMethods(): array
    {
        $customer = $this->customer;
        if (!$customer) {
            return [];
        }

        $disabled = [];
        $disabledMethods = DisabledPaymentMethod::where('object_type', ObjectType::Customer->typeName())
            ->where('object_id', $customer)
            ->all();
        foreach ($disabledMethods as $model) {
            $disabled[] = $model->method;
        }

        return array_unique($disabled);
    }

    public function setSelectedPaymentMethod(?string $id): void
    {
        $this->selectedPaymentMethod = $id;
    }

    public function setOpenModalFlag(bool $flag): void
    {
        $this->openModalFlag = $flag;
    }

    public function setForceAutoPay(): void
    {
        $this->forceAutoPay = true;
    }

    public function setMakeDefault(bool $value): void
    {
        $this->makeDefault = $value;
    }

    /**
     * Gets all the currently outstanding AutoPay invoices for this customer.
     */
    private function getOutstandingAutoPayInvoices(): array
    {
        if (!$this->customer) {
            return [];
        }

        return Invoice::where('customer', $this->customer)
            ->where('autopay', true)
            ->where('paid', false)
            ->where('closed', false)
            ->where('draft', false)
            ->where('voided', false)
            ->where('date', time(), '<=')
            ->where('( next_payment_attempt <= UNIX_TIMESTAMP() OR next_payment_attempt IS NULL OR attempt_count > 0 )')
            ->where('status', Transaction::STATUS_PENDING, '<>')
            ->where('payment_plan_id IS NULL')
            ->all()
            ->toArray();
    }

    /**
     * Gets the outstanding AutoPay balance.
     */
    private function getOutstandingAutoPayBalance(): ?Money
    {
        $autoPayInvoices = $this->getOutstandingAutoPayInvoices();

        $totalBalance = null;
        foreach ($autoPayInvoices as $invoice) {
            $invoiceBalance = Money::fromDecimal($invoice->currency, $invoice->balance);
            if (!$totalBalance) {
                $totalBalance = $invoiceBalance;
            } else {
                $totalBalance = $totalBalance->add($invoiceBalance);
            }
        }

        return $totalBalance;
    }

    /**
     * Checks if this form allows AutoPay enrollment.
     */
    private function allowAutoPayEnrollment(): bool
    {
        $customer = $this->customer;
        if (!$customer) {
            return false;
        }

        return !$customer->autopay && $this->settings->allowAutoPayEnrollment;
    }
}
