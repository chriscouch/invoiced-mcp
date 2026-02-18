<?php

namespace App\Tests\Integrations\Adyen\EventSubscriber;

use App\Core\Statsd\StatsdClient;
use App\CustomerPortal\Libs\CustomerPortalEvents;
use App\Integrations\Adyen\EventSubscriber\AdyenTokenizationSubscriber;
use App\Integrations\Adyen\ValueObjects\AdyenTokenizationWebhookEvent;
use App\PaymentProcessing\Enums\PaymentFlowSource;
use App\PaymentProcessing\Enums\PaymentFlowStatus;
use App\PaymentProcessing\Gateways\AdyenGateway;
use App\PaymentProcessing\Models\Card;
use App\PaymentProcessing\Models\FlowFormSubmission;
use App\PaymentProcessing\Models\PaymentFlow;
use App\PaymentProcessing\Reconciliation\PaymentSourceReconciler;
use App\Tests\AppTestCase;
use Mockery;

class AdyenTokenizationSubscriberTest extends AppTestCase
{
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        self::hasCompany();
        self::hasCustomer();
        self::acceptsCreditCards();
        self::hasMerchantAccount(AdyenGateway::ID);
    }

    public function testProcess(): void
    {
        $reference = '9i17gboe04f5t13a8'.time();

        FlowFormSubmission::where('reference', $reference)->delete();
        PaymentFlow::where('identifier', $reference)->delete();

        $statsD = Mockery::mock(StatsdClient::class);
        $events = Mockery::mock(CustomerPortalEvents::class);
        $reconciler = new PaymentSourceReconciler();
        $reconciler->setStatsd($statsD);
        $subscriber = new AdyenTokenizationSubscriber($events, $reconciler, self::getService('test.delete_payment_info'));
        $subscriber->setStatsd($statsD);

        $submission = new FlowFormSubmission();
        $submission->reference = $reference;
        $submission->saveOrFail();

        $flow = new PaymentFlow();
        $flow->identifier = $reference;
        $flow->status = PaymentFlowStatus::CollectPaymentDetails;
        $flow->currency = 'usd';
        $flow->amount = 100;
        $flow->initiated_from = PaymentFlowSource::Api;
        $flow->saveOrFail();

        $data = [
            'additionalData' => [
                'recurring.recurringDetailReference' => 'test_gateway_id',
                'shopperReference' => 'N59LAP36h0yqlIo41B4i54IE',
            ],
            'merchantReference' => 'missing',
            'paymentMethod' => 'amex',
            'success' => 'false',
        ];

        $statsD->shouldReceive('increment')
            ->with('adyen_tokenization_subscriber.success_false')
            ->once();
        $event = new AdyenTokenizationWebhookEvent($data);
        $subscriber->process($event);

        $data['success'] = 'true';
        $statsD->shouldReceive('increment')
            ->with('adyen_tokenization_subscriber.no_submission')
            ->once();
        $event = new AdyenTokenizationWebhookEvent($data);
        $subscriber->process($event);

        $data['merchantReference'] = $reference; // Set the correct reference for the submission
        $event = new AdyenTokenizationWebhookEvent($data);
        $subscriber->process($event);

        $statsD->shouldReceive('increment')
            ->with('adyen_tokenization_subscriber.no_customer_or_merchant')
            ->once();
        $submission->data = http_build_query(['make_default' => 1]);
        $submission->saveOrFail();
        $subscriber->process($event);

        $flow->customer = self::$customer;
        $flow->merchant_account = self::$merchantAccount;
        $flow->saveOrFail();
        $statsD->shouldReceive('increment')
            ->with('reconciliation.source.succeeded')
            ->twice();
        $subscriber->process($event);

        self::$customer->refresh();
        /** @var Card[] $cards */
        $cards = Card::where('customer_id', self::$customer)->execute();
        $this->assertCount(1, $cards);
        $this->assertFalse(self::$customer->autopay);
        $card = $cards[0];
        $this->assertEquals($card->id, self::$customer->default_source_id);
        $this->assertEquals([
            'brand' => 'amex',
            'chargeable' => true,
            'created_at' => $card->created_at,
            'customer_id' => self::$customer->id,
            'exp_month' => 12,
            'exp_year' => 2025,
            'failure_reason' => null,
            'funding' => 'unknown',
            'gateway' => 'flywire_payments',
            'gateway_customer' => 'N59LAP36h0yqlIo41B4i54IE',
            'gateway_id' => 'test_gateway_id',
            'gateway_setup_intent' => null,
            'id' => $card->id,
            'issuing_country' => null,
            'last4' => '0000',
            'merchant_account' => self::$merchantAccount->id,
            'receipt_email' => null,
            'updated_at' => $card->updated_at,
            'object' => 'card',
        ], $card->toArray());

        $statsD->shouldReceive('increment')
            ->with('billing_portal.autopay_enrollment')
            ->once();
        $submission->data = http_build_query(['make_default' => 1, 'enroll_autopay' => 1]);
        $submission->saveOrFail();

        $flow->funding = 'credit';
        $flow->last4 = '1234';
        $flow->expMonth = 11;
        $flow->expYear = 2099;
        $flow->country = 'NL';
        $flow->saveOrFail();

        $events->shouldReceive('track')->once();
        $data['additionalData']['recurring.recurringDetailReference'] = 'autopay_gateway_id';
        $event = new AdyenTokenizationWebhookEvent($data);
        $subscriber->process($event);

        self::$customer->refresh();
        /** @var Card[] $cards */
        $cards = Card::where('customer_id', self::$customer)
            ->sort('id ASC')
            ->execute();
        $this->assertCount(2, $cards);
        $this->assertTrue(self::$customer->autopay);
        $card = $cards[1];
        $this->assertEquals($card->id, self::$customer->default_source_id);
        $this->assertEquals([
            'brand' => 'amex',
            'chargeable' => true,
            'created_at' => $card->created_at,
            'customer_id' => self::$customer->id,
            'exp_month' => 11,
            'exp_year' => 2099,
            'failure_reason' => null,
            'funding' => 'credit',
            'gateway' => 'flywire_payments',
            'gateway_customer' => 'N59LAP36h0yqlIo41B4i54IE',
            'gateway_id' => 'autopay_gateway_id',
            'gateway_setup_intent' => null,
            'id' => $card->id,
            'issuing_country' => 'NL',
            'last4' => '1234',
            'merchant_account' => self::$merchantAccount->id,
            'receipt_email' => null,
            'updated_at' => $card->updated_at,
            'object' => 'card',
        ], $card->toArray());
    }
}
