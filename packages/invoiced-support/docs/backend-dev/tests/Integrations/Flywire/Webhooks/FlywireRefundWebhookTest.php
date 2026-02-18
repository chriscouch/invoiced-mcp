<?php

namespace App\Tests\Integrations\Flywire\Webhooks;

use App\Integrations\Flywire\FlywirePrivateClient;
use App\Integrations\Flywire\Operations\SaveFlywireRefund;
use App\Integrations\Flywire\Webhooks\FlywireRefundWebhook;
use App\PaymentProcessing\Gateways\FlywireGateway;
use App\PaymentProcessing\Models\Charge;
use App\PaymentProcessing\Models\Refund;
use App\Tests\AppTestCase;
use Mockery;

class FlywireRefundWebhookTest extends AppTestCase
{
    public static Refund $refund1;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        self::hasCompany();
        self::hasCustomer();

        $charge = new Charge();
        $charge->customer = self::$customer;
        $charge->currency = 'usd';
        $charge->amount = 100;
        $charge->status = Charge::PENDING;
        $charge->gateway = FlywireGateway::ID;
        $charge->gateway_id = 'PTU146221637';
        $charge->last_status_check = 0;
        $charge->saveOrFail();

        $refund = new Refund();
        $refund->charge = $charge;
        $refund->amount = $charge->amount;
        $refund->currency = $charge->currency;
        $refund->status = 'succeeded';
        $refund->gateway = $charge->gateway;
        $refund->gateway_id = 'RPTUE0D63641';
        $refund->saveOrFail();

        self::$refund1 = $refund;
    }

    /**
     * @dataProvider dataProvider
     */
    public function testProcess(string $data, string $refund, array $expected): void
    {
        $input = json_decode($data, true);
        $refund = json_decode($refund, true);
        $client = Mockery::mock(FlywirePrivateClient::class);
        $client->shouldReceive('getRefund')->andReturn($refund);
        $operation = new SaveFlywireRefund($client);
        $webhook = new FlywireRefundWebhook(self::getService('test.tenant'), $operation);
        $webhook->process(self::$company, $input);
        $this->assertEquals($expected['status'], self::$refund1->refresh()->status);
    }

    public function dataProvider(): array
    {
        return [
            'initiated' => [
                '
           {
  "event_type": "created",
  "event_date": "2021-05-20T11:24:45Z",
  "event_resource": "refunds",
  "data": {
    "refund_id": "RPTUE0D63641",
    "payment_id": "PTU146221637",
    "external_reference": "a-reference",
    "bundle_id": "BUDR0AEA9E47",
    "created_at": "2024-09-17T19:47:38Z",
    "recipient_id": "UUO",
    "status": "initiated",
    "amount": "4800",
    "currency": "EUR",
    "amount_to": "4800",
    "currency_to": "EUR"
  }
}
            ',
                '
           {
    "id": "RPTUE0D63641",
    "payment": {
        "id": "PTU146221637"
    },
    "external_reference": "a-reference",
    "bundle": {
        "id": "BUDR0AEA9E47"
     },
    "created_at": "2024-09-17T19:47:38Z",
    "sender": {
        "id": "UUO"
    },
    "status": "initiated",
    "amount": {
        "value": "4800",
        "currency": {
            "code": "EUR"
         }
     },
    "amount_to": {
        "value": "4800",
        "currency": {
            "code": "EUR"
         }
     }
}
            ',
                [
                    'status' => 'pending',
                ],
            ],
            'received' => [
                '
          {
  "event_type": "received",
  "event_date": "2021-05-20T11:24:45Z",
  "event_resource": "refunds",
  "data": {
    "refund_id": "RPTUE0D63641",
    "payment_id": "PTU146221637",
    "external_reference": "a-reference",
    "bundle_id": "BUDR0AEA9E47",
    "created_at": "2024-09-17T19:47:38Z",
    "recipient_id": "UUO",
    "status": "received",
    "amount": "4800",
    "currency": "EUR",
    "amount_to": "4800",
    "currency_to": "EUR"
  }
}            ',
                '
           {
    "id": "RPTUE0D63641",
    "payment": {
        "id": "PTU146221637"
    },
    "external_reference": "a-reference",
    "bundle": {
        "id": "BUDR0AEA9E47"
     },
    "created_at": "2024-09-17T19:47:38Z",
    "sender": {
        "id": "UUO"
    },
    "status": "received",
    "amount": {
        "value": "4800",
        "currency": {
            "code": "EUR"
         }
     },
    "amount_to": {
        "value": "4800",
        "currency": {
            "code": "EUR"
         }
     }
}
            ',
                [
                    'status' => 'succeeded',
                ],
            ],
            'finished' => [
                '
{
  "event_type": "finished",
  "event_date": "2021-05-20T11:24:45Z",
  "event_resource": "refunds",
  "data": {
    "refund_id": "RPTUE0D63641",
    "payment_id": "PTU146221637",
    "external_reference": "a-reference",
    "bundle_id": "BUDR0AEA9E47",
    "created_at": "2024-09-17T19:47:38Z",
    "recipient_id": "UUO",
    "status": "finished",
    "amount": "4800",
    "currency": "EUR",
    "amount_to": "4800",
    "currency_to": "EUR"
  }
}
            ',
                '
           {
    "id": "RPTUE0D63641",
    "payment": {
        "id": "PTU146221637"
    },
    "external_reference": "a-reference",
    "bundle": {
        "id": "BUDR0AEA9E47"
     },
    "created_at": "2024-09-17T19:47:38Z",
    "sender": {
        "id": "UUO"
    },
    "status": "finished",
    "amount": {
        "value": "4800",
        "currency": {
            "code": "EUR"
         }
     },
    "amount_to": {
        "value": "4800",
        "currency": {
            "code": "EUR"
         }
     }
}
            ',
                [
                    'status' => 'succeeded',
                ],
            ],
            'returned' => [
                '
{
  "event_type": "returned",
  "event_date": "2021-05-20T11:24:45Z",
  "event_resource": "refunds",
  "data": {
    "refund_id": "RPTUE0D63641",
    "payment_id": "PTU146221637",
    "external_reference": "a-reference",
    "bundle_id": "BUDR0AEA9E47",
    "created_at": "2024-09-17T19:47:38Z",
    "recipient_id": "UUO",
    "status": "returned",
    "amount": "4800",
    "currency": "EUR",
    "amount_to": "4800",
    "currency_to": "EUR"
  }
}
            ',
                '
           {
    "id": "RPTUE0D63641",
    "payment": {
        "id": "PTU146221637"
    },
    "external_reference": "a-reference",
    "bundle": {
        "id": "BUDR0AEA9E47"
     },
    "created_at": "2024-09-17T19:47:38Z",
    "sender": {
        "id": "UUO"
    },
    "status": "returned",
    "amount": {
        "value": "4800",
        "currency": {
            "code": "EUR"
         }
     },
    "amount_to": {
        "value": "4800",
        "currency": {
            "code": "EUR"
         }
     }
}
            ',
                [
                    'status' => 'failed',
                ],
            ],
            'cancelled' => [
                '
  {
  "event_type": "cancelled",
  "event_date": "2021-05-20T11:24:45Z",
  "event_resource": "refunds",
  "data": {
    "refund_id": "RPTUE0D63641",
    "payment_id": "PTU146221637",
    "external_reference": "a-reference",
    "bundle_id": null,
    "created_at": "2024-09-17T19:47:38Z",
    "recipient_id": "UUO",
    "status": "cancelled",
    "amount": "4800",
    "currency": "EUR",
    "amount_to": "4800",
    "currency_to": "EUR"
  }
}
            ',
                '
           {
    "id": "RPTUE0D63641",
    "payment": {
        "id": "PTU146221637"
    },
    "external_reference": "a-reference",
    "bundle": {
        "id": "BUDR0AEA9E47"
     },
    "created_at": "2024-09-17T19:47:38Z",
    "sender": {
        "id": "UUO"
    },
    "status": "cancelled",
    "amount": {
        "value": "4800",
        "currency": {
            "code": "EUR"
         }
     },
    "amount_to": {
        "value": "4800",
        "currency": {
            "code": "EUR"
         }
     }
}
            ',
                [
                    'status' => 'failed',
                ],
            ],
        ];
    }
}
