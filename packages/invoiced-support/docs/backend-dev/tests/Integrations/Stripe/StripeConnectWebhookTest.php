<?php

namespace App\Tests\Integrations\Stripe;

use App\Integrations\Stripe\StripeConnectWebhook;
use App\Tests\AppTestCase;

class StripeConnectWebhookTest extends AppTestCase
{
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        self::hasCompany();
        self::hasCustomer();
        self::hasMerchantAccount('stripe', credentials: ['key' => 'test']);
        self::hasBankAccount('stripe');
        self::$bankAccount->gateway_setup_intent = 'seti_1PagbdKzrtJqUQfw8oNZFgYZ';
        self::$bankAccount->verified = false;
        self::$bankAccount->saveOrFail();
    }

    private function getWebhook(): StripeConnectWebhook
    {
        return self::getService('test.stripe_connect_webhook');
    }

    public function testSetupIntentSucceeded(): void
    {
        $webhook = $this->getWebhook();
        $event = '{
  "id": "evt_1NG8Du2eZvKYlo2CUI79vXWy",
  "object": "event",
  "api_version": "2019-02-19",
  "created": 1686089970,
  "data": {
    "object": {
      "id": "seti_1PagbdKzrtJqUQfw8oNZFgYZ",
      "object": "setup_intent",
      "application": "ca_1t8e7QhQHzsytwLAe6xKivcP591wVU7u",
      "automatic_payment_methods": null,
      "cancellation_reason": null,
      "client_secret": "seti_1PagbdKzrtJqUQfw8oNZFgYZ_secret_QRZlq6qao3KFcbjpqKc6Wh1yR53FtkK",
      "created": 1720541369,
      "customer": "cus_QRA40a4ybyQy8N",
      "description": null,
      "flow_directions": null,
      "last_setup_error": null,
      "latest_attempt": "setatt_1Pagc8KzrtJqUQfwOhbymvAx",
      "livemode": false,
      "mandate": "mandate_1Pagc9KzrtJqUQfwG812IrRh",
      "metadata": {
      },
      "next_action": null,
      "on_behalf_of": null,
      "payment_method": "pm_1Pagc8KzrtJqUQfw9NhU7rJI",
      "payment_method_configuration_details": null,
      "payment_method_options": {
        "us_bank_account": {
          "mandate_options": {
          },
          "verification_method": "automatic"
        }
      },
      "payment_method_types": [
        "us_bank_account"
      ],
      "single_use_mandate": null,
      "status": "succeeded",
      "usage": "off_session"
    }
  },
  "livemode": false,
  "pending_webhooks": 0,
  "request": {
    "id": null,
    "idempotency_key": null
  },
  "type": "setup_intent.succeeded"
}';
        $event = json_decode($event, true);

        $webhook->process(self::$company, $event);

        $this->assertTrue(self::$bankAccount->refresh()->verified);
    }
}
