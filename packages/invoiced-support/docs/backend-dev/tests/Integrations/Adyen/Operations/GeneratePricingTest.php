<?php

namespace App\Tests\Integrations\Adyen\Operations;

use App\Integrations\Adyen\AdyenClient;
use App\Integrations\Adyen\Models\PricingConfiguration;
use App\Integrations\Adyen\Operations\GeneratePricing;
use App\Tests\AppTestCase;
use Mockery;

class GeneratePricingTest extends AppTestCase
{
    private function getOperation(): GeneratePricing
    {
        $adyenClient = Mockery::mock(AdyenClient::class);

        return new GeneratePricing($adyenClient);
    }

    public function testGetOrCreatePricing(): void
    {
        $operation = $this->getOperation();

        $params1 = [
            'merchant_account' => 'InvoicedCOM',
            'currency' => 'usd',
            'card_variable_fee' => 2.9,
            'card_international_added_variable_fee' => 1,
            'chargeback_fee' => 15.0,
            'ach_fixed_fee' => 2.0,
        ];
        $pricing1 = $operation->getOrCreatePricing($params1);
        $this->assertTrue($pricing1->persisted());

        $pricing2 = $operation->getOrCreatePricing($params1);
        $this->assertEquals($pricing1->id, $pricing2->id);

        $params2 = [
            'merchant_account' => 'InvoicedCOM',
            'currency' => 'usd',
            'card_variable_fee' => 2.5,
            'card_international_added_variable_fee' => 1,
            'chargeback_fee' => 15.0,
            'ach_fixed_fee' => 2.0,
        ];
        $pricing3 = $operation->getOrCreatePricing($params2);
        $this->assertNotEquals($pricing1->id, $pricing3->id);
    }

    public function testGenerateHash(): void
    {
        $operation = $this->getOperation();

        $hash1 = $operation->generateHash([
            'merchant_account' => 'InvoicedCOM',
            'card_variable_fee' => 2.9,
            'card_international_added_variable_fee' => 1,
            'chargeback_fee' => 15.0,
            'ach_fixed_fee' => 2.0,
        ]);
        $this->assertLessThan(50, strlen($hash1));

        $hash2 = $operation->generateHash([
            'merchant_account' => 'InvoicedCOM',
            'card_variable_fee' => 2.9,
            'card_international_added_variable_fee' => 1,
            'chargeback_fee' => 15.0,
        ]);
        $this->assertNotEquals($hash1, $hash2);

        $hash3 = $operation->generateHash([
            'merchant_account' => 'InvoicedCOM',
            'card_interchange_passthrough' => true,
            'card_variable_fee' => 0.3,
            'chargeback_fee' => 15.0,
        ]);
        $this->assertNotEquals($hash1, $hash3);
        $this->assertNotEquals($hash2, $hash3);
    }

    /**
     * @dataProvider splitConfigurationProvider
     */
    public function testGenerateSplitConfigurationValues(PricingConfiguration $pricing, array $expected): void
    {
        $operation = $this->getOperation();
        $this->assertEquals($expected, $operation->generateSplitConfigurationValues($pricing));
    }

    public function splitConfigurationProvider(): array
    {
        return [
            // Standard US Pricing
            [
                new PricingConfiguration([
                    'id' => 1,
                    'currency' => 'usd',
                    'card_variable_fee' => 2.9,
                    'card_international_added_variable_fee' => 1,
                    'ach_variable_fee' => 0.8,
                    'ach_max_fee' => 5,
                    'chargeback_fee' => 15,
                ]),
                [
                    'description' => 'Pricing # 1',
                    'rules' => [
                        [
                            'currency' => 'ANY',
                            'fundingSource' => 'ANY',
                            'paymentMethod' => 'ANY',
                            'shopperInteraction' => 'ANY',
                            'splitLogic' => [
                                'acquiringFees' => 'deductFromLiableAccount',
                                'adyenFees' => 'deductFromLiableAccount',
                                'chargeback' => 'deductFromOneBalanceAccount',
                                'chargebackCostAllocation' => 'deductFromLiableAccount',
                                'commission' => [
                                    'variablePercentage' => 290,
                                ],
                                'refund' => 'deductFromOneBalanceAccount',
                                'refundCostAllocation' => 'deductFromLiableAccount',
                                'remainder' => 'addToLiableAccount',
                                'surcharge' => 'addToOneBalanceAccount',
                                'tip' => 'addToOneBalanceAccount',
                            ],
                        ],
                        [
                            'currency' => 'USD',
                            'fundingSource' => 'ANY',
                            'paymentMethod' => 'ach',
                            'shopperInteraction' => 'ANY',
                            'splitLogic' => [
                                'acquiringFees' => 'deductFromLiableAccount',
                                'adyenFees' => 'deductFromLiableAccount',
                                'chargeback' => 'deductFromOneBalanceAccount',
                                'chargebackCostAllocation' => 'deductFromLiableAccount',
                                'commission' => [
                                    'variablePercentage' => 80,
                                ],
                                'refund' => 'deductFromOneBalanceAccount',
                                'refundCostAllocation' => 'deductFromLiableAccount',
                                'remainder' => 'addToLiableAccount',
                                'surcharge' => 'addToOneBalanceAccount',
                                'tip' => 'addToOneBalanceAccount',
                            ],
                        ],
                    ],
                ],
            ],
            // IC++ 30bps + $2 ACH
            [
                new PricingConfiguration([
                    'id' => 2,
                    'currency' => 'usd',
                    'card_variable_fee' => 0.3,
                    'card_interchange_passthrough' => true,
                    'ach_fixed_fee' => 2,
                    'chargeback_fee' => 15,
                ]),
                [
                    'description' => 'Pricing # 2',
                    'rules' => [
                        [
                            'currency' => 'ANY',
                            'fundingSource' => 'ANY',
                            'paymentMethod' => 'ANY',
                            'shopperInteraction' => 'ANY',
                            'splitLogic' => [
                                'acquiringFees' => 'deductFromOneBalanceAccount',
                                'adyenFees' => 'deductFromLiableAccount',
                                'chargeback' => 'deductFromOneBalanceAccount',
                                'chargebackCostAllocation' => 'deductFromLiableAccount',
                                'commission' => [
                                    'variablePercentage' => 30,
                                ],
                                'refund' => 'deductFromOneBalanceAccount',
                                'refundCostAllocation' => 'deductFromOneBalanceAccount',
                                'remainder' => 'addToLiableAccount',
                                'surcharge' => 'addToOneBalanceAccount',
                                'tip' => 'addToOneBalanceAccount',
                            ],
                        ],
                        [
                            'currency' => 'USD',
                            'fundingSource' => 'ANY',
                            'paymentMethod' => 'ach',
                            'shopperInteraction' => 'ANY',
                            'splitLogic' => [
                                'acquiringFees' => 'deductFromLiableAccount',
                                'adyenFees' => 'deductFromLiableAccount',
                                'chargeback' => 'deductFromOneBalanceAccount',
                                'chargebackCostAllocation' => 'deductFromLiableAccount',
                                'commission' => [
                                    'fixedAmount' => 200,
                                ],
                                'refund' => 'deductFromOneBalanceAccount',
                                'refundCostAllocation' => 'deductFromLiableAccount',
                                'remainder' => 'addToLiableAccount',
                                'surcharge' => 'addToOneBalanceAccount',
                                'tip' => 'addToOneBalanceAccount',
                            ],
                        ],
                    ],
                ],
            ],
            // Standard Canada Pricing
            [
                new PricingConfiguration([
                    'id' => 3,
                    'currency' => 'cad',
                    'card_variable_fee' => 2.5,
                    'card_international_added_variable_fee' => 1,
                    'chargeback_fee' => 15,
                ]),
                [
                    'description' => 'Pricing # 3',
                    'rules' => [
                        [
                            'currency' => 'ANY',
                            'fundingSource' => 'ANY',
                            'paymentMethod' => 'ANY',
                            'shopperInteraction' => 'ANY',
                            'splitLogic' => [
                                'acquiringFees' => 'deductFromLiableAccount',
                                'adyenFees' => 'deductFromLiableAccount',
                                'chargeback' => 'deductFromOneBalanceAccount',
                                'chargebackCostAllocation' => 'deductFromLiableAccount',
                                'commission' => [
                                    'variablePercentage' => 250,
                                ],
                                'refund' => 'deductFromOneBalanceAccount',
                                'refundCostAllocation' => 'deductFromLiableAccount',
                                'remainder' => 'addToLiableAccount',
                                'surcharge' => 'addToOneBalanceAccount',
                                'tip' => 'addToOneBalanceAccount',
                            ],
                        ],
                    ],
                ],
            ],
            // Amex Pricing
            [
                new PricingConfiguration([
                    'id' => 1,
                    'currency' => 'usd',
                    'card_variable_fee' => 2.9,
                    'card_international_added_variable_fee' => 1,
                    'amex_interchange_variable_markup' => .2,
                    'ach_variable_fee' => 0.8,
                    'ach_max_fee' => 5,
                    'chargeback_fee' => 15,
                ]),
                [
                    'description' => 'Pricing # 1',
                    'rules' => [
                        [
                            'currency' => 'ANY',
                            'fundingSource' => 'ANY',
                            'paymentMethod' => 'ANY',
                            'shopperInteraction' => 'ANY',
                            'splitLogic' => [
                                'acquiringFees' => 'deductFromLiableAccount',
                                'adyenFees' => 'deductFromLiableAccount',
                                'chargeback' => 'deductFromOneBalanceAccount',
                                'chargebackCostAllocation' => 'deductFromLiableAccount',
                                'commission' => [
                                    'variablePercentage' => 290,
                                ],
                                'refund' => 'deductFromOneBalanceAccount',
                                'refundCostAllocation' => 'deductFromLiableAccount',
                                'remainder' => 'addToLiableAccount',
                                'surcharge' => 'addToOneBalanceAccount',
                                'tip' => 'addToOneBalanceAccount',
                            ],
                        ],
                        [
                            'currency' => 'ANY',
                            'fundingSource' => 'ANY',
                            'paymentMethod' => 'amex',
                            'shopperInteraction' => 'ANY',
                            'splitLogic' => [
                                'acquiringFees' => 'deductFromOneBalanceAccount',
                                'adyenFees' => 'deductFromLiableAccount',
                                'chargeback' => 'deductFromOneBalanceAccount',
                                'chargebackCostAllocation' => 'deductFromLiableAccount',
                                'commission' => [
                                    'variablePercentage' => 20,
                                ],
                                'refund' => 'deductFromOneBalanceAccount',
                                'refundCostAllocation' => 'deductFromOneBalanceAccount',
                                'remainder' => 'addToLiableAccount',
                                'surcharge' => 'addToOneBalanceAccount',
                                'tip' => 'addToOneBalanceAccount',
                            ],
                        ],
                        [
                            'currency' => 'USD',
                            'fundingSource' => 'ANY',
                            'paymentMethod' => 'ach',
                            'shopperInteraction' => 'ANY',
                            'splitLogic' => [
                                'acquiringFees' => 'deductFromLiableAccount',
                                'adyenFees' => 'deductFromLiableAccount',
                                'chargeback' => 'deductFromOneBalanceAccount',
                                'chargebackCostAllocation' => 'deductFromLiableAccount',
                                'commission' => [
                                    'variablePercentage' => 80,
                                ],
                                'refund' => 'deductFromOneBalanceAccount',
                                'refundCostAllocation' => 'deductFromLiableAccount',
                                'remainder' => 'addToLiableAccount',
                                'surcharge' => 'addToOneBalanceAccount',
                                'tip' => 'addToOneBalanceAccount',
                            ],
                        ],
                    ],
                ],
            ],
        ];
    }
}
