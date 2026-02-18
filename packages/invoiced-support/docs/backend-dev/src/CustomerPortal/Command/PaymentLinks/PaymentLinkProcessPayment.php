<?php

namespace App\CustomerPortal\Command\PaymentLinks;

use App\AccountsReceivable\Models\Customer;
use App\AccountsReceivable\Models\Invoice;
use App\CashApplication\Models\Payment;
use App\Core\I18n\ValueObjects\Money;
use App\Core\Statsd\Interfaces\StatsdAwareInterface;
use App\Core\Statsd\StatsdAwareTrait;
use App\Core\Utils\Enums\ObjectType;
use App\CustomerPortal\Enums\CustomerPortalEvent;
use App\CustomerPortal\Exceptions\PaymentLinkException;
use App\CustomerPortal\Libs\CustomerPortalEvents;
use App\CustomerPortal\ValueObjects\PaymentLinkResult;
use App\PaymentProcessing\Enums\PaymentFlowSource;
use App\PaymentProcessing\Exceptions\ChargeDeclinedException;
use App\PaymentProcessing\Exceptions\ChargeException;
use App\PaymentProcessing\Libs\PaymentRouter;
use App\PaymentProcessing\Models\BankAccount;
use App\PaymentProcessing\Models\Card;
use App\PaymentProcessing\Models\PaymentFlow;
use App\PaymentProcessing\Models\PaymentMethod;
use App\PaymentProcessing\Models\PaymentSource;
use App\PaymentProcessing\Operations\ProcessPayment;
use App\PaymentProcessing\ValueObjects\ChargeApplication;
use App\PaymentProcessing\ValueObjects\InvoiceChargeApplicationItem;

class PaymentLinkProcessPayment implements StatsdAwareInterface
{
    use StatsdAwareTrait;

    public function __construct(
        private ProcessPayment $processPayment,
        private CustomerPortalEvents $customerPortalEvents,
    ) {
    }

    /**
     * Processes the payment for a payment link submission.
     *
     * @throws PaymentLinkException
     */
    public function process(PaymentLinkResult $result, Money $amount, array $parameters): void
    {
        $paymentSourceParameters = $parameters['payment_source'] ?? [];
        $customer = $result->getCustomer();
        $company = $customer->tenant();
        $invoice = $result->getInvoice();
        $documents = [$invoice];

        $paymentFlow = $result->getPaymentFlow();
        // Determine the payment gateway to use
        // (saved methods are treated differently)
        $paymentSource = null;
        if (isset($paymentSourceParameters['payment_source_type']) && isset($paymentSourceParameters['payment_source_id'])) {
            $paymentSource = $this->getSavedPaymentSource($paymentSourceParameters['payment_source_type'], $paymentSourceParameters['payment_source_id'], $customer);
            $method = $paymentSource->getPaymentMethod();
            unset($paymentSourceParameters['payment_source_type']);
            unset($paymentSourceParameters['payment_source_id']);
        } else {
            $methodId = (string) ($paymentSourceParameters['method'] ?? '');
            $method = PaymentMethod::instance($company, $methodId);
            $router = new PaymentRouter();
            $gateway = $router->getGateway($method, $customer, $documents);
            if (!$gateway) {
                throw new PaymentLinkException('Missing payment gateway');
            }
        }

        $paymentFlow->setBeforePayment($method, $paymentSource, $parameters['receipt_email'] ?? null, $gateway ?? null);
        $paymentSourceParameters['identifier'] = $paymentFlow->identifier;

        // perform a charge through a payment gateway
        try {
            $payment = $this->performPayment($method, $customer, $invoice, $amount, $paymentSource, $paymentSourceParameters, $paymentFlow);
            $result->setPayment($payment);
        } catch (ChargeException|ChargeDeclinedException $e) {
            // rethrow as a form exception
            throw new PaymentLinkException($e->getMessage(), 0, $e);
        }

        // track the customer portal event
        $this->customerPortalEvents->track($customer, CustomerPortalEvent::SubmitPayment);
        $this->statsd->increment('billing_portal.payment');
    }

    private function buildChargeApplication(Invoice $invoice, Money $amount): ChargeApplication
    {
        $items = [
            new InvoiceChargeApplicationItem($amount, $invoice),
        ];

        return new ChargeApplication($items, PaymentFlowSource::CustomerPortal);
    }

    /**
     * Performs a charge against the payment gateway.
     *
     * @throws PaymentLinkException
     * @throws ChargeException|ChargeDeclinedException when the charge attempt fails
     */
    private function performPayment(PaymentMethod $method, Customer $customer, Invoice $invoice, Money $amount, ?PaymentSource $paymentSource, array $parameters, PaymentFlow $paymentFlow): ?Payment
    {
        if (!$method->enabled()) {
            throw new ChargeException('Payment method is not enabled: '.$method->id);
        }

        $chargeApplication = $this->buildChargeApplication($invoice, $amount);

        // Pay with a saved payment source if given.
        if ($paymentSource) {
            return $this->processPayment->payWithSource($paymentSource, $chargeApplication, $parameters, $paymentFlow);
        }

        // If no payment source is given then this is a one-time payment.
        return $this->processPayment->pay($method, $customer, $chargeApplication, $parameters, $paymentFlow);
    }

    /**
     * @throws PaymentLinkException
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
            throw new PaymentLinkException('Unrecognized payment type: '.$type);
        }

        if (!($source instanceof PaymentSource)) {
            throw new PaymentLinkException('Could not locate payment information');
        }

        return $source;
    }
}
