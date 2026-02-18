<?php

namespace App\Tests\Integrations\Adyen\Api;

use App\Core\RestApi\ValueObjects\ApiCallContext;
use App\Core\RestApi\ValueObjects\ApiRouteDefinition;
use App\Integrations\Adyen\AdyenClient;
use App\Integrations\Adyen\Api\RetrieveFlywirePaymentsAccountRoute;
use App\Integrations\Adyen\FlywirePaymentsOnboarding;
use App\Integrations\Adyen\Models\AdyenAccount;
use App\Integrations\Adyen\Operations\GetAccountInformation;
use App\PaymentProcessing\Gateways\AdyenGateway;
use App\Tests\AppTestCase;
use Mockery;
use Symfony\Component\HttpFoundation\Request;

class RetrieveFlywirePaymentsAccountRouteTest extends AppTestCase
{
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        self::hasCompany();
        $adyenAccount = new AdyenAccount();
        $adyenAccount->saveOrFail();
        self::hasMerchantAccount(AdyenGateway::ID);
    }

    /**
     * @dataProvider promiseProvider
     */
    public function testBuildResponse(array $input, array $expected): void
    {
        [$balanceAccount, $legalEntity, $accountHolder, $sweeps] = $input;
        $info = Mockery::mock(GetAccountInformation::class);
        $info->shouldReceive('balanceAccount')->andReturn($balanceAccount)->once();
        $info->shouldReceive('legalEntity')->andReturn($legalEntity)->once();
        $info->shouldReceive('accountHolder')->andReturn($accountHolder)->once();
        $info->shouldReceive('sweep')->andReturn($sweeps)->once();

        $onboarding = Mockery::mock(FlywirePaymentsOnboarding::class);
        $onboarding->shouldReceive('getOnboardingStartUrl')->andReturn('test')->once();

        $adyenClient = Mockery::mock(AdyenClient::class);

        $route = new RetrieveFlywirePaymentsAccountRoute(
            self::getService('test.tenant'),
            $onboarding,
            $info,
            $adyenClient,
        );

        $definition = new ApiRouteDefinition(null, null, []);
        $request = new Request();
        $context = new ApiCallContext($request, [], [], $definition);
        $this->assertEquals($expected, $route->buildResponse($context));
    }

    public function promiseProvider(): array
    {
        return [
            'variant1' => [
                [
                    // balance
                    [
                        'balances' => [
                            [
                                'currency' => 'usd',
                                'balance' => 105,
                                'available' => 95,
                                'pending' => 0,
                            ],
                            [
                                'currency' => 'eur',
                                'balance' => 200,
                                'available' => 95,
                                'pending' => 0,
                            ],
                        ],
                        'status' => 'active',
                    ],
                    // legal entity
                    [],
                    // account
                    [
                        'capabilities' => [
                            'sendToTransferInstrument' => [
                                'enabled' => true,
                                'allowed' => false,
                                'verificationStatus' => 'invalid',
                            ],
                            'sendToBalanceAccount' => [
                                'enabled' => true,
                                'allowed' => false,
                            ],
                            'receivePayments' => [
                                'enabled' => false,
                                'allowed' => true,
                                'verificationStatus' => 'valid',
                            ],
                        ],
                    ],
                    // withdrawal
                    [
                        'bank_name' => 'test bank x0000',
                        'frequency' => null,
                    ],
                ],
                [
                    // saved for future use
                    'state' => 'active',
                    'balances' => [
                        [
                            'currency' => 'usd',
                            'balance' => 1.05,
                            'available' => 0.95,
                            'pending' => 0,
                        ],
                        [
                            'currency' => 'eur',
                            'balance' => 2,
                            'available' => 0.95,
                            'pending' => 0,
                        ],
                    ],
                    'withdrawal' => [
                        'next_withdrawal' => null,
                        'bank_name' => 'test bank x0000',
                        'frequency' => null,
                        'outgoing_pending_amount' => null,
                    ],
                    'statuses' => [
                        'outgoing_payments_status' => 'disabled',
                        'receive_payments' => 'disabled',
                    ],
                    'disabled_reasons' => [],
                    'update_uri' => 'test',
                    'bank_accounts' => [],
                    'statement_descriptor' => 'TEST',
                ],
            ],
            'variant2' => [
                [
                    // balance
                    [
                        'balances' => [
                            [
                                'currency' => 'usd',
                                'balance' => 100,
                                'available' => 95,
                                'pending' => 0,
                            ],
                        ],
                        'status' => 'pending',
                    ],
                    // legal entity
                    [],
                    // account
                    [
                        'capabilities' => [
                            'sendToTransferInstrument' => [
                                'enabled' => true,
                                'allowed' => false,
                                'verificationStatus' => 'pending',
                                'problems' => [
                                    [
                                        'entity' => [
                                            'id' => 'LE1234',
                                        ],
                                        'verificationErrors' => [
                                            [
                                                'code' => '2_8036',
                                                'message' => 'test 3',
                                            ],
                                            [
                                                'code' => '2_8037',
                                                'message' => 'test 4',
                                            ],
                                        ],
                                    ],
                                    [
                                        'entity' => [
                                            'id' => 'LE1234',
                                        ],
                                        'verificationErrors' => [
                                            [
                                                'code' => '2_8038',
                                                'message' => 'test 1',
                                            ],
                                            [
                                                'code' => '2_8039',
                                                'message' => 'test 2',
                                            ],
                                        ],
                                    ],
                                ],
                            ],
                            'sendToBalanceAccount' => [
                                'enabled' => true,
                                'allowed' => true,
                            ],
                            'receivePayments' => [
                                'enabled' => true,
                                'allowed' => true,
                                'verificationStatus' => 'valid',
                            ],
                        ],
                    ],
                    // withdrawal
                    [
                        'bank_name' => 'test bank x0000',
                        'frequency' => 'daily',
                    ],
                ],
                [
                    // saved for future use
                    'state' => 'pending',
                    'balances' => [
                        [
                            'currency' => 'usd',
                            'balance' => 1,
                            'available' => 0.95,
                            'pending' => 0,
                        ],
                    ],
                    'withdrawal' => [
                        'next_withdrawal' => null,
                        'bank_name' => 'test bank x0000',
                        'frequency' => 'daily',
                        'outgoing_pending_amount' => null,
                    ],
                    'statuses' => [
                        'outgoing_payments_status' => 'pending',
                        'receive_payments' => 'enabled',
                    ],
                    'disabled_reasons' => [
                        [
                            'code' => '2_8036',
                            'message' => 'test 3',
                            'subErrors' => [],
                        ],
                        [
                            'code' => '2_8037',
                            'message' => 'test 4',
                            'subErrors' => [],
                        ],
                        [
                            'code' => '2_8038',
                            'message' => 'test 1',
                            'subErrors' => [],
                        ],
                        [
                            'code' => '2_8039',
                            'message' => 'test 2',
                            'subErrors' => [],
                        ],
                    ],
                    'update_uri' => 'test',
                    'bank_accounts' => [],
                    'statement_descriptor' => 'TEST',
                ],
            ],
        ];
    }
}
