<?php

namespace App\Tests\Integrations\Flywire\Syncs;

use App\Integrations\Flywire\Enums\FlywirePaymentStatus;
use App\Integrations\Flywire\FlywirePrivateClient;
use App\Integrations\Flywire\Models\FlywirePayment;
use App\Integrations\Flywire\Operations\SaveFlywirePayment;
use App\Integrations\Flywire\Syncs\FlywirePaymentSync;
use App\PaymentProcessing\Gateways\FlywireGateway;
use App\PaymentProcessing\Models\Charge;
use App\Tests\AppTestCase;
use Mockery;

class FlywirePaymentSyncTest extends AppTestCase
{
    private static Charge $charge;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        self::hasCompany();
        self::hasCustomer();
        self::hasMerchantAccount(FlywireGateway::ID, 'gateway2_'.time());
        self::$charge = new Charge();
        self::$charge->customer = self::$customer;
        self::$charge->currency = 'usd';
        self::$charge->amount = 100;
        self::$charge->status = Charge::PENDING;
        self::$charge->gateway = FlywireGateway::ID;
        self::$charge->gateway_id = 'test';
        self::$charge->last_status_check = 0;
        self::$charge->saveOrFail();
    }

    /**
     * @dataProvider dataProvider
     */
    public function testSync(string $data, array $expected): void
    {
        $updateChargeStatus = self::getService('test.update_charge_status');

        $client = Mockery::mock(FlywirePrivateClient::class);
        $client->shouldReceive('getPayments')
            ->andReturn([
                'payments' => [
                    ['id' => 'test'],
                ],
            ]);
        $input = json_decode($data, true);
        $client->shouldReceive('getPayment')->andReturn($input);
        $operation = new SaveFlywirePayment($client, $updateChargeStatus, self::getService('test.payment_flow_reconcile'));

        $sync = new FlywirePaymentSync($client, $operation);

        FlywirePayment::where('payment_id', 'test')->delete();
        self::$charge->status = Charge::PENDING;
        self::$charge->saveOrFail();

        $sync->sync(self::$merchantAccount, ['UUO'], false);

        self::$charge->refresh();
        $this->assertEquals($expected['status'], self::$charge->status);
        $this->assertEquals($expected['message'], self::$charge->failure_message);

        $payment = FlywirePayment::where('payment_id', 'test')->one();
        $this->assertEquals(FlywirePaymentStatus::fromString($input['status']), $payment->status);
    }

    public function dataProvider(): array
    {
        return [
            'processed' => [
                '{
    "id": "test",
    "created_at": "2024-10-24T20:51:57Z",
    "price": {
        "value": "106200",
        "currency": {
            "code": "EUR"
        }
    },
    "purchase": {
        "value": "120000",
        "currency": {
            "code": "USD"
        }
    },
    "status": "processed",
    "external_reference": "1234",
    "country": "ES",
    "recipient": {
        "id": "UUO"
    },
    "charge_events": [
        {
"payment_method_details": {
            "type": "MC",
            "brand": "MASTERCARD",
            "card_classification": "CREDIT",
            "expiration_month": "2",
            "expiration_year": "2030",
            "last_four_digits": "5454"
}
        }
    ],
    "fields": {
      "booking_reference": "REF-1234"
    }
  }',
                [
                    'status' => Charge::PENDING,
                    'message' => null,
                ],
            ],
            'guaranteed' => [
                '{
    "id": "test",
    "created_at": "2024-10-24T20:51:57Z",    "price": {
        "value": "106200",
        "currency": {
            "code": "EUR"
        }
    },
    "purchase": {
        "value": "120000",
        "currency": {
            "code": "USD"
        }
    },
    "status": "guaranteed",
    "external_reference": "1234",
    "country": "ES",
    "recipient": {
        "id": "UUO"
    },
    "charge_events": [
        {
"payment_method_details": {
            "type": "MC",
            "brand": "MASTERCARD",
            "card_classification": "CREDIT",
            "expiration_month": "2",
            "expiration_year": "2030",
            "last_four_digits": "5454"
}
        }
    ],
    "fields": {
      "booking_reference": "REF-1234"
    }
  }',
                [
                    'status' => Charge::SUCCEEDED,
                    'message' => null,
                ],
            ],
            'delivered' => [
                '{
    "id": "test",
    "created_at": "2024-10-24T20:51:57Z",
    "price": {
        "value": "106200",
        "currency": {
            "code": "EUR"
        }
    },
    "purchase": {
        "value": "120000",
        "currency": {
            "code": "USD"
        }
    },
    "status": "delivered",
    "external_reference": "1234",
    "country": "ES",
    "recipient": {
        "id": "UUO"
    },
    "charge_events": [
        {
"payment_method_details": {
            "type": "MC",
            "brand": "MASTERCARD",
            "card_classification": "CREDIT",
            "expiration_month": "2",
            "expiration_year": "2030",
            "last_four_digits": "5454"
}
        }
    ],
    "fields": {
      "booking_reference": "REF-1234"
    }
  }',
                [
                    'status' => Charge::SUCCEEDED,
                    'message' => null,
                ],
            ],
            'failed' => [
                '{
    "id": "test",
    "created_at": "2024-10-24T20:51:57Z",
    "price": {
        "value": "106200",
        "currency": {
            "code": "EUR"
        }
    },
    "purchase": {
        "value": "120000",
        "currency": {
            "code": "USD"
        }
    },
    "status": "failed",
    "external_reference": "1234",
    "country": "ES",
    "recipient": {
        "id": "UUO"
    },
    "charge_events": [
        {
"payment_method_details": {
            "type": "MC",
            "brand": "MASTERCARD",
            "card_classification": "CREDIT",
            "expiration_month": "2",
            "expiration_year": "2030",
            "last_four_digits": "5454"
}
        }
    ],
    "failed_raw_reason": {
        "code": "056",
        "description": "Your transaction has not been successful, please try again. If the error persists select a different payment method or contact our customer support team."
    },
    "fields": {
      "booking_reference": "REF-1234"
    }
  }',
                [
                    'status' => Charge::FAILED,
                    'message' => 'Your transaction has not been successful, please try again. If the error persists select a different payment method or contact our customer support team.',
                ],
            ],
            'cancelled' => [
                '{
    "id": "test",
    "created_at": "2024-10-24T20:51:57Z",
    "price": {
        "value": "106200",
        "currency": {
            "code": "EUR"
        }
    },
    "purchase": {
        "value": "120000",
        "currency": {
            "code": "USD"
        }
    },
    "status": "cancelled",
    "external_reference": "1234",
    "country": "ES",
    "recipient": {
        "id": "UUO"
    },
    "charge_events": [
        {
            "payment_method_details": {
              "type": "bank_transfer"
            }
        }
    ],
    "fields": {
      "booking_reference": "1234"
    },
    "cancellation_reason": "expired"
  }',
                [
                    'status' => Charge::FAILED,
                    'message' => 'expired',
                ],
            ],
            'reversed' => [
                '{
    "id": "test",
    "created_at": "2024-10-24T20:51:57Z",
    "price": {
        "value": "106200",
        "currency": {
            "code": "EUR"
        }
    },
    "purchase": {
        "value": "120000",
        "currency": {
            "code": "USD"
        }
    },
    "status": "reversed",
    "external_reference": "1234",
    "country": "ES",
    "recipient": {
        "id": "UUO"
    },
    "charge_events": [
        {
"payment_method_details": {
            "type": "MC",
            "brand": "MASTERCARD",
            "card_classification": "CREDIT",
            "expiration_month": "2",
            "expiration_year": "2030",
            "last_four_digits": "5454"
}
        }
    ],
    "failed_raw_reason": {
        "code": "106",
        "description": "Refund finished"
    },
    "reversed_type": "refund",
    "entity_id": "RPTUDD91239F",
    "reversed_amount": {
      "value": "100000",
      "currency": {
        "code": "USD",
        "subunit_to_unit": "100"
      }
    },
    "fields": {
      "booking_reference": "1234"
    }
}',
                [
                    'status' => Charge::FAILED,
                    'message' => 'Refund finished',
                ],
            ],
        ];
    }
}
