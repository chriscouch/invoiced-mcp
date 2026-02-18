<?php

namespace App\Integrations\Adyen\Operations;

use App\Core\I18n\ValueObjects\Money;
use App\Integrations\Adyen\AdyenClient;
use App\Integrations\Adyen\Models\AdyenAccount;
use App\Integrations\Adyen\Models\PricingConfiguration;
use App\Integrations\Exceptions\IntegrationApiException;
use App\PaymentProcessing\Gateways\AdyenGateway;
use App\PaymentProcessing\Models\MerchantAccount;

class GeneratePricing
{
    private const PARAMETERS = [
        'ach_fixed_fee',
        'ach_max_fee',
        'ach_variable_fee',
        'amex_interchange_variable_markup',
        'card_fixed_fee',
        'card_interchange_passthrough',
        'card_international_added_variable_fee',
        'card_variable_fee',
        'chargeback_fee',
        'currency',
        'merchant_account',
        'override_split_configuration_id',
    ];

    public function __construct(
        private AdyenClient $adyenClient,
    ) {
    }

    /**
     * Converts the requested pricing into a pricing model.
     *
     * @throws IntegrationApiException
     */
    public function setPricingOnMerchant(AdyenAccount $adyenAccount, array $parameters): PricingConfiguration
    {
        // Create or retrieve the split configuration
        $pricing = $this->getOrCreatePricing($parameters);
        $this->createOnAdyen($pricing);

        // Assign it to any Adyen stores
        $merchantAccounts = MerchantAccount::withoutDeleted()
            ->where('gateway', AdyenGateway::ID)
            ->where('gateway_id', '0', '<>')
            ->first(100);
        foreach ($merchantAccounts as $merchantAccount) {
            $configuration = $merchantAccount->toGatewayConfiguration();
            $storeId = $configuration->credentials->store ?? '';
            $balanceAccountId = $configuration->credentials->balance_account ?? '';
            if ($storeId && $balanceAccountId) {
                $this->adyenClient->updateStore($storeId, [
                    'splitConfiguration' => [
                        'balanceAccountId' => $balanceAccountId,
                        'splitConfigurationId' => $pricing->split_configuration_id,
                    ],
                ]);
            }
        }

        // Save the pricing configuration on the Adyen account
        $adyenAccount->pricing_configuration = $pricing;
        $adyenAccount->saveOrFail();

        return $pricing;
    }

    public function getOrCreatePricing(array $parameters): PricingConfiguration
    {
        // Check if there is an existing pricing model with the same hash
        $hash = $this->generateHash($parameters);
        $pricing = PricingConfiguration::where('hash', $hash)->oneOrNull();
        if ($pricing) {
            return $pricing;
        }

        // Create a new pricing model
        $pricing = new PricingConfiguration();
        foreach (self::PARAMETERS as $key) {
            if (isset($parameters[$key])) {
                $pricing->$key = $parameters[$key];
            }
        }
        $pricing->hash = $hash;
        // Apply an override split configuration ID
        if ($pricing->override_split_configuration_id) {
            $pricing->split_configuration_id = $pricing->override_split_configuration_id;
        }
        $pricing->saveOrFail();

        return $pricing;
    }

    /**
     * Generates an MD5 hash of the pricing configuration in order
     * to de-duplicate the same configuration in the database.
     */
    public function generateHash(array $parameters): string
    {
        $values = [];
        foreach (self::PARAMETERS as $key) {
            $value = $parameters[$key] ?? '';
            $values[] = "$key=$value";
        }
        $str = implode('&', $values);

        return md5($str);
    }

    public function generateSplitConfigurationValues(PricingConfiguration $pricing): array
    {
        $rules = [];

        // Create the card rule as the base rule
        $cardAcquiringFees = $pricing->card_interchange_passthrough ? 'deductFromOneBalanceAccount' : 'deductFromLiableAccount';
        $cardCommission = [];
        if ($value = $pricing->card_fixed_fee) {
            $fixedFee = Money::fromDecimal($pricing->currency, $value);
            $cardCommission['fixedAmount'] = $fixedFee->amount;
        }

        if ($value = $pricing->card_variable_fee) {
            $cardCommission['variablePercentage'] = (int) round($value * 100);
        }

        $rules[] = [
            'currency' => 'ANY',
            'fundingSource' => 'ANY',
            'paymentMethod' => 'ANY',
            'shopperInteraction' => 'ANY',
            'splitLogic' => [
                'acquiringFees' => $cardAcquiringFees,
                'adyenFees' => 'deductFromLiableAccount', // Adyen fees are always charged to Flywire
                'chargeback' => 'deductFromOneBalanceAccount',
                'chargebackCostAllocation' => 'deductFromLiableAccount',
                'commission' => $cardCommission,
                'refund' => 'deductFromOneBalanceAccount',
                'refundCostAllocation' => $cardAcquiringFees,
                'remainder' => 'addToLiableAccount',
                'surcharge' => 'addToOneBalanceAccount',
                'tip' => 'addToOneBalanceAccount',
            ],
        ];

        // Create the Amex specific rule (if enabled)
        if ($value = $pricing->amex_interchange_variable_markup) {
            $rules[] = [
                'currency' => 'ANY',
                'fundingSource' => 'ANY',
                'paymentMethod' => 'amex',
                'shopperInteraction' => 'ANY',
                'splitLogic' => [
                    'acquiringFees' => 'deductFromOneBalanceAccount',
                    'adyenFees' => 'deductFromLiableAccount', // Adyen fees are always charged to Flywire
                    'chargeback' => 'deductFromOneBalanceAccount',
                    'chargebackCostAllocation' => 'deductFromLiableAccount',
                    'commission' => [
                        'variablePercentage' => (int) round($value * 100),
                    ],
                    'refund' => 'deductFromOneBalanceAccount',
                    'refundCostAllocation' => 'deductFromOneBalanceAccount',
                    'remainder' => 'addToLiableAccount',
                    'surcharge' => 'addToOneBalanceAccount',
                    'tip' => 'addToOneBalanceAccount',
                ],
            ];
        }

        // Create the ACH rule (if enabled)
        if ($pricing->ach_fixed_fee || $pricing->ach_variable_fee) {
            $achCommission = [];
            if ($value = $pricing->ach_fixed_fee) {
                $fixedFee = Money::fromDecimal($pricing->currency, $value);
                $achCommission['fixedAmount'] = $fixedFee->amount;
            }

            if ($value = $pricing->ach_variable_fee) {
                $achCommission['variablePercentage'] = (int) round($value * 100);
            }

            $rules[] = [
                'currency' => 'USD',
                'fundingSource' => 'ANY',
                'paymentMethod' => 'ach',
                'shopperInteraction' => 'ANY',
                'splitLogic' => [
                    'acquiringFees' => 'deductFromLiableAccount', // ACH is always charged to Flywire
                    'adyenFees' => 'deductFromLiableAccount', // Adyen fees are always charged to Flywire
                    'chargeback' => 'deductFromOneBalanceAccount',
                    'chargebackCostAllocation' => 'deductFromLiableAccount',
                    'commission' => $achCommission,
                    'refund' => 'deductFromOneBalanceAccount',
                    'refundCostAllocation' => 'deductFromLiableAccount',
                    'remainder' => 'addToLiableAccount',
                    'surcharge' => 'addToOneBalanceAccount',
                    'tip' => 'addToOneBalanceAccount',
                ],
            ];
        }

        return [
            'description' => 'Pricing # '.$pricing->id,
            'rules' => $rules,
        ];
    }

    /**
     * Creates a split configuration on Adyen for this pricing rule.
     * Does nothing if the pricing rule already has an associated
     * split configuraiton.
     *
     * @throws IntegrationApiException
     */
    private function createOnAdyen(PricingConfiguration $pricing): void
    {
        if ($pricing->split_configuration_id) {
            return;
        }

        $parameters = $this->generateSplitConfigurationValues($pricing);
        $result = $this->adyenClient->createSplitConfiguration($pricing->merchant_account, $parameters);
        $pricing->split_configuration_id = $result['splitConfigurationId'];
        $pricing->saveOrFail();
    }
}
