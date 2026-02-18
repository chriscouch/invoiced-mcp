<?php

namespace App\Tests\Integrations\Flywire\Webhooks;

use App\CashApplication\Models\Payment;
use App\Integrations\Flywire\FlywirePrivateClient;
use App\Integrations\Flywire\Models\FlywirePayment;
use App\Integrations\Flywire\Operations\SaveFlywirePayment;
use App\Integrations\Flywire\Webhooks\FlywirePaymentWebhook;
use App\PaymentProcessing\Gateways\FlywireGateway;
use App\PaymentProcessing\Models\Charge;
use App\PaymentProcessing\Operations\UpdateChargeStatus;
use App\Tests\AppTestCase;
use Mockery;

class FlywirePaymentWebhookTest extends AppTestCase
{
    private static Charge $charge;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        self::hasCompany();
        self::hasCustomer();
        self::hasMerchantAccount(FlywireGateway::ID, 'gateway_'.time());

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
    public function testProcess(string $webhookJson, string $paymentJson, array $expected): void
    {
        $webhookData = json_decode($webhookJson, true);
        $webhookData['merchant_account_id'] = self::$merchantAccount->id;
        $updateChargeStatus = Mockery::mock(UpdateChargeStatus::class);
        $updateChargeStatus->shouldReceive('saveStatus')->withSomeOfArgs($expected['status'], $expected['message'])->times(Charge::PENDING === $expected['status'] ? 0 : 1);
        $client = Mockery::mock(FlywirePrivateClient::class);
        $paymentData = json_decode($paymentJson, true);
        $paymentData['recipient']['fields'] = [
            [
                'id' => 'invoiced_ref',
                'value' => $paymentData['callback']['id'] ?? null,
            ]
        ];
        $client->shouldReceive('getPayment')->andReturn($paymentData);
        $operation = new SaveFlywirePayment($client, $updateChargeStatus, self::getService('test.payment_flow_reconcile'));
        $webhook = new FlywirePaymentWebhook(self::getService('test.tenant'), $operation);

        $webhook->process(self::$company, $webhookData);

        /** @var FlywirePayment[] $payments */
        $payments = FlywirePayment::where('payment_id', $webhookData['data']['payment_id'])->execute();
        $this->assertCount(1, $payments);
        $this->assertEquals($webhookData['data']['amount_from'] / 100, $payments[0]->amount_from);
        $this->assertEquals($webhookData['data']['amount_to'] / 100, $payments[0]->amount_to);
        $this->assertEquals(strtolower($webhookData['data']['currency_from']), $payments[0]->currency_from);
        $this->assertEquals(strtolower($webhookData['data']['currency_to']), $payments[0]->currency_to);

        $expectedStatus = 'cancelled' === $webhookData['data']['status'] ? 'canceled' : $webhookData['data']['status'];
        $this->assertEquals(ucfirst($expectedStatus), $payments[0]->status->name);
        $this->assertEquals($paymentData['callback']['id'] ?? null, $payments[0]->reference);

        /** @var Payment[] $payments */
        $payments = Payment::where('reference', $webhookData['data']['payment_id'])->execute();
        $this->assertEquals($expected['payments'] ?? [], array_map(fn ($payment) => $payment->toArray(), $payments));
    }

    public function dataProvider(): array
    {
        return [
            'initiated' => [
                '{
  "event_type": "initiated",
  "event_date": "2021-10-12T19:26:01Z",
  "event_resource": "payments",
  "data": {
    "payment_id": "test",
    "amount_from": "106200",
    "currency_from": "EUR",
    "amount_to": "120000",
    "currency_to": "USD",
    "status": "initiated",
    "expiration_date": "2021-10-14T21:08:52Z",
    "callback": {"id": "1234"},
    "country": "ES",
    "payment_method": {
      "type": "card"
    },
    "fields": {
      "booking_reference": "REF-1234"
    },
    "payer": {
      "first_name": "John",
      "last_name": "Doe",
      "middle_name": null,
      "address1": "Carrer del Gravador Esteve",
      "address2": null,
      "city": "Valencia",
      "state": null,
      "zip": "",
      "country": "ES",
      "phone": "+34 1234 567 890",
      "email": "john.doe@flywire.com"
    }
  }
}',
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
    "status": "initiated",
    "due_at": "2021-10-14T21:08:52Z",
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
            'processed' => [
                '{
  "event_type": "processed",
  "event_date": "2021-10-12T19:26:01Z",
  "event_resource": "charges",
  "data": {
    "payment_id": "test",
    "amount_from": "106200",
    "currency_from": "EUR",
    "amount_to": "120000",
    "currency_to": "USD",
    "status": "processed",
    "expiration_date": "2021-10-14T21:08:52Z",
    "callback": {"id": "1234"},
    "country": "ES",
    "payment_method": {
      "type": "card",
      "brand": "mastercard",
      "card_classification": "credit",
      "card_expiration": "03/2030",
      "last_four_digits": "5454"
    },
    "fields": {
      "booking_reference": "REF-1234"
    }
  }
}',
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
    "due_at": "2021-10-14T21:08:52Z",
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
  "event_type": "guaranteed",
  "event_date": "2021-10-12T19:26:01Z",
  "event_resource": "payments",
  "data": {
    "payment_id": "test",
    "amount_from": "106200",
    "currency_from": "EUR",
    "amount_to": "120000",
    "currency_to": "USD",
    "status": "guaranteed",
    "expiration_date": "2021-10-14T21:08:52Z",
    "callback": {"id": "1234"},
    "country": "ES",
    "payment_method": {
      "type": "card",
      "brand": "mastercard",
      "card_classification": "credit",
      "card_expiration": "03/2030",
      "last_four_digits": "5454"
    },
    "fields": {
      "booking_reference": "REF-1234"
    }
  }
}',
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
    "status": "guaranteed",
    "callback": {"id": "1234"},
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
  "event_type": "delivered",
  "event_date": "2021-10-12T19:26:01Z",
  "event_resource": "payments",
  "data": {
    "payment_id": "test",
    "amount_from": "106200",
    "currency_from": "EUR",
    "amount_to": "120000",
    "currency_to": "USD",
    "status": "delivered",
    "expiration_date": "2021-10-14T21:08:52Z",
    "callback": {"id": "1234"},
    "country": "ES",
    "payment_method": {
      "type": "card",
      "brand": "mastercard",
      "card_classification": "credit",
      "card_expiration": "03/2030",
      "last_four_digits": "5454"
    },
    "fields": {
      "booking_reference": "REF-1234"
    }
  }
}',
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
    "callback": {"id": "1234"},
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
  "event_type": "failed",
  "event_date": "2021-10-12T21:09:17Z",
  "event_resource": "charges",
  "data": {
    "payment_id": "test",
    "amount_from": "106200",
    "currency_from": "EUR",
    "amount_to": "120000",
    "currency_to": "USD",
    "status": "failed",
    "expiration_date": "2021-10-14T21:08:52Z",
    "callback": {"id": "1234"},
    "country": "ES",
    "payment_method": {
      "type": "card",
      "brand": "mastercard",
      "card_classification": "credit",
      "card_expiration": "02/2022",
      "last_four_digits": "5454"
    },
    "fields": {
      "booking_reference": "REF-1234"
    }
  }
}',
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
    "expiration_date": "2021-10-14T21:08:52Z",
    "callback": {"id": "1234"},
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
  "event_type": "cancelled",
  "event_date": "2021-10-12T21:12:29Z",
  "event_resource": "payments",
  "data": {
    "payment_id": "test",
    "amount_from": "106200",
    "currency_from": "EUR",
    "amount_to": "120000",
    "currency_to": "USD",
    "status": "cancelled",
    "expiration_date": "2021-10-21T19:26:01Z",
    "callback": {"id": "1234"},
    "country": "ES",
    "payment_method": {
      "type": "bank_transfer"
    },
    "fields": {
      "booking_reference": "1234"
    },
    "cancellation_reason": "expired"
  }
}',
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
    "callback": {"id": "1234"},
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
  "event_type": "reversed",
  "event_date": "2021-10-12T21:12:29Z",
  "event_resource": "payments",
  "data": {
    "payment_id": "test",
    "amount_from": "106200",
    "currency_from": "EUR",
    "amount_to": "120000",
    "currency_to": "USD",
    "status": "reversed",
    "expiration_date": "2021-10-21T19:26:01Z",
    "callback": {"id": "1234"},
    "country": "ES",
    "payment_method": {
      "type": "card"
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
  }
}',
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
    "expiration_date": "2021-10-14T21:08:52Z",
    "callback": {"id": "1234"},
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
    "fields": {
      "booking_reference": "REF-1234"
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
