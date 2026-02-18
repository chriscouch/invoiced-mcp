<?php

namespace App\Tests\Integrations\Adyen\Webhooks;

use App\AccountsReceivable\Models\Invoice;
use App\CashApplication\Enums\PaymentItemIntType;
use App\EntryPoint\QueueJob\ProcessAdyenChargebackWebhookJob;
use App\EntryPoint\QueueJob\ProcessAdyenPaymentAuthorizationWebhookJob;
use App\Integrations\Adyen\Models\AdyenAccount;
use App\Integrations\Adyen\Models\AdyenPaymentResult;
use App\PaymentProcessing\Enums\DisputeStatus;
use App\PaymentProcessing\Enums\PaymentFlowSource;
use App\PaymentProcessing\Enums\PaymentFlowStatus;
use App\PaymentProcessing\Gateways\AdyenGateway;
use App\PaymentProcessing\Gateways\TestGateway;
use App\PaymentProcessing\Models\Charge;
use App\PaymentProcessing\Models\Dispute;
use App\PaymentProcessing\Models\PaymentFlow;
use App\PaymentProcessing\Models\PaymentFlowApplication;
use App\PaymentProcessing\Models\PaymentMethod;
use App\Tests\AppTestCase;
use Mockery;
use Psr\Log\LoggerInterface;

class AdyenPaymentWebhookTest extends AppTestCase
{
    private static Charge $charge;
    private static PaymentFlow $paymentFlow;
    private static PaymentFlowApplication $paymentFlowApplication;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        self::hasCompany();
        self::hasMerchantAccount(AdyenGateway::ID);
        self::hasCustomer();

        self::getService('test.database')->executeStatement('DELETE FROM Disputes');
        self::getService('test.database')->executeStatement('DELETE FROM Charges');

        $adyenAccount = new AdyenAccount();
        $adyenAccount->balance_account_id = 'test_account';
        $adyenAccount->saveOrFail();

        self::registerPayment();

        self::$charge = new Charge();
        self::$charge->currency = 'usd';
        self::$charge->amount = 1000;
        self::$charge->gateway = TestGateway::ID;
        self::$charge->status = 'succeeded';
        self::$charge->gateway_id = '9913140798220028';
        self::$charge->saveOrFail();
    }

    private function getWebhook(string $service): ProcessAdyenChargebackWebhookJob | ProcessAdyenPaymentAuthorizationWebhookJob
    {
        return self::getService("test.$service");
    }

    /**
     * @dataProvider input
     */
    public function testInput(
        string $input,
        array $dispute): void
    {
        $webhook = $this->getWebhook('adyen_payment_webhook');
        $notification = json_decode($input, true);
        $webhook->args = ['event' => $notification['NotificationRequestItem']];

        $webhook->perform();
        $disputes = Dispute::execute();
        $this->assertCount(1, $disputes);
        $this->assertEquals($dispute, array_intersect_key($disputes[0]->toArray(), $dispute));
        $this->assertEquals(self::$charge->id, $disputes[0]->charge_id);
    }

    /**
     * @dataProvider paymentAuthorizationInput
     */
    public function testPaymentAuthorization(string $input, array $data): void
    {
        $adyenResult = new AdyenPaymentResult();
        $adyenResult->reference = self::$paymentFlow->identifier;

        $json = json_encode($data, JSON_THROW_ON_ERROR);
        $adyenResult->result = $json;
        $adyenResult->saveOrFail();

        $webhook = $this->getWebhook('adyen_payment_authorization_webhook');

        $input = str_replace('YOUR_MERCHANT_REFERENCE', self::$paymentFlow->identifier, $input);
        $authData = json_decode($input, true);
        $webhook->args = ['event' => $authData['NotificationRequestItem']];

        $webhook->perform();

        $failedChargeCount = Charge::where('gateway', AdyenGateway::ID)
            ->where('payment_flow_id', self::$paymentFlow->id)
            ->where('status', 'failed')
            ->count();
        $this->assertEquals($data['failedChargeCount'], $failedChargeCount);
    }

    public function input(): array
    {
        return [
            'REQUEST_FOR_INFORMATION' => [
                '{
                      "NotificationRequestItem": {
                        "amount": {
                          "currency": "EUR",
                          "value": 1000
                        },
                        "eventCode": "REQUEST_FOR_INFORMATION",
                        "eventDate": "2021-01-01T01:00:00+01:00",
                        "merchantAccountCode": "YOUR_MERCHANT_ACCOUNT",
                        "merchantReference": "YOUR_MERCHANT_REFERENCE",
                        "originalReference": "9913140798220028",
                        "paymentMethod": "mc",
                        "pspReference": "QFQTPCQ8HXSKGK82",
                        "reason": "",
                        "success": "true"
                      }
                }',
                [
                    'currency' => 'eur',
                    'amount' => 10,
                    'gateway' => AdyenGateway::ID,
                    'gateway_id' => 'QFQTPCQ8HXSKGK82',
                    'status' => DisputeStatus::Unresponded->name,
                    'reason' => '',
                ],
            ],
            'NOTIFICATION_OF_CHARGEBACK' => [
                '{
                      "NotificationRequestItem": {
                        "amount": {
                          "currency": "EUR",
                          "value": 1000
                        },
                            "eventCode": "NOTIFICATION_OF_CHARGEBACK",
                            "eventDate": "2021-01-01T01:00:00+01:00",
                            "merchantAccountCode": "YOUR_MERCHANT_ACCOUNT",
                            "merchantReference": "YOUR_MERCHANT_REFERENCE",
                            "originalReference": "9913140798220028",
                            "paymentMethod": "mc",
                            "pspReference": "QFQTPCQ8HXSKGK82",
                            "reason": "Fraudulent Processing of Transactions",
                            "success": "true"          
                      }
                }',
                [
                    'currency' => 'eur',
                    'amount' => 10,
                    'gateway' => AdyenGateway::ID,
                    'gateway_id' => 'QFQTPCQ8HXSKGK82',
                    'status' => DisputeStatus::Unresponded->name,
                    'reason' => 'Fraudulent Processing of Transactions',
                ],
            ],
            'CHARGEBACK' => [
                '{
                      "NotificationRequestItem": {
                        "amount": {
                          "currency": "EUR",
                          "value": 1000
                        },
                        "eventCode": "CHARGEBACK",
                        "eventDate": "2021-01-01T01:00:00+01:00",
                        "merchantAccountCode": "YOUR_MERCHANT_ACCOUNT",
                        "merchantReference": "YOUR_MERCHANT_REFERENCE",
                        "originalReference": "9913140798220028",
                        "paymentMethod": "mc",
                        "pspReference": "QFQTPCQ8HXSKGK82",
                        "reason": "Payment.TxId=300000000524534724 dispute",
                        "success": "true"
                      }
                }',
                [
                    'currency' => 'eur',
                    'amount' => 10,
                    'gateway' => AdyenGateway::ID,
                    'gateway_id' => 'QFQTPCQ8HXSKGK82',
                    'status' => DisputeStatus::Unresponded->name,
                    'reason' => 'Payment.TxId=300000000524534724 dispute',
                ],
            ],
            'CHARGEBACK_REVERSED' => [
                '{
                      "NotificationRequestItem": {
                        "amount": {
                          "currency": "EUR",
                          "value": 1000
                        },
                        "eventCode": "CHARGEBACK_REVERSED",
                        "eventDate": "2021-01-01T01:00:00+01:00",
                        "merchantAccountCode": "YOUR_MERCHANT_ACCOUNT",
                        "merchantReference": "YOUR_MERCHANT_REFERENCE",
                        "paymentMethod": "mc",
                        "pspReference": "QFQTPCQ8HXSKGK82",
                        "reason": "Fraudulent Processing of Transactions",
                        "success": "true"                        
                      }
                }',
                [
                    'currency' => 'eur',
                    'amount' => 10,
                    'gateway' => AdyenGateway::ID,
                    'gateway_id' => 'QFQTPCQ8HXSKGK82',
                    'status' => DisputeStatus::Pending->name,
                    'reason' => 'Fraudulent Processing of Transactions',
                ],
            ],
            'PREARBITRATION_WON' => [
                '{
                      "NotificationRequestItem": {
                        "amount": {
                          "currency": "EUR",
                          "value": 1000
                        },
                        "eventCode": "PREARBITRATION_WON",
                        "eventDate": "2021-01-01T01:00:00+01:00",
                        "merchantAccountCode": "YOUR_MERCHANT_ACCOUNT",
                        "merchantReference": "YOUR_MERCHANT_REFERENCE",
                        "paymentMethod": "bankTransfer_IBAN",
                        "pspReference": "QFQTPCQ8HXSKGK82",
                        "reason": "",
                        "success": "true"     
                      }
                }',
                [
                    'currency' => 'eur',
                    'amount' => 10,
                    'gateway' => AdyenGateway::ID,
                    'gateway_id' => 'QFQTPCQ8HXSKGK82',
                    'status' => DisputeStatus::Won->name,
                    // left from previos execution
                    'reason' => 'Fraudulent Processing of Transactions',
                ],
            ],
            'PREARBITRATION_LOST' => [
                '{
                      "NotificationRequestItem": {
                        "amount": {
                          "currency": "EUR",
                          "value": 1000
                        },
                        "eventCode": "PREARBITRATION_LOST",
                        "eventDate": "2021-01-01T01:00:00+01:00",
                        "merchantAccountCode": "YOUR_MERCHANT_ACCOUNT",
                        "merchantReference": "YOUR_MERCHANT_REFERENCE",
                        "paymentMethod": "visa",
                        "pspReference": "QFQTPCQ8HXSKGK82",
                        "reason": "Other Fraud-Card Absent Environment",
                        "success": "true"    
                      }
                }',
                [
                    'currency' => 'eur',
                    'amount' => 10,
                    'gateway' => AdyenGateway::ID,
                    'gateway_id' => 'QFQTPCQ8HXSKGK82',
                    'status' => DisputeStatus::Lost->name,
                    'reason' => 'Other Fraud-Card Absent Environment',
                ],
            ],
            'SECOND_CHARGEBACK' => [
                '{
                      "NotificationRequestItem": {
                        "amount": {
                          "currency": "EUR",
                          "value": 1000
                        },
                        "eventCode": "SECOND_CHARGEBACK",
                        "eventDate": "2021-01-01T01:00:00+01:00",
                        "merchantAccountCode": "YOUR_MERCHANT_ACCOUNT",
                        "merchantReference": "YOUR_MERCHANT_REFERENCE",
                        "paymentMethod": "jcb",
                        "pspReference": "QFQTPCQ8HXSKGK82",
                        "reason": "502:Cardmember Dispute",
                        "success": "true"      
                      }
                }',
                [
                    'currency' => 'eur',
                    'amount' => 10,
                    'gateway' => AdyenGateway::ID,
                    'gateway_id' => 'QFQTPCQ8HXSKGK82',
                    'status' => DisputeStatus::Lost->name,
                    'reason' => '502:Cardmember Dispute',
                ],
            ],
        ];
    }

    public function paymentAuthorizationInput(): array
    {
        return [
            '3D_NOT_AUTHENTICATED' => ['
                {
                    "NotificationRequestItem": {
                      "amount": {
                        "currency": "USD",
                        "value": 1000
                      },
                      "eventCode": "AUTHORISATION",
                      "eventDate": "2025-10-13T17:25:55+02:00",
                      "merchantAccountCode": "YOUR_MERCHANT_ACCOUNT",
                      "merchantReference": "YOUR_MERCHANT_REFERENCE",
                      "paymentMethod": "visa",
                      "pspReference": "QFQTPCQ8HXSKGK82",
                      "reason": "Authentication failed",
                      "success": "false",
                      "additionalData": {
                        "expiryDate": "12/2030",
                        "cardSummary": "1111",
                        "paymentMethodVariant": "visa",
                        "threeDAuthenticated": "false",
                        "acquirerCode": "TestPmmAcquirer"
                      }
                    }
                }',
                [
                    'pspReference' =>'QFQTPCQ8HXSKGK82',
                    'resultCode' => 'Refused',
                    'refusalReason' => 'Authentication failed',
                    'paymentMethod' => [
                        'brand' => 'visa'
                    ],
                    'failedChargeCount' => 1,
                ]
            ]
        ];
    }

    private static function registerPayment(): void
    {

        $paymentMethod = PaymentMethod::instance(self::$company, PaymentMethod::CREDIT_CARD);
        $paymentMethod->gateway = AdyenGateway::ID;
        $paymentMethod->enabled = true;
        $paymentMethod->saveOrFail();

        $invoice = new Invoice();
        $invoice->setCustomer(self::$customer);
        $invoice->items = [
            [
                'name' => 'Test Item',
                'description' => 'test',
                'quantity' => 1,
                'unit_cost' => 10,
            ],
        ];
        $invoice->saveOrFail();

        $reference = 'PAYMENT_FLOW_' . time();
        self::$paymentFlow = new PaymentFlow();
        self::$paymentFlow->identifier = $reference;
        self::$paymentFlow->make_payment_source_default = true;
        self::$paymentFlow->status = PaymentFlowStatus::Succeeded;
        self::$paymentFlow->currency = 'usd';
        self::$paymentFlow->amount = 10;
        self::$paymentFlow->customer = self::$customer;
        self::$paymentFlow->initiated_from = PaymentFlowSource::CustomerPortal;
        self::$paymentFlow->gateway = AdyenGateway::ID;
        self::$paymentFlow->merchant_account = self::$merchantAccount;
        self::$paymentFlow->saveOrFail();

        self::$paymentFlowApplication = new PaymentFlowApplication();
        self::$paymentFlowApplication->payment_flow = self::$paymentFlow;
        self::$paymentFlowApplication->type = PaymentItemIntType::Invoice;
        self::$paymentFlowApplication->amount = 10;
        self::$paymentFlowApplication->invoice = $invoice;
        self::$paymentFlowApplication->saveOrFail();
    }
}
