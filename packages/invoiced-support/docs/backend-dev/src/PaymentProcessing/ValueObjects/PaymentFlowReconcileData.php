<?php

namespace App\PaymentProcessing\ValueObjects;

use App\AccountsReceivable\Models\Customer;
use App\Core\I18n\ValueObjects\Money;
use App\Integrations\Adyen\Models\AdyenPaymentResult;
use App\Integrations\Flywire\Enums\FlywirePaymentStatus;
use App\PaymentProcessing\Gateways\AdyenGateway;
use App\PaymentProcessing\Gateways\FlywireGateway;
use App\PaymentProcessing\Gateways\StripeGateway;
use App\PaymentProcessing\Models\Charge;
use App\PaymentProcessing\Models\MerchantAccount;
use App\PaymentProcessing\Models\PaymentMethod;

class PaymentFlowReconcileData
{
    public function __construct(
        public string $gateway,
        public string $status,
        public string $gatewayId,
        public Money $amount,
        public string $brand = '',
        public string $funding = 'unknown',
        public string $last4 = '0000',
        public string $expiry = '',
        public ?string $failureReason = null,
        public ?string $country = null,
        public ?string $cardGateway = null,
        public ?string $cardCustomerGateway = null,
        public string $bankName = 'Unknown',
        public ?string $routingNumber = null,
        public string $currency = 'USD',
        public ?string $accountHolderName = null,
        public ?string $accountHolderType = null,
        public ?string $type = null,
        public bool $verified = true,
    ) {
    }

    public static function fromAdyenResult(AdyenPaymentResult $result, ?Money $amount = null): self
    {
        $result = json_decode($result->result, true);
        $method = $result['paymentMethod']['type'] ?? $result['paymentMethod'] ?? null;
        // Source "Final state result": https://docs.adyen.com/online-payments/build-your-integration/payment-result-codes/
        $status = match ($result['resultCode']) {
            'Authorised' => in_array($method, [PaymentMethod::ACH, PaymentMethod::AFFIRM, PaymentMethod::KLARNA])  ? ChargeValueObject::PENDING : ChargeValueObject::SUCCEEDED,
            default => ChargeValueObject::FAILED,
        };

        $funding = match (strtolower($result['additionalData']['fundingSource'] ?? 'unknown')) {
            'debit', 'deffered_debit' => 'debit',
            'credit' => 'credit',
            default => 'unknown',
        };

        return new self(
            gateway: AdyenGateway::ID,
            status: $status,
            gatewayId: $result['pspReference'],
            amount: $amount ?? new Money(
                $result['amount']['currency'],
                $result['amount']['value']
            ),
            brand: $result['paymentMethod']['brand'] ?? 'Unknown',
            funding: $funding,
            last4: $result['additionalData']['cardSummary'] ?? '0000',
            expiry: $result['additionalData']['expiryDate'] ?? '',
            failureReason: $result['refusalReason'] ?? null,
            country: $result['additionalData']['cardIssuingCountry'] ?? null,
            cardGateway: $result['additionalData']['recurring.recurringDetailReference'] ?? $result['additionalData']['tokenization.storedPaymentMethodId'] ?? null,
            cardCustomerGateway: $result['additionalData']['recurring.shopperReference'] ?? $result['additionalData']['tokenization.shopperReference'] ?? null,
        );
    }

    public static function fromFlywire(array $data): self
    {
        $status = match (FlywirePaymentStatus::fromString($data['status'])) {
            FlywirePaymentStatus::Initiated, FlywirePaymentStatus::Processed => Charge::PENDING,
            FlywirePaymentStatus::Guaranteed, FlywirePaymentStatus::Delivered => Charge::SUCCEEDED,
            FlywirePaymentStatus::Failed, FlywirePaymentStatus::Canceled, FlywirePaymentStatus::Reversed => Charge::FAILED,
        };

        $country = 'US';
        $firstName = '';
        $lastName = '';
        $middleName = '';
        foreach ($data['sender']['fields'] ?? [] as $field) {
            if ($field['id'] === 'first_name') {
                $firstName = $field['value'];
            } elseif ($field['id'] === 'last_name') {
                $lastName = $field['value'];
            } elseif ($field['id'] === 'middle_name') {
                $middleName = $field['value'];
            } elseif ($field['id'] === 'country') {
                $country = $field['value'];
            }
        }

        $methodDetails = $data['charge_intent']['payment_method_details'] ?? $data['charge_intent'] ?? [];

        return new self(
            gateway: FlywireGateway::ID,
            status: $status,
            gatewayId: $data['id'],
            amount: new Money(
                $data['purchase']['currency']['code'],
                $data['purchase']['value'] ?? 0
            ),
            brand: $methodDetails['brand'] ?? 'Unknown',
            funding: strtolower($methodDetails['card_classification'] ?? 'unknown'),
            last4: $methodDetails['last_four_digits'] ?? '0000',
            expiry: $methodDetails['card_expiration'] ?? '',
            failureReason: null,
            country: $country,
            currency: $data['price']['currency']['code'] ?? 'USD',
            accountHolderName: implode(" ", array_filter([$firstName, $middleName, $lastName])) ?: null,
        );
    }

    public static function fromStripe(array $data): self
    {
        $details = $data['payment_method_details']['card'] ?? [];

        $exp = ($details['exp_month'] ?? null) && ($details['exp_year'] ?? null)
            ? str_pad($details['exp_month'], 2, '0').'/'.$details['exp_year']
            : '';

        return new self(
            gateway: StripeGateway::ID,
            status: $data['invoicedStatus']['status'],
            gatewayId: $data['id'],
            amount: new Money(
                $data['currency'],
                $data['amount'] ?? 0
            ),
            brand: $details['brand'] ?? 'Unknown',
            funding: strtolower($details['funding'] ?? 'unknown'),
            last4: $details['last4'] ?? '0000',
            expiry: $exp,
            failureReason: $data['invoicedStatus']['failureMessage'] ?: '',
            country: $details['country'] ?? null,
            cardGateway: $data['payment_method'] ?? null,
            cardCustomerGateway: $data['customer'] ?? null,
        );
    }

    private function toCardValueObject(Customer $customer, MerchantAccount $account, bool $chargeable, ?string $receiptEmail): CardValueObject
    {
        $expiryParts = explode('/', $this->expiry ?: '12/'.date('Y'));
        return new CardValueObject(
            customer: $customer,
            gateway: $this->gateway,
            gatewayId: $this->cardGateway,
            gatewayCustomer: $this->cardCustomerGateway,
            merchantAccount: $account,
            chargeable: $chargeable,
            receiptEmail: $receiptEmail,
            brand: $this->brand,
            funding: $this->funding,
            last4: $this->last4,
            expMonth: (int) $expiryParts[0],
            expYear: (int) $expiryParts[1],
            country: $this->country,
        );
    }
    private function toBankAccountValueObject(Customer $customer, MerchantAccount $account, bool $chargeable, ?string $receiptEmail): BankAccountValueObject
    {
        return new BankAccountValueObject(
            customer: $customer,
            gateway: $this->gateway,
            gatewayId: $this->cardGateway,
            gatewayCustomer: $this->cardCustomerGateway,
            merchantAccount: $account,
            chargeable: $chargeable,
            receiptEmail: $receiptEmail,
            bankName: $this->bankName,
            routingNumber: $this->routingNumber,
            last4: $this->last4,
            currency: $this->currency,
            country: $this->country ?? 'US',
            accountHolderName: $this->accountHolderName,
            accountHolderType: $this->accountHolderType,
            type: $this->type,
            verified: $this->verified,
        );
    }

    public function toSourceValueObject(Customer $customer, MerchantAccount $account, bool $chargeable, ?string $receiptEmail, string $method = PaymentMethod::CREDIT_CARD): SourceValueObject
    {
        return PaymentMethod::CREDIT_CARD === $method ? $this->toCardValueObject($customer, $account, $chargeable, $receiptEmail) : $this->toBankAccountValueObject($customer, $account, $chargeable, $receiptEmail);
    }

}
