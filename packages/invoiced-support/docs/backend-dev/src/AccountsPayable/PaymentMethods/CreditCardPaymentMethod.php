<?php

namespace App\AccountsPayable\PaymentMethods;

use App\AccountsPayable\Exception\AccountsPayablePaymentException;
use App\AccountsPayable\Interfaces\AccountsPayablePaymentMethodInterface;
use App\AccountsPayable\Models\CompanyCard;
use App\AccountsPayable\Models\VendorPayment;
use App\AccountsPayable\ValueObjects\PayVendorPayment;
use App\AccountsReceivable\Models\Customer;
use App\AccountsReceivable\Models\Invoice;
use App\Companies\Models\Company;
use App\Core\Multitenant\TenantContext;
use App\Integrations\Stripe\HasStripeClientTrait;
use App\Network\Models\NetworkConnection;
use App\PaymentProcessing\Enums\PaymentFlowSource;
use App\PaymentProcessing\Exceptions\ChargeException;
use App\PaymentProcessing\Models\PaymentMethod;
use App\PaymentProcessing\Operations\ProcessPayment;
use App\PaymentProcessing\ValueObjects\ChargeApplication;
use App\PaymentProcessing\ValueObjects\CreditChargeApplicationItem;
use App\PaymentProcessing\ValueObjects\InvoiceChargeApplicationItem;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Stripe\Exception\ExceptionInterface;

class CreditCardPaymentMethod implements AccountsPayablePaymentMethodInterface, LoggerAwareInterface
{
    use LoggerAwareTrait;
    use HasStripeClientTrait;

    private VendorPayment $createdPayment;

    public function __construct(
        string $stripePlatformSecret,
        private TenantContext $tenant,
        private ProcessPayment $processPayment,
    ) {
        $this->stripeSecret = $stripePlatformSecret;
    }

    public function pay(PayVendorPayment $payment, array $options): VendorPayment
    {
        if (!isset($options['card'])) {
            throw new AccountsPayablePaymentException('Missing payment card.');
        }

        /** @var CompanyCard $card */
        $card = $options['card'];

        // Look up the payment processing configuration of the vendor
        $networkConnection = $payment->vendor->network_connection;
        if (!$networkConnection) {
            throw new AccountsPayablePaymentException('Vendor must be in your network to submit a payment.');
        }
        $vendorCompany = $networkConnection->vendor;
        $paymentMethod = $this->getPaymentMethod($vendorCompany);

        $this->processCard($payment, $card, $paymentMethod, $vendorCompany, $networkConnection);

        // Obtain the vendor payment created by the event subscriber
        return $this->createdPayment;
    }

    private function getPaymentMethod(Company $vendorCompany): PaymentMethod
    {
        $paymentMethod = PaymentMethod::queryWithTenant($vendorCompany)
            ->where('id', PaymentMethod::CREDIT_CARD)
            ->where('enabled', true)
            ->where('gateway', 'stripe')
            ->oneOrNull();
        if (!$paymentMethod) {
            throw new AccountsPayablePaymentException('Vendor does not have credit card processing enabled.');
        }

        return $paymentMethod;
    }

    /**
     * @throws AccountsPayablePaymentException
     */
    private function processCard(PayVendorPayment $payment, CompanyCard $card, PaymentMethod $paymentMethod, Company $vendorCompany, NetworkConnection $networkConnection): void
    {
        try {
            $this->tenant->runAs($vendorCompany, function () use ($payment, $card, $paymentMethod, $networkConnection) {
                $merchantAccount = $paymentMethod->getDefaultMerchantAccount();

                // Clone the payment method to the vendor's Stripe account
                // and create a new Card in our database
                $stripe = $this->getStripe();
                $clonedMethod = $stripe->paymentMethods->create([
                    'customer' => $card->stripe_customer,
                    'payment_method' => $card->stripe_payment_method,
                ], [
                    'stripe_account' => $merchantAccount->gateway_id,
                ]);

                // Look up customer
                $customer = Customer::where('network_connection_id', $networkConnection)->oneOrNull();
                if (!$customer) {
                    throw new AccountsPayablePaymentException('Could not find matching customer through network connection');
                }

                // Build the charge application
                $chargeApplication = $this->buildChargeApplication($payment);

                // Process the payment against the saved card
                $parameters = [
                    'gateway_token' => $clonedMethod->id,
                ];
                $this->processPayment->pay($paymentMethod, $customer, $chargeApplication, $parameters, null);
            });
        } catch (ExceptionInterface $e) {
            $this->logger->error('A Stripe error occurred', ['exception' => $e]);

            throw new AccountsPayablePaymentException('An unknown error has occurred');
        } catch (ChargeException $e) {
            throw new AccountsPayablePaymentException($e->getMessage());
        }
    }

    private function buildChargeApplication(PayVendorPayment $payment): ChargeApplication
    {
        $items = [];

        foreach ($payment->getItems() as $item) {
            // Attempt to find the matching invoice through the network document association
            if ($networkDocumentId = $item->bill->network_document_id) {
                $invoice = Invoice::where('network_document_id', $networkDocumentId)->oneOrNull();
                if ($invoice) {
                    $items[] = new InvoiceChargeApplicationItem($item->amount, $invoice, $item->bill);
                    continue;
                }
            }

            // When a matching invoice is not found then the payment is created as an overpayment
            $items[] = new CreditChargeApplicationItem($item->amount, payableDocument: $item->bill);
        }

        return new ChargeApplication($items, PaymentFlowSource::Network);
    }

    public function setCreatedPayment(VendorPayment $payment): void
    {
        $this->createdPayment = $payment;
    }
}
