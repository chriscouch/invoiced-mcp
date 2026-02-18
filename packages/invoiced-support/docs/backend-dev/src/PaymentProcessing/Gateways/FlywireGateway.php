<?php

namespace App\PaymentProcessing\Gateways;

use App\AccountsReceivable\Models\Customer;
use App\AccountsReceivable\Models\Estimate;
use App\AccountsReceivable\Models\Invoice;
use App\Core\I18n\ValueObjects\Money;
use App\Core\Utils\RandomString;
use App\Integrations\Exceptions\IntegrationApiException;
use App\Integrations\Flywire\FlywireHelper;
use App\Integrations\Flywire\FlywirePrivateClient;
use App\Integrations\Flywire\FlywireRefundApproveClient;
use App\PaymentProcessing\Enums\PaymentMethodType;
use App\PaymentProcessing\Exceptions\ChargeException;
use App\PaymentProcessing\Exceptions\InvalidGatewayConfigurationException;
use App\PaymentProcessing\Exceptions\PaymentSourceException;
use App\PaymentProcessing\Exceptions\RefundException;
use App\PaymentProcessing\Interfaces\OneTimeChargeInterface;
use App\PaymentProcessing\Interfaces\PaymentGatewayInterface;
use App\PaymentProcessing\Interfaces\PaymentSourceVaultInterface;
use App\PaymentProcessing\Interfaces\RefundInterface;
use App\PaymentProcessing\Libs\GatewayHelper;
use App\PaymentProcessing\Models\MerchantAccount;
use App\PaymentProcessing\Models\PaymentSource;
use App\PaymentProcessing\ValueObjects\BankAccountValueObject;
use App\PaymentProcessing\ValueObjects\CardValueObject;
use App\PaymentProcessing\ValueObjects\ChargeValueObject;
use App\PaymentProcessing\ValueObjects\PaymentGatewayConfiguration;
use App\PaymentProcessing\ValueObjects\RefundValueObject;
use App\PaymentProcessing\ValueObjects\SourceValueObject;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;

class FlywireGateway implements PaymentGatewayInterface, OneTimeChargeInterface, PaymentSourceVaultInterface, RefundInterface, LoggerAwareInterface
{
    use LoggerAwareTrait;

    const ID = 'flywire';

    private const string CREDIT = 'credit';

    public static function getId(): string
    {
        return self::ID;
    }

    public function __construct(
        private readonly FlywirePrivateClient $client,
        private readonly FlywireRefundApproveClient $refundApproveClient,
    ) {
    }

    public function validateConfiguration(PaymentGatewayConfiguration $gatewayConfiguration): void
    {
        if (!isset($gatewayConfiguration->credentials->flywire_portal_codes)) {
            throw new InvalidGatewayConfigurationException('Missing Flywire portal codes configuration!');
        }

        if (!isset($gatewayConfiguration->credentials->shared_secret)) {
            throw new InvalidGatewayConfigurationException('Missing Flywire shared secret!');
        }
    }

    public function charge(Customer $customer, MerchantAccount $account, Money $amount, array $parameters, string $description, array $documents = []): ChargeValueObject
    {
        // Validate the signature of the payment in order to confirm the provided details.
        // It might also be possible to confirm the payment using the internal API.
        $this->validateSignature();

        $flywireAmount = Money::fromDecimal($amount->currency, $parameters['flywireAmount']);
        if (!$flywireAmount->equals($amount)) {
            throw new ChargeException('Amount mismatch.');
        }

        return $this->buildCharge($customer, $flywireAmount, $parameters, $account, null, $description);
    }

    public function vaultSource(Customer $customer, MerchantAccount $account, array $parameters): SourceValueObject
    {
        if (!isset($parameters['token']) || !$parameters['token']) {
            throw new PaymentSourceException('Vaulting sources is not supported.');
        }

        if (self::CREDIT == $parameters['type']) {
            return new CardValueObject(
                customer: $customer,
                gateway: $account->gateway,
                gatewayId: $parameters['token'],
                merchantAccount: $account,
                chargeable: true,
                brand: $parameters['brand'] ?? 'Unknown',
                funding: 'unknown',
                last4: $parameters['digits'] ?? '0000',
                expMonth: (int) $parameters['expirationMonth'],
                expYear: (int) $parameters['expirationYear'],
            );
        }

        $tenant = $customer->tenant();

        return new BankAccountValueObject(
            customer: $customer,
            gateway: $account->gateway,
            gatewayId: $parameters['token'],
            merchantAccount: $account,
            chargeable: true,
            last4: $parameters['digits'] ?? '0000',
            currency: $customer->currency ?? $tenant->currency,
            country: $customer->country ?? $tenant->country ?? 'US',
            verified: true,
        );
    }

    public function chargeSource(PaymentSource $source, Money $amount, array $parameters, string $description, array $documents = []): ChargeValueObject
    {
        // This branch prevents a double charge when a payment form also has "Save payment method", "Enroll in AutoPay", etc checked.
        // The Flywire Checkout modal has already charged the tokenized payment method. The customer should not be charged again in this scenario.
        $merchantAccount = $source->getMerchantAccount();
        if (isset($parameters['save_flywire_method']) && $parameters['save_flywire_method']) {
            return $this->buildCharge($source->customer, $amount, $parameters, $merchantAccount, $source, $description);
        }

        $id = $parameters['identifier'] ?? $parameters['payment_flow'] ?? $source->customer->client_id . '-' . RandomString::generate(24);
        try {
            $level3 = $this->makeLevel3($merchantAccount, $source->customer, $amount, $documents);
            $data = $this->getData($source, $merchantAccount, $amount, $id, $parameters, $level3, $documents);
            $data = $this->client->pay($merchantAccount, $id, $data);
        } catch (IntegrationApiException $e) {
            $message = $e->getMessage();
            $charge = $this->buildCharge($source->customer, $amount, [
                'status' => 'error',
                'reference' => $id,
                'failureReason' => $message,
            ], $merchantAccount, $source, $description);
            throw new ChargeException($message, $charge);
        }

        return $this->buildCharge($source->customer, $amount, $data, $merchantAccount, $source, $description);
    }

    public function getData(PaymentSource $source, MerchantAccount $account, Money $amount, string $id, array $parameters, array $level3, array $documents = []): array
    {
        $overrides = [];
        if (isset($parameters['injected_data'])) {
            $overrides = json_decode(base64_decode($parameters['injected_data']), true) ?: [];
        }
        $portalCode = FlywireHelper::getPortalCodeForCurrency($account, $amount->currency);

        $invoiceNumbers = implode(", ", array_map(fn($item) => $item->number,
            array_filter($documents,
                fn($item) => ($item instanceof Invoice) || ($item instanceof Estimate)
            )
        ));

        $regex = '/[^a-zA-Z0-9-_\s]/';

        $data = [
            'amount' => (string) $amount->toDecimal(),
            'token' => $source->gateway_id,
            'recipient' => [
                // portal codes may include space, we do not want it
                'id' => str_replace(' ', '', (string) $portalCode),
                'fields' => [
                    [
                        'id' => 'customer_name',
                        'value' => preg_replace($regex, "", $source->customer->name),
                    ],
                    [
                        'id' => 'customer_number',
                        'value' => preg_replace($regex, "", $source->customer->number),
                    ],
                    [
                        'id' => 'invoice_number',
                        'value' => preg_replace($regex, "", $invoiceNumbers),
                    ],
                    [
                        'id' => 'invoiced_ref',
                        'value' => $id,
                    ],
                ]
            ],
            'surchargeConfig' => [
                'enable' => $account->tenant()->features->has('flywire_surcharging') && $source->customer->surcharging,
            ],
            'paymentMethodProcessingData' => $level3,
        ];

        foreach ($overrides as $key => $value) {
            if (null === $value) {
                unset($data[$key]);

                continue;
            }

            // normalize payables to minor unit format,
            // smth that Flywire API expects
            if ('payables' == $key) {
                $value = array_map(function ($item) use ($amount) {
                    $item['amount'] = Money::fromDecimal($amount->currency, $item['amount'])->amount;

                    return $item;
                }, $value);
            }

            $data[$key] = $value;
        }

        return $data;
    }

    public function deleteSource(MerchantAccount $account, PaymentSource $source): void
    {
        // Flywire does not support deleting payment information. Do nothing to let it
        // be deleted from our database.
    }

    public function refund(MerchantAccount $merchantAccount, string $chargeId, Money $amount): RefundValueObject
    {
        try {
            $data = $this->client->refund($merchantAccount, $chargeId, $amount);
        } catch (IntegrationApiException $e) {
            throw new RefundException($e->getMessage());
        }

        try {
            if (!empty($data['id'])) {
                $this->refundApproveClient->approveRefund($data['id']); // response is empty if all good [status code: 200]
            } else {
                $this->logger->error('Unexpected situation: Flywire refund initiated but there is no id for approval, $chargeId: ' .
                    $chargeId . ', $payload: ' . json_encode($data));
            }
        } catch (IntegrationApiException $e) {
            $this->logger->error('Error occurred on Flywire refund approval, $e: ' . $e->getMessage());
        }

        foreach ($data['requests'] as $request) {
            if ($request['refund_reference'] !== $chargeId) {
                return $this->buildRefund($request['refund_reference'], $amount);
            }
        }

        throw new RefundException('Your refund has been processed, but could not be reconciled. Please contact support.');
    }

    // we should remove following code branch
    // when Flywire will fix the bug with missing signature for recurring requests
    // INV-175, another iteration, now signature is missing completely
    private function validateSignature(): void
    {
    }

    private function buildCharge(Customer $customer, Money $amount, array $parameters, MerchantAccount $merchantAccount, ?PaymentSource $source, string $description): ChargeValueObject
    {
        $method = $source?->getMethod();
        if (!$method) {
            $method = match ($parameters['paymentMethod'] ?? '') {
                'bank_transfer' => PaymentMethodType::BankTransfer->toString(),
                'direct_debit' => PaymentMethodType::DirectDebit->toString(),
                'online' => PaymentMethodType::Online->toString(),
                default => PaymentMethodType::Card->toString(),
            };
        }

        $status = match ($parameters['status'] ?? '') {
            'success' => ChargeValueObject::SUCCEEDED,
            'error' => ChargeValueObject::FAILED,
            default => ChargeValueObject::PENDING,
        };

        return new ChargeValueObject(
            customer: $customer,
            amount: $amount,
            gateway: FlywireGateway::ID,
            gatewayId: $parameters['reference'],
            method: $method,
            status: $status,
            merchantAccount: $merchantAccount,
            source: $source,
            description: $description,
            failureReason:  $parameters['failureReason'] ?? null,
        );
    }

    private function buildRefund(string $refundId, Money $amount): RefundValueObject
    {
        return new RefundValueObject(
            amount: $amount,
            gateway: FlywireGateway::ID,
            gatewayId: $refundId,
            status: RefundValueObject::PENDING,
        );
    }

    public function makeLevel3(MerchantAccount $merchantAccount, Customer $customer, Money $amount, array $documents): array {
        $level3 = GatewayHelper::makeLevel3($documents, $customer, $amount);

        $currency = $amount->currency;
        $total = $level3->shipping; //start with the shipping amount
        $shipping = $level3->shipping;
        $salesTax = $level3->salesTax;
        $discounts = Money::zero($currency);
        $duty = Money::zero($currency);
        $subtotal = $amount->subtract($salesTax)->subtract($shipping);

        $lineItems = [];
        foreach ($level3->lineItems as $lineItem) {
            // Cpayex does not allow for a decimal to be used for quantity
            // recalculate total using an integer quantity. Any extra amount
            // will be added with the adjustment line item.
            $quantity = (int) floor($lineItem->quantity);
            $discount = $lineItem->discount;
            $unitCost = $lineItem->unitCost;
            $lineTotal = Money::fromDecimal($currency, $quantity * $unitCost->toDecimal())->subtract($discount);

            // prorate tax across line items, as required for Level 3
            $prorationFactor = !$subtotal->isZero() ? floor($lineTotal->toDecimal() / $subtotal->toDecimal() * 100) / 100 : 1;
            $lineItemTax = Money::fromDecimal($currency, $level3->salesTax->toDecimal() * $prorationFactor);
            $total = $total->add($lineTotal->add($lineItemTax));
            $discounts = $discount->add($discount);

            $regex = '/[^a-zA-Z0-9-_\s]/';

            $lineItems[] = [
                'product_code' => preg_replace($regex, "", $lineItem->productCode),
                'description' => preg_replace($regex, "", $lineItem->description),
                'quantity' => $quantity,
                'unit_of_measure' => $lineItem->unitOfMeasure,
                'tax_amount' => $lineItemTax->amount,
                'discount_amount' => $discount->amount,
                'unit_price' => $unitCost->amount,
                'total_amount' => $lineTotal->amount,
                'total_amount_with_tax' => $lineTotal->add($lineItemTax)->amount
            ];
        }

        $difference = $amount->subtract($total);
        if (!$difference->isZero()) {
            $lineItems[] = [
                'product_code' => 'Adjustment',
                'description' => 'Adjustment',
                'quantity' => 1,
                'unit_of_measure' => 'EA',
                'tax_amount' => 0,
                'discount_amount' => 0,
                'unit_price' => $difference->amount,
                'total_amount' => $difference->amount,
                'total_amount_with_tax' => $difference->amount
            ];
        }

        return [
            'customer_reference' => $merchantAccount->gateway_id ?: 'Unknown',
            'card_acceptor_tax_id' => $customer->tax_id,
            'duty_amount' => $duty->amount,
            'shipping_amount' => $shipping->amount,
            'total_tax_amount' => $salesTax->amount,
            'total_discount_amount' => $discounts->amount,
            'items' => $lineItems,
        ];
    }
}
