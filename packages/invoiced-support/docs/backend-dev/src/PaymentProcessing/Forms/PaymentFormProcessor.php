<?php

namespace App\PaymentProcessing\Forms;

use App\AccountsReceivable\Models\Customer;
use App\AccountsReceivable\Models\Invoice;
use App\AccountsReceivable\Models\Note;
use App\CashApplication\Models\Payment;
use App\Chasing\Models\PromiseToPay;
use App\Core\I18n\ValueObjects\Money;
use App\Core\Statsd\Interfaces\StatsdAwareInterface;
use App\Core\Statsd\StatsdAwareTrait;
use App\Core\Utils\Enums\ObjectType;
use App\CustomerPortal\Enums\CustomerPortalEvent;
use App\CustomerPortal\Libs\CustomerPortal;
use App\CustomerPortal\Libs\CustomerPortalEvents;
use App\Notifications\Enums\NotificationEventType;
use App\Notifications\Libs\NotificationSpool;
use App\PaymentProcessing\Enums\PaymentAmountOption;
use App\PaymentProcessing\Exceptions\AdyenCardException;
use App\PaymentProcessing\Exceptions\ChargeDeclinedException;
use App\PaymentProcessing\Exceptions\ChargeException;
use App\PaymentProcessing\Exceptions\FormException;
use App\PaymentProcessing\Exceptions\PaymentSourceException;
use App\PaymentProcessing\Libs\ChargeApplicationBuilder;
use App\PaymentProcessing\Libs\PaymentRouter;
use App\PaymentProcessing\Models\BankAccount;
use App\PaymentProcessing\Models\Card;
use App\PaymentProcessing\Models\PaymentFlow;
use App\PaymentProcessing\Models\PaymentMethod;
use App\PaymentProcessing\Models\PaymentSource;
use App\PaymentProcessing\Operations\DeletePaymentInfo;
use App\PaymentProcessing\Operations\ProcessPayment;
use App\PaymentProcessing\Operations\VaultPaymentInfo;
use App\PaymentProcessing\ValueObjects\ChargeApplication;
use App\PaymentProcessing\ValueObjects\PaymentForm;
use Carbon\CarbonImmutable;
use Symfony\Component\HttpFoundation\InputBag;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class PaymentFormProcessor implements StatsdAwareInterface
{
    use StatsdAwareTrait;

    public function __construct(
        private VaultPaymentInfo $vaultPaymentInfo,
        private DeletePaymentInfo $deletePaymentInfo,
        private ProcessPayment $processPayment,
        private NotificationSpool $notificationSpool,
        private CustomerPortalEvents $customerPortalEvents
    ) {
    }

    /**
     * Builds a payment form object given a POST form submission.
     *
     * @throws NotFoundHttpException|FormException
     */
    public function makePaymentFormPost(CustomerPortal $portal, InputBag $inputBag): PaymentForm
    {
        $company = $portal->company();
        $settings = $portal->getPaymentFormSettings();
        $builder = new PaymentFormBuilder($portal);

        // verify the payment method
        // (saved methods are treated differently)
        // set the method
        $methodId = $inputBag->getString('method');
        $isStoredMethod = str_starts_with($methodId, 'saved:');
        if (!$isStoredMethod) {
            $builder->setMethod(PaymentMethod::instance($company, $methodId));
        }

        $currency = (string) $inputBag->get('currency');
        $amountOptions = $inputBag->all('amount_type');
        $amounts = $inputBag->all('amount');

        // add the estimates
        foreach ($inputBag->all('Quote') as $clientId) {
            $amountOption = $amountOptions['Quote'][$clientId] ?? null;
            $amountOption = $amountOption ? PaymentAmountOption::from($amountOption) : null;
            $amount = $amounts['Quote'][$clientId] ?? null;
            $amount = $amount ? Money::fromDecimal($currency, $amount) : null;
            $builder->addEstimateFromClientId($clientId, $amountOption, $amount);
        }

        // add the invoices
        foreach ($inputBag->all('Invoice') as $clientId) {
            $amountOption = $amountOptions['Invoice'][$clientId] ?? null;
            $amountOption = $amountOption ? PaymentAmountOption::from($amountOption) : null;
            $amount = $amounts['Invoice'][$clientId] ?? null;
            $amount = $amount ? Money::fromDecimal($currency, $amount) : null;
            $builder->addInvoiceFromClientId($clientId, $amountOption, $amount);
        }

        // add the credit notes
        foreach ($inputBag->all('CreditNote') as $clientId) {
            $amountOption = $amountOptions['CreditNote'][$clientId] ?? null;
            $amountOption = $amountOption ? PaymentAmountOption::from($amountOption) : null;
            $amount = $amounts['CreditNote'][$clientId] ?? null;
            $amount = $amount ? Money::fromDecimal($currency, $amount) : null;
            $builder->addCreditNoteFromClientId($clientId, $amountOption, $amount);
        }

        // add the credit balance
        if ($clientId = (string) $inputBag->get('CreditBalance')) {
            $amountOption = $amountOptions['CreditBalance'][$clientId] ?? null;
            $amountOption = $amountOption ? PaymentAmountOption::from($amountOption) : null;
            $amount = $amounts['CreditBalance'][$clientId] ?? null;
            $amount = $amount ? Money::fromDecimal($currency, $amount) : null;
            $builder->addCreditBalanceFromClientId($clientId, $amountOption, $amount);
        }

        // add the advance payment
        if ($clientId = (string) $inputBag->get('advance')) {
            $amountOption = $amountOptions['advance'][$clientId] ?? null;
            $amountOption = $amountOption ? PaymentAmountOption::from($amountOption) : null;
            $amount = $amounts['advance'][$clientId] ?? null;
            $amount = $amount ? Money::fromDecimal($currency, $amount) : null;
            $builder->addAdvancePaymentFromClientId($clientId, $amountOption, $amount);
        }

        // select the method from the saved source
        if ($isStoredMethod) {
            [, $sourceType, $sourceId] = explode(':', $methodId);
            $builder->setPaymentSource($sourceType, $sourceId);
        }

        $form = $builder->build();

        $method = $form->method;
        if (!$method) {
            throw new NotFoundHttpException('Payment method does not exist: '.$methodId);
        }

        if ('zero_amount' == $method->id && !$settings->allowApplyingCredits) {
            throw new NotFoundHttpException('Applying credits is not allowed');
        }

        return $form;
    }

    /**
     * Handles the submitted payment form.
     *
     * @throws FormException|ChargeDeclinedException when the submission fails
     * @throws AdyenCardException when no card details from Adyen provided
     */
    public function handleSubmit(PaymentForm $form, array $parameters): PromiseToPay|Payment|null
    {
        $method = $form->method;
        if (!($method instanceof PaymentMethod)) {
            throw new FormException('No payment method selected');
        }

        // check if the cvc code is provided
        if (!array_value($parameters, 'cvc') && 'card' == array_value($parameters, 'payment_source_type') && $form->company->accounts_receivable_settings->saved_cards_require_cvc) {
            throw new FormException('Please enter in the required CVC code');
        }

        // store the email address on the customer
        $customer = $form->customer;
        if (isset($parameters['email']) && !$customer->email) {
            $customer->email = $parameters['email'];
            $customer->save();
        }

        // check if we are enrolling the customer in AutoPay
        $enrollInAutoPay = array_value($parameters, 'enroll_autopay');
        $paymentSource = null;
        if ($enrollInAutoPay) {
            unset($parameters['enroll_autopay']);
            $paymentSource = $this->enrollInAutoPay($method, $form, $parameters);
        }

        // check if we are making this payment source the default
        if (array_value($parameters, 'make_default') && 'zero_amount' !== $method->id) {
            unset($parameters['make_default']);
            $paymentSource = $this->saveDefault($method, $customer, $parameters);
        }

        // charge a saved payment source when given
        if (isset($parameters['payment_source_type']) && isset($parameters['payment_source_id'])) {
            // look up the source
            $paymentSource = $this->getSavedPaymentSource($parameters['payment_source_type'], $parameters['payment_source_id'], $form->customer);
            unset($parameters['payment_source_type']);
            unset($parameters['payment_source_id']);
        }

        if (!isset($parameters['payment_flow'])) {
            throw new FormException('Missing payment flow identifier.');
        }
        /** @var ?PaymentFlow $paymentFlow */
        $paymentFlow = PaymentFlow::where('identifier', $parameters['payment_flow'])->oneOrNull();
        if (!$paymentFlow) {
            throw new FormException('The payment flow does not exist.');
        }

        // handle zero amount payments (applying a credit note)
        if ('zero_amount' == $method->id) {
            if (!$form->hasCredit()) {
                throw new FormException('No credit notes were selected');
            }

            $payment = $this->performZeroPayment($form);

            // send a notification
            $this->notificationSpool->spool(NotificationEventType::PaymentDone, $payment->tenant_id, $payment->id, $customer->id);

            return null;
        }

        // handle promise-to-pays
        $router = new PaymentRouter();
        $gateway = $router->getGateway($method, $customer, $form->documents);
        if (!$gateway) {
            $this->recordPromiseToPay($form, $parameters);

            return null;
        }

        $paymentFlow->setBeforePayment($method, $paymentSource, $parameters['email'] ?? null, $gateway);
        $parameters['identifier'] = $paymentFlow->identifier;

        // perform a charge through a payment gateway
        try {
            $payment = $this->performPayment($method, $form, $paymentFlow, $paymentSource, $parameters);
            if ($payment) {
                // send a notification
                $this->notificationSpool->spool(NotificationEventType::PaymentDone, $payment->tenant_id, $payment->id, $customer->id);

                // track the customer portal event
                $this->customerPortalEvents->track($customer, CustomerPortalEvent::SubmitPayment);
                $this->statsd->increment('billing_portal.payment');
            }

            return $payment;
        } catch (ChargeException $e) {
            $this->statsd->increment('billing_portal.failed_payment');

            // rethrow as a form exception
            throw new FormException($e->getMessage(), 0, $e);
        }
    }

    //
    // Helper methods
    //

    private function buildChargeApplication(PaymentForm $form): ChargeApplication
    {
        return (new ChargeApplicationBuilder())
            ->addPaymentForm($form)
            ->build();
    }

    /**
     * @throws FormException
     */
    private function performZeroPayment(PaymentForm $form): Payment
    {
        $chargeApplication = $this->buildChargeApplication($form);

        $amount = $chargeApplication->getPaymentAmount();
        // If not found then create a new transaction
        $payment = new Payment();
        foreach ($chargeApplication->paymentValues as $k => $v) {
            $payment->$k = $v;
        }
        $payment->setCustomer($form->customer);
        $payment->currency = $amount->currency;
        $payment->date = time();
        $payment->amount = $amount->toDecimal();
        $payment->method = PaymentMethod::OTHER;
        $payment->source = $chargeApplication->getPaymentSource()->toString();
        // build payment application splits
        $payment->applied_to = array_map(fn ($item) => $item->build(), $chargeApplication->getItems());
        if (!$payment->save()) {
            throw new FormException($payment->getErrors());
        }

        return $payment;
    }

    /**
     * Enrolls the customer in AutoPay and vaults the payment source.
     *
     * @throws FormException when the customer could not be enrolled
     */
    private function enrollInAutoPay(PaymentMethod $method, PaymentForm $form, array $parameters): PaymentSource
    {
        $customer = $form->customer;
        $customer->autopay = true;
        $saved = $customer->save();

        if (!$saved) {
            throw new FormException('Unable to enroll in AutoPay.');
        }

        // track the customer portal event
        $this->customerPortalEvents->track($customer, CustomerPortalEvent::AutoPayEnrollment);
        $this->statsd->increment('billing_portal.autopay_enrollment');

        // If the customer is paying from a saved source AND enrolling
        // in AutoPay then there is nothing to vault, so we skip that step.
        if ($paymentSource = $form->paymentSource) {
            return $paymentSource;
        }

        // save the incoming payment as the default payment source
        return $this->saveDefault($method, $customer, $parameters);
    }

    /**
     * Saves a token as the default payment source on the customer.
     *
     * @throws FormException when the payment source cannot be saved as default
     */
    private function saveDefault(PaymentMethod $method, Customer $customer, array $parameters): PaymentSource
    {
        // Check if setting an existing payment source to the default
        if (isset($parameters['payment_source_type']) && isset($parameters['payment_source_id'])) {
            $paymentSource = $this->getSavedPaymentSource($parameters['payment_source_type'], $parameters['payment_source_id'], $customer);
            $customer->setDefaultPaymentSource($paymentSource, $this->deletePaymentInfo);

            return $paymentSource;
        }

        try {
            return $this->vaultPaymentInfo->save($method, $customer, $parameters, true);
        } catch (PaymentSourceException $e) {
            // rethrow as a form exception
            throw new FormException($e->getMessage(), 0, $e);
        }
    }

    /**
     * Performs a charge against the payment gateway.
     *
     * @throws ChargeException when the charge attempt fails
     */
    private function performPayment(PaymentMethod $method, PaymentForm $form, PaymentFlow $paymentFlow, ?PaymentSource $paymentSource, array $parameters): ?Payment
    {
        if (!$method->enabled()) {
            throw new ChargeException('Payment method is not enabled: '.$method->id);
        }

        $chargeApplication = $this->buildChargeApplication($form);

        // Pay with a saved payment source if given.
        if ($paymentSource) {
            return $this->processPayment->payWithSource($paymentSource, $chargeApplication, $parameters, $paymentFlow);
        }

        // If no payment source is given then this is a one-time payment.
        return $this->processPayment->pay($method, $form->customer, $chargeApplication, $parameters, $paymentFlow);
    }

    /**
     * @throws FormException
     */
    private function getSavedPaymentSource(string $type, string $id, Customer $customer): PaymentSource
    {
        if (ObjectType::Card->typeName() == $type) {
            $source = Card::where('id', $id)
                ->where('customer_id', $customer)
                ->where('chargeable', true)
                ->oneOrNull();
        } elseif (ObjectType::BankAccount->typeName() == $type) {
            $source = BankAccount::where('id', $id)
                ->where('customer_id', $customer)
                ->where('chargeable', true)
                ->oneOrNull();
        } else {
            throw new FormException('Unrecognized payment type: '.$type);
        }

        if (!($source instanceof PaymentSource)) {
            throw new FormException('Could not locate payment information');
        }

        return $source;
    }

    /**
     * Records a promise to pay.
     *
     * @throws FormException
     */
    private function recordPromiseToPay(PaymentForm $form, array $parameters): void
    {
        // parse the date
        $dateStr = array_value($parameters, 'date');
        $date = (new CarbonImmutable($dateStr))->setTime(18, 0);

        // the expected date cannot be before Aug 25, 2015
        // when this feature was built
        if ($date->getTimestamp() <= 1440553864) {
            throw new FormException('We could not validate the expected arrival date');
        }

        $method = array_value($parameters, 'method');
        $reference = array_value($parameters, 'reference');
        $notes = array_value($parameters, 'notes');

        // set the promise to pay on each invoice
        foreach ($form->documents as $document) {
            if (!$document instanceof Invoice) {
                continue;
            }

            $promiseToPay = PromiseToPay::where('invoice_id', $document)
                ->sort('id DESC')
                ->oneOrNull();

            if (!$promiseToPay) {
                $promiseToPay = new PromiseToPay();
                $promiseToPay->invoice = $document;
                $customer = $form->customer;
                $promiseToPay->customer = $customer;
            }

            $promiseToPay->currency = $document->currency;
            $promiseToPay->amount = $document->balance;
            $promiseToPay->method = $method;
            $promiseToPay->reference = $reference;
            $promiseToPay->date = $date->getTimestamp();
            $promiseToPay->saveOrFail();

            if ($notes) {
                $note = new Note();
                $note->invoice = $document;
                $note->user = null;
                $note->notes = trim($notes);
                $note->saveOrFail();
            }

            $this->notificationSpool->spool(NotificationEventType::PromiseCreated, $promiseToPay->tenant_id, $promiseToPay->id, $promiseToPay->customer_id);
        }

        // track the customer portal event
        $this->customerPortalEvents->track($form->customer, CustomerPortalEvent::PromiseToPay);
        $this->statsd->increment('billing_portal.promise_to_pay');
    }
}
