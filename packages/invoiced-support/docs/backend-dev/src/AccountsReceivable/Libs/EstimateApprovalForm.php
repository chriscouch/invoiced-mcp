<?php

namespace App\AccountsReceivable\Libs;

use App\AccountsReceivable\Models\Customer;
use App\AccountsReceivable\Models\Estimate;
use App\Companies\Models\Company;
use App\Core\Utils\Enums\ObjectType;
use App\CustomerPortal\Libs\CustomerPortal;
use App\PaymentProcessing\Libs\PaymentGatewayMetadata;
use App\PaymentProcessing\Libs\PaymentRouter;
use App\PaymentProcessing\Models\DisabledPaymentMethod;
use App\PaymentProcessing\Models\PaymentMethod;
use App\PaymentProcessing\Traits\PaymentFormTrait;

/**
 * This class customize the appearance of the estimate approval form
 * and handles form submissions.
 */
class EstimateApprovalForm
{
    use PaymentFormTrait;

    private Customer $customer;
    private array $methods;

    public function __construct(
        private CustomerPortal $customerPortal,
        private Estimate $estimate,
    ) {
        $this->customer = $estimate->customer();
    }

    public function getEstimate(): Estimate
    {
        return $this->estimate;
    }

    public function getCustomer(): Customer
    {
        return $this->customer;
    }

    public function getCompany(): Company
    {
        return $this->customerPortal->company();
    }

    public function useNewEstimateForm(): bool
    {
        return $this->customerPortal->company()->features->has('estimates_v2');
    }

    /**
     * Checks if the customer needs to verify their billing
     * information.
     */
    public function mustVerifyBillingInformation(): bool
    {
        return $this->customerPortal->enabled();
    }

    /**
     * Checks if the customer can pay a deposit.
     */
    public function hasDeposit(): bool
    {
        return $this->estimate->getDepositBalance()->isPositive();
    }

    /**
     * Checks if the customer needs to pay a deposit.
     *
     * @deprecated
     */
    public function hasRequiredDeposit(): bool
    {
        if (!$this->useNewEstimateForm()) {
            // do not ask for deposit if customer has AutoPay
            // this is used for AJ Tutoring
            if ($this->customer->autopay) {
                return false;
            }
        }

        return $this->hasDeposit();
    }

    /**
     * Checks if the customer needs payment information.
     */
    public function needsPaymentInformation(): bool
    {
        $customer = $this->estimate->customer();

        return $customer->autopay && !$customer->payment_source;
    }

    /**
     * Gets the enabled payment methods for this form.
     *
     * @deprecated
     *
     * @return PaymentMethod[] key-value map of payment methods
     */
    public function methods(): array
    {
        // start with all enabled methods
        $methods = $this->getMethods();

        // then collect all methods not supported by this estimate
        $disabled = $this->getDisabledEstimateMethods();

        // and remove them
        foreach ($disabled as $method) {
            if (isset($methods[$method])) {
                unset($methods[$method]);
            }
        }

        return array_values($methods);
    }

    /**
     * Gets the enabled AutoPay payment methods for this form.
     *
     * @deprecated
     *
     * @return PaymentMethod[] key-value map of payment methods
     */
    public function autoPayMethods(): array
    {
        // start with all enabled methods
        $methods = $this->getMethods();

        // filter out all of the AutoPay supporting methods
        $result = [];
        foreach ($methods as $method) {
            if ($method->supportsAutoPay()) {
                $result[] = $method;
            }
        }

        return $result;
    }

    /**
     * @deprecated
     *
     * @return PaymentMethod[]
     */
    private function getMethods(): array
    {
        if (isset($this->methods)) {
            return $this->methods;
        }

        // start with all enabled methods
        $methods = $this->getDefaultMethods($this->customerPortal->company(), $this->estimate->customer());

        // then collect all methods not supported by this form
        $disabled = array_unique(array_merge(
            $this->getDisabledCustomerMethods(),
            $this->getMethodsNotSupported($methods)
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
     *
     * @deprecated
     *
     * @return string[] list of disabled method IDs
     */
    private function getDisabledCustomerMethods(): array
    {
        $customer = $this->estimate->customer();

        $disabled = [];
        $disabledMethods = DisabledPaymentMethod::where('object_type', ObjectType::Customer->typeName())
            ->where('object_id', $customer)
            ->all();
        foreach ($disabledMethods as $model) {
            $disabled[] = $model->method;
        }

        return array_unique($disabled);
    }

    /**
     * Gets payment methods disabled by this estimate.
     *
     * @deprecated
     *
     * @return string[] list of disabled method IDs
     */
    private function getDisabledEstimateMethods(): array
    {
        $disabledMethods = DisabledPaymentMethod::where('object_type', ObjectType::Estimate->typeName())
            ->where('object_id', $this->estimate)
            ->all();

        $disabled = [];
        foreach ($disabledMethods as $model) {
            $disabled[] = $model->method;
        }

        return $disabled;
    }

    /**
     * Gets payment methods not supported by this form. This
     * can be due to a payment gateway that doesn't support the
     * form's currency or else an amount restriction.
     *
     * @deprecated
     *
     * @param array $methods list of method objects
     *
     * @return string[] list of disabled method IDs
     */
    private function getMethodsNotSupported(array $methods): array
    {
        $disabled = [];
        $router = new PaymentRouter();
        foreach ($methods as $method) {
            // Currently direct debit not supported
            if (in_array($method->id, [PaymentMethod::DIRECT_DEBIT])) {
                $disabled[] = $method->id;

                continue;
            }

            $gateway = $router->getGateway($method, $this->getCustomer(), [$this->estimate]);
            if (!$gateway) {
                continue;
            }

            // Check if the method's gateway supports
            // this form's currency. If '*' is returned
            // then the gateway supports all currencies.
            $currencies = PaymentGatewayMetadata::get()->getSupportedCurrencies($gateway, $method->id);
            if (is_array($currencies) && !in_array($this->estimate->currency, $currencies)) {
                $disabled[] = $method->id;
            }
        }

        return $disabled;
    }
}
