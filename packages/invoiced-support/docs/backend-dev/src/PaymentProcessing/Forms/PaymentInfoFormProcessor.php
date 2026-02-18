<?php

namespace App\PaymentProcessing\Forms;

use App\AccountsReceivable\Models\Customer;
use App\Core\Statsd\Interfaces\StatsdAwareInterface;
use App\Core\Statsd\StatsdAwareTrait;
use App\CustomerPortal\Enums\CustomerPortalEvent;
use App\CustomerPortal\Libs\CustomerPortalEvents;
use App\PaymentProcessing\Exceptions\AutoPayException;
use App\PaymentProcessing\Exceptions\FormException;
use App\PaymentProcessing\Exceptions\PaymentSourceException;
use App\PaymentProcessing\Models\PaymentMethod;
use App\PaymentProcessing\Models\PaymentSource;
use App\PaymentProcessing\Operations\AutoPay;
use App\PaymentProcessing\Operations\VaultPaymentInfo;
use App\PaymentProcessing\ValueObjects\PaymentInfoForm;

class PaymentInfoFormProcessor implements StatsdAwareInterface
{
    use StatsdAwareTrait;

    public function __construct(
        private VaultPaymentInfo $vaultPaymentInfo,
        private AutoPay $autoPay,
        private CustomerPortalEvents $customerPortalEvents
    ) {
    }

    /**
     * Handles the submitted payment form.
     *
     * @param array $parameters submitted parameters
     *
     * @throws FormException when the submission fails
     */
    public function handleSubmit(PaymentInfoForm $form, array $parameters): PaymentSource
    {
        // check if we are making this payment source the default
        $makeDefault = true;
        if (isset($parameters['make_default'])) {
            $makeDefault = $parameters['make_default'];
            unset($parameters['make_default']);
        }

        // check if we are enrolling the customer in AutoPay
        $enrollAutoPay = false;
        if (array_value($parameters, 'enroll_autopay')) {
            $enrollAutoPay = $parameters['enroll_autopay'];
            unset($parameters['enroll_autopay']);
        }

        /** @var Customer $customer */
        $customer = $form->customer;
        /** @var PaymentMethod $method */
        $method = $form->method;

        if (!$method->enabled()) {
            throw new FormException('Payment method is not enabled: '.$method->id);
        }

        if (!$method->supportsAutoPay()) {
            throw new FormException('Payment method does not support AutoPay: '.$method->id);
        }

        try {
            $paymentSource = $this->vaultPaymentInfo->save($method, $customer, $parameters, $makeDefault);
        } catch (PaymentSourceException $e) {
            // rethrow as a form exception
            throw new FormException($e->getMessage(), 0, $e);
        }

        // enroll the customer in AutoPay once the payment information is saved
        if ($enrollAutoPay) {
            $this->enrollInAutoPay($customer);
        }

        // track the customer portal event
        $this->customerPortalEvents->track($customer, CustomerPortalEvent::AddPaymentMethod);
        $this->statsd->increment('billing_portal.save_payment_info');

        // collect outstanding AutoPay invoices when
        // changing the customer's default payment source
        if ($makeDefault) {
            if (!$paymentSource->needsVerification()) {
                $this->collectOutstandingAutoPayInvoices($form);
            }
        }

        return $paymentSource;
    }

    /**
     * Enrolls the customer in AutoPay and vaults the payment source.
     *
     * @throws FormException when the customer could not be enrolled
     */
    private function enrollInAutoPay(Customer $customer): void
    {
        $customer->autopay = true;
        $saved = $customer->save();

        if (!$saved) {
            throw new FormException('Unable to enroll in AutoPay.');
        }

        // track the customer portal event
        $this->customerPortalEvents->track($customer, CustomerPortalEvent::AutoPayEnrollment);
        $this->statsd->increment('billing_portal.autopay_enrollment');
    }

    /**
     * Collects payment on any outstanding AutoPay invoices.
     *
     * @throws FormException
     */
    private function collectOutstandingAutoPayInvoices(PaymentInfoForm $form): void
    {
        foreach ($form->outstandingAutoPayInvoices as $invoice) {
            try {
                $this->autoPay->collect($invoice);
            } catch (AutoPayException $e) {
                throw new FormException('Failed to capture payment for invoice # '.$invoice->number.': '.$e->getMessage());
            }
        }
    }
}
