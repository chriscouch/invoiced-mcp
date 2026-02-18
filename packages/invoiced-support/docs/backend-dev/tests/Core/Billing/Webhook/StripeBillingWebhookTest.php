<?php

namespace App\Tests\Core\Billing\Webhook;

use App\Companies\Models\Company;
use App\Core\Billing\Models\BillingProfile;
use App\Core\Billing\Webhook\StripeBillingWebhook;
use App\Tests\AppTestCase;
use Mockery;
use Stripe\Exception\InvalidRequestException as StripeError;
use Stripe\StripeClient;

class StripeBillingWebhookTest extends AppTestCase
{
    private static Company $company2;
    private static Company $company3;
    private static Company $company4;
    private static string $stripeCustomerId;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        self::$stripeCustomerId = uniqid();
        self::getService('test.database')->delete('BillingHistories', ['stripe_transaction' => 'charge_failed']);
    }

    public static function tearDownAfterClass(): void
    {
        parent::tearDownAfterClass();
        self::$company2->delete();
        self::$company3->delete();
        self::$company4->delete();
        self::getService('test.database')->delete('BillingProfiles', ['stripe_customer' => self::$stripeCustomerId]);
    }

    private function getWebhook(): StripeBillingWebhook
    {
        return self::getService('test.stripe_billing_webhook');
    }

    public function testGetHandleMethod(): void
    {
        $webhook = $this->getWebhook();
        $this->assertEquals('handleChargeFailed', $webhook->getHandleMethod((object) ['type' => 'charge.failed']));
        $this->assertEquals('handleCustomerSubscriptionUpdated', $webhook->getHandleMethod((object) ['type' => 'customer.subscription.updated']));
    }

    public function testHandleInvalidEvent(): void
    {
        $webhook = $this->getWebhook();
        $this->assertEquals(StripeBillingWebhook::ERROR_INVALID_EVENT, $webhook->handle([]));
    }

    public function testHandleLivemodeMismatch(): void
    {
        $webhook = $this->getWebhook();

        $event = [
            'id' => 'evt_test',
            'livemode' => true,
        ];

        $this->assertEquals(StripeBillingWebhook::ERROR_LIVEMODE_MISMATCH, $webhook->handle($event));
    }

    public function testHandleConnectEvent(): void
    {
        $webhook = $this->getWebhook();

        $event = [
            'id' => 'evt_test',
            'livemode' => false,
            'user_id' => 'usr_1234',
        ];

        $this->assertEquals(StripeBillingWebhook::ERROR_STRIPE_CONNECT_EVENT, $webhook->handle($event));
    }

    public function testHandleException(): void
    {
        $webhook = $this->getWebhook();

        $e = new StripeError('error');
        $staticEvent = Mockery::mock('Stripe\Event');
        $staticEvent->shouldReceive('retrieve')
            ->withArgs(['evt_test'])
            ->andThrow($e);
        $stripe = Mockery::mock(StripeClient::class);
        $stripe->events = $staticEvent;
        $webhook->setStripe($stripe);

        $event = [
            'id' => 'evt_test',
            'livemode' => false,
            'type' => 'customer.subscription.updated',
        ];

        $this->assertEquals(StripeBillingWebhook::ERROR_GENERIC, $webhook->handle($event));
    }

    public function testHandleCustomerNotFound(): void
    {
        $webhook = $this->getWebhook();

        $validatedEvent = new \stdClass();
        $validatedEvent->type = 'customer.subscription.updated';
        $validatedEvent->data = new \stdClass();
        $validatedEvent->data->object = new \stdClass();
        $validatedEvent->data->object->customer = self::$stripeCustomerId;
        $staticEvent = Mockery::mock('Stripe\Event');
        $staticEvent->shouldReceive('retrieve')
            ->withArgs(['evt_test2'])
            ->andReturn($validatedEvent);
        $stripe = Mockery::mock(StripeClient::class);
        $stripe->events = $staticEvent;
        $webhook->setStripe($stripe);

        $event = [
            'id' => 'evt_test2',
            'livemode' => false,
            'type' => 'customer.subscription.updated',
        ];

        $this->assertEquals(StripeBillingWebhook::ERROR_CUSTOMER_NOT_FOUND, $webhook->handle($event));
    }

    public function testHandleNotSupported(): void
    {
        $webhook = $this->getWebhook();

        self::$company3 = new Company();
        self::$company3->name = 'testHandleNotSupported';
        self::$company3->username = 'testHandleNotSupported';
        self::$company3->saveOrFail();

        $billingProfile = BillingProfile::getOrCreate(self::$company3);
        $billingProfile->billing_system = 'stripe';
        $billingProfile->stripe_customer = self::$stripeCustomerId;
        $billingProfile->saveOrFail();

        $staticEvent = Mockery::mock('Stripe\Event');
        $validatedEvent = new \stdClass();
        $validatedEvent->type = 'event.not_found';
        $validatedEvent->data = new \stdClass();
        $validatedEvent->data->object = new \stdClass();
        $validatedEvent->data->object->customer = self::$stripeCustomerId;
        $staticEvent->shouldReceive('retrieve')
            ->withArgs(['evt_test3'])
            ->andReturn($validatedEvent);
        $stripe = Mockery::mock(StripeClient::class);
        $stripe->events = $staticEvent;
        $webhook->setStripe($stripe);

        $event = [
            'id' => 'evt_test3',
            'livemode' => false,
            'type' => 'event.not_found',
        ];

        $this->assertEquals(StripeBillingWebhook::ERROR_EVENT_NOT_SUPPORTED, $webhook->handle($event));
    }

    public function testHandle(): void
    {
        $webhook = $this->getWebhook();

        $validatedEvent = new \stdClass();
        $validatedEvent->type = 'test';
        $validatedEvent->data = new \stdClass();
        $validatedEvent->data->object = new \stdClass();
        $validatedEvent->data->object->customer = self::$stripeCustomerId;
        $staticEvent = Mockery::mock('Stripe\Event');
        $staticEvent->shouldReceive('retrieve')
            ->withArgs(['evt_test'])
            ->andReturn($validatedEvent);
        $stripe = Mockery::mock(StripeClient::class);
        $stripe->events = $staticEvent;
        $webhook->setStripe($stripe);

        $event = [
            'id' => 'evt_test',
            'livemode' => false,
            'type' => 'test',
        ];

        $this->assertEquals(StripeBillingWebhook::SUCCESS, $webhook->handle($event));
    }

    /**
     * @doesNotPerformAssertions
     */
    public function testHandleChargeFailed(): void
    {
        $webhook = $this->getWebhook();

        $event = new \stdClass();
        $event->id = 'charge_failed';
        $event->customer = self::$stripeCustomerId;
        $event->description = 'Descr';
        $event->created = 12;
        $event->amount = 1000;
        $event->fraud_details = new \stdClass();
        $event->source = new \stdClass();
        $event->source->object = 'card';
        $event->source->last4 = '1234';
        $event->source->exp_month = '05';
        $event->source->exp_year = '2014';
        $event->source->brand = 'Visa';
        $event->failure_message = 'Fail!';

        $company = new Company();
        $company->name = 'Test';
        $company->username = 'Test'.random_int(0, 1000000);
        $billingProfile = new BillingProfile();

        $webhook->handleChargeFailed($event, $billingProfile);
    }

    public function testHandleChargeFailedFraudulent(): void
    {
        $webhook = $this->getWebhook();

        $event = json_decode('{
      "id": "ch_GlctAXvOOEQcQU",
      "object": "charge",
      "amount": 10000,
      "amount_refunded": 0,
      "application": null,
      "application_fee": null,
      "application_fee_amount": null,
      "balance_transaction": null,
      "billing_details": {
        "address": {
          "city": "Miami",
          "country": "US",
          "line1": "132 jf st road",
          "line2": null,
          "postal_code": "33125",
          "state": "FL"
        },
        "email": null,
        "name": "Cynthia Bracamonte",
        "phone": null
      },
      "captured": false,
      "created": 1582169022,
      "currency": "usd",
      "customer": "cus_GiwLHpKHFT9owD",
      "description": "Invoice 30CCFF08-0001",
      "destination": null,
      "dispute": null,
      "disputed": false,
      "failure_code": "card_declined",
      "failure_message": "Your card was declined.",
      "fraud_details": {
        "stripe_report": "fraudulent"
      },
      "invoice": "in_GiwLLPYGFG3Occ",
      "livemode": true,
      "metadata": {
      },
      "on_behalf_of": null,
      "order": null,
      "outcome": {
        "network_status": "not_sent_to_network",
        "reason": "highest_risk_level",
        "risk_level": "highest",
        "risk_score": 95,
        "rule": "block_if_high_risk",
        "seller_message": "Stripe blocked this payment as too risky.",
        "type": "blocked"
      },
      "paid": false,
      "payment_intent": "pi_0GBVUOVhp3yppPrIqmTMQU0c",
      "payment_method": "card_GiwK6VdX3sPFQu",
      "payment_method_details": {
        "card": {
          "brand": "visa",
          "checks": {
            "address_line1_check": "unavailable",
            "address_postal_code_check": "unavailable",
            "cvc_check": null
          },
          "country": "US",
          "exp_month": 10,
          "exp_year": 2021,
          "fingerprint": "JL4OZmG5kS4xASdU",
          "funding": "debit",
          "installments": null,
          "last4": "1450",
          "network": "visa",
          "three_d_secure": null,
          "wallet": null
        },
        "type": "card"
      },
      "receipt_email": "carlhaupt988@biehes.com",
      "receipt_number": null,
      "receipt_url": "https://pay.stripe.com/receipts/acct_1t8VVhp3yppPrIpyytSn/ch_GlctAXvOOEQcQU/rcpt_GlctL5ftcVJ5wPlmi3LymUavhTo3QVs",
      "refunded": false,
      "refunds": {
        "object": "list",
        "data": [
        ],
        "has_more": false,
        "total_count": 0,
        "url": "/v1/charges/ch_GlctAXvOOEQcQU/refunds"
      },
      "review": null,
      "shipping": null,
      "source": {
        "id": "card_GiwK6VdX3sPFQu",
        "object": "card",
        "address_city": "Miami",
        "address_country": "US",
        "address_line1": "132 jf st road",
        "address_line1_check": "unavailable",
        "address_line2": null,
        "address_state": "FL",
        "address_zip": "33125",
        "address_zip_check": "unavailable",
        "brand": "Visa",
        "country": "US",
        "customer": "cus_GiwLHpKHFT9owD",
        "cvc_check": null,
        "dynamic_last4": null,
        "exp_month": 10,
        "exp_year": 2021,
        "fingerprint": "JL4OZmG5kS4xASdU",
        "funding": "debit",
        "last4": "1450",
        "metadata": {
        },
        "name": "Cynthia Bracamonte",
        "tokenization_method": null
      },
      "source_transfer": null,
      "statement_descriptor": null,
      "statement_descriptor_suffix": null,
      "status": "failed",
      "transfer_data": null,
      "transfer_group": null
    }');

        self::$company2 = new Company();
        self::$company2->name = 'testHandleChargeFailedFraudulent';
        self::$company2->username = 'testHandleChargeFailedFraudulent';
        self::$company2->saveOrFail();
        $billingProfile = BillingProfile::getOrCreate(self::$company2);

        $webhook->handleChargeFailed($event, $billingProfile);

        $this->assertTrue(self::$company2->refresh()->fraud);
    }

    public function testHandleSubscriptionCreated(): void
    {
        $webhook = $this->getWebhook();

        $event = new \stdClass();
        $event->status = 'trialing';
        $event->trial_end = 100;
        $event->current_period_end = 101;
        $event->plan = new \stdClass();
        $event->plan->id = 'growth';

        $billingProfile = new BillingProfile();

        $webhook->handleCustomerSubscriptionCreated($event, $billingProfile);

        $this->assertFalse($billingProfile->past_due);
    }

    public function testHandleSubscriptionUnpaid(): void
    {
        $webhook = $this->getWebhook();

        $event = new \stdClass();
        $event->status = 'unpaid';
        $event->trial_end = 100;
        $event->plan = new \stdClass();
        $event->plan->id = 'startup';

        $billingProfile = new BillingProfile();

        $webhook->handleCustomerSubscriptionUpdated($event, $billingProfile);

        $this->assertFalse($billingProfile->past_due);
    }

    public function testHandleSubscriptionPastDue(): void
    {
        $webhook = $this->getWebhook();

        $event = new \stdClass();
        $event->status = 'past_due';
        $event->trial_end = 100;
        $event->current_period_end = 101;
        $event->plan = new \stdClass();
        $event->plan->id = 'startup';

        $billingProfile = new BillingProfile();

        $webhook->handleCustomerSubscriptionUpdated($event, $billingProfile);

        $this->assertTrue($billingProfile->past_due);
    }

    public function testHandleSubscriptionActive(): void
    {
        $webhook = $this->getWebhook();

        $event = new \stdClass();
        $event->status = 'active';
        $event->trial_end = 100;
        $event->current_period_end = 1000;
        $event->plan = new \stdClass();
        $event->plan->id = 'startup';

        $billingProfile = new BillingProfile();

        $webhook->handleCustomerSubscriptionUpdated($event, $billingProfile);

        $this->assertFalse($billingProfile->past_due);
    }

    public function testHandleSubscriptionCanceled(): void
    {
        $webhook = $this->getWebhook();

        $event = new \stdClass();
        $event->cancellation_details = (object) ['reason' => 'payment_failed'];

        $company = Mockery::mock(Company::class.'[save]');
        $company->shouldReceive('save')->once();
        $billingProfile = new BillingProfile();
        $company->billing_profile = $billingProfile;
        $webhook->setCompanies([$company]);

        $webhook->handleCustomerSubscriptionDeleted($event, $billingProfile);

        $this->assertTrue($company->canceled);
    }

    public function testHandleInvoiceUpdated(): void
    {
        $webhook = $this->getWebhook();

        $event = new \stdClass();
        $event->id = 'inv_upd_test';
        $event->auto_advance = false;
        $event->status = 'open';

        $staticInvoice = Mockery::mock('Stripe\Invoice');
        $staticInvoice->shouldReceive('retrieve')
            ->withArgs(['inv_upd_test'])
            ->andReturn($staticInvoice);
        $staticInvoice->shouldReceive('voidInvoice')
            ->withArgs([])
            ->once();
        $billingProfile = new BillingProfile();
        $stripe = Mockery::mock(StripeClient::class);
        $stripe->invoices = $staticInvoice;
        $webhook->setStripe($stripe);

        $webhook->handleInvoiceUpdated($event, $billingProfile);
    }

    public function testHandleChargeRefundedFraudulent(): void
    {
        $webhook = $this->getWebhook();

        $event = json_decode('{
  "id": "ch_GlXvURYW29FznI",
  "object": "charge",
  "livemode": true,
  "payment_intent": "pi_0GE0q6Vhp3yppPrIDeX2iFbT",
  "status": "succeeded",
  "amount": 10000,
  "amount_refunded": 10000,
  "application": null,
  "application_fee": null,
  "application_fee_amount": null,
  "balance_transaction": "txn_GlXv7iMx1SA7RD",
  "billing_details": {
    "address": {
      "city": "Gastonia",
      "country": "US",
      "line1": "p o box 1116",
      "line2": null,
      "postal_code": "28053",
      "state": "NC"
    },
    "email": null,
    "name": "M Roy",
    "phone": null
  },
  "captured": true,
  "created": 1582150578,
  "currency": "usd",
  "customer": "cus_GlWuDOWntanMfT",
  "description": "Invoice 91B40D7F-0001",
  "destination": null,
  "dispute": null,
  "disputed": false,
  "failure_code": null,
  "failure_message": null,
  "fraud_details": {
    "user_report": "fraudulent"
  },
  "invoice": "in_GlWuUzzFK8Os2w",
  "metadata": {
  },
  "on_behalf_of": null,
  "order": null,
  "outcome": {
    "network_status": "approved_by_network",
    "reason": null,
    "risk_level": "normal",
    "risk_score": 57,
    "seller_message": "Payment complete.",
    "type": "authorized"
  },
  "paid": true,
  "payment_method": "card_GlWuM3iCi28E3Q",
  "payment_method_details": {
    "card": {
      "brand": "visa",
      "checks": {
        "address_line1_check": "fail",
        "address_postal_code_check": "pass",
        "cvc_check": null
      },
      "country": "US",
      "exp_month": 5,
      "exp_year": 2021,
      "fingerprint": "Ad40SqcFw0buBAv3",
      "funding": "credit",
      "installments": null,
      "last4": "2813",
      "network": "visa",
      "three_d_secure": null,
      "wallet": null
    },
    "type": "card"
  },
  "receipt_email": "brynnmarrjewelers@ifugru.com",
  "receipt_number": "2140-4761",
  "receipt_url": "https://pay.stripe.com/receipts/acct_1t8VVhp3yppPrIpyytSn/ch_GlXvURYW29FznI/rcpt_GlXvT4eXj0kP3swrPZ2FYll8IbnxmdU",
  "refunded": true,
  "refunds": {
    "object": "list",
    "data": [
      {
        "id": "re_GlZ11OTliB1Q6f",
        "object": "refund",
        "amount": 10000,
        "balance_transaction": "txn_GlZ1EtfHMQEkXN",
        "charge": "ch_GlXvURYW29FznI",
        "created": 1582154608,
        "currency": "usd",
        "metadata": {
        },
        "payment_intent": "pi_0GE0q6Vhp3yppPrIDeX2iFbT",
        "reason": "fraudulent",
        "receipt_number": "3814-7982",
        "source_transfer_reversal": null,
        "status": "succeeded",
        "transfer_reversal": null
      }
    ],
    "has_more": false,
    "total_count": 1,
    "url": "/v1/charges/ch_GlXvURYW29FznI/refunds"
  },
  "review": null,
  "shipping": null,
  "source": {
    "id": "card_GlWuM3iCi28E3Q",
    "object": "card",
    "address_city": "Gastonia",
    "address_country": "US",
    "address_line1": "p o box 1116",
    "address_line1_check": "fail",
    "address_line2": null,
    "address_state": "NC",
    "address_zip": "28053",
    "address_zip_check": "pass",
    "brand": "Visa",
    "country": "US",
    "customer": "cus_GlWuDOWntanMfT",
    "cvc_check": null,
    "dynamic_last4": null,
    "exp_month": 5,
    "exp_year": 2021,
    "fingerprint": "Ad40SqcFw0buBAv3",
    "funding": "credit",
    "last4": "2813",
    "metadata": {
    },
    "name": "M Roy",
    "tokenization_method": null
  },
  "source_transfer": null,
  "statement_descriptor": null,
  "statement_descriptor_suffix": null,
  "transfer_data": null,
  "transfer_group": null
}');

        self::$company4 = new Company();
        self::$company4->name = 'testHandleChargeRefundedFraudulent';
        self::$company4->username = 'testHandleChargeRefundedFraudulent';
        self::$company4->saveOrFail();
        $billingProfile = BillingProfile::getOrCreate(self::$company4);

        $webhook->handleChargeRefunded($event, $billingProfile);

        $this->assertTrue(self::$company4->refresh()->fraud);
    }
}
