<?php

namespace App\Integrations\Adyen\Operations;


use App\AccountsReceivable\Models\Customer;
use App\Integrations\Adyen\Models\AdyenPaymentResult;
use App\PaymentProcessing\Gateways\AdyenGateway;
use App\PaymentProcessing\Models\MerchantAccount;
use App\PaymentProcessing\Models\PaymentFlow;
use App\PaymentProcessing\Operations\DeletePaymentInfo;
use App\PaymentProcessing\Reconciliation\PaymentSourceReconciler;
use App\PaymentProcessing\ValueObjects\CardValueObject;

class SaveAlreadyExistingPaymentSource
{
    public function __construct(
        private readonly PaymentSourceReconciler $paymentSourceReconciler,
        private readonly DeletePaymentInfo $deletePaymentInfo
    ) {
    }

    public function process(MerchantAccount $merchantAccount, Customer $customer, string $reference): void
    {
        $flow = PaymentFlow::where('identifier', $reference)->oneOrNull();
        $adyenResult = AdyenPaymentResult::where('reference', $reference)->oneOrNull();

        if (!$adyenResult || !$flow?->make_payment_source_default || !$customer->autopay) {
            return;
        }

        $data = json_decode($adyenResult->result, true);

        if (
            !$data ||
            !isset($data['additionalData']) ||
            $data['additionalData']['tokenization.store.operationType'] !== 'alreadyExisting'
        ) {
            return;
        }

        $token = $data['additionalData']['tokenization.storedPaymentMethodId'] ?? null;
        if (!$token) {
            return;
        }

        $expiry = $data['additionalData']['expiryDate'] ?? '';
        $expiryParts = explode('/', $expiry);

        $card = new CardValueObject(
            customer: $customer,
            gateway: AdyenGateway::ID,
            gatewayId: $token,
            gatewayCustomer: $customer->client_id,
            merchantAccount: $merchantAccount,
            chargeable: true,
            receiptEmail: $customer->email,
            brand: $data['paymentMethod']['brand'] ?? 'Unknown',
            funding: $data['additionalData']['funding'] ?? 'unknown',
            last4: $data['additionalData']['cardSummary'] ?? '0000',
            expMonth: (int)($expiryParts[0] ?? 12),
            expYear: (int)($expiryParts[1] ?? date('Y')),
            country: $data['additionalData']['cardIssuingCountry'] ?? 'US',
        );

        $paymentSource = $this->paymentSourceReconciler->reconcile($card);
        $customer->setDefaultPaymentSource($paymentSource, $this->deletePaymentInfo);
    }
}
