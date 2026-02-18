<?php

namespace App\Tests\PaymentProcessing\Reconciliation;

use App\Core\I18n\ValueObjects\Money;
use App\Core\Utils\Enums\ObjectType;
use App\ActivityLog\Enums\EventType;
use App\ActivityLog\Libs\EventSpool;
use App\ActivityLog\Models\Event;
use App\PaymentProcessing\Models\Charge;
use App\PaymentProcessing\Models\Refund;
use App\PaymentProcessing\Reconciliation\RefundReconciler;
use App\PaymentProcessing\ValueObjects\RefundValueObject;
use App\Tests\AppTestCase;

class RefundReconcilerTest extends AppTestCase
{
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        self::hasCompany();
        self::hasCustomer();
        self::hasCard();
    }

    public function testReconcileFullRefund(): void
    {
        EventSpool::enable();

        $amount = new Money('usd', 100);

        $charge = new Charge();
        $charge->customer = self::$customer;
        $charge->currency = 'usd';
        $charge->amount = 1;
        $charge->gateway = 'invoiced';
        $charge->gateway_id = 'ch_test'.microtime(true);
        $charge->status = 'succeeded';
        $charge->last_status_check = time();
        $charge->saveOrFail();

        $refund = new RefundValueObject(
            amount: $amount,
            timestamp: (int) mktime(0, 0, 0, 12, 2, 2016),
            gateway: 'invoiced',
            gatewayId: 're_1234',
            status: RefundValueObject::SUCCEEDED,
        );

        $reconciler = $this->getReconciler();

        /** @var Refund $refundModel */
        $refundModel = $reconciler->reconcile($refund, $charge);

        $this->assertInstanceOf(Refund::class, $refundModel);
        $expected = [
            'amount' => 1.0,
            'charge_id' => $charge->id,
            'created_at' => $refundModel->created_at,
            'currency' => 'usd',
            'failure_message' => null,
            'gateway' => 'invoiced',
            'gateway_id' => 're_1234',
            'id' => $refundModel->id(),
            'merchant_account_transaction_id' => null,
            'object' => 'refund',
            'status' => 'succeeded',
        ];
        $refundArray = $refundModel->toArray();
        unset($refundArray['updated_at']);
        $this->assertEquals($expected, $refundArray);

        $this->assertTrue($charge->refunded);
        $this->assertEquals(1, $charge->amount_refunded);

        // should create event
        self::getService('test.event_spool')->flush(); // write out events
        $this->assertEquals(1, Event::where('object_type_id', ObjectType::Refund->value)
            ->where('object_id', $refundModel)
            ->where('type_id', EventType::RefundCreated->toInteger())
            ->count());

        // reconciling the refund again should be blocked
        $this->assertNull($reconciler->reconcile($refund, $charge));
    }

    public function testReconcilePartialRefund(): void
    {
        $charge = new Charge();
        $charge->customer = self::$customer;
        $charge->currency = 'usd';
        $charge->amount = 1;
        $charge->gateway = 'invoiced';
        $charge->gateway_id = 'ch_test'.microtime(true);
        $charge->status = 'succeeded';
        $charge->last_status_check = time();
        $charge->saveOrFail();

        $refund = new RefundValueObject(
            amount: new Money('usd', 50),
            timestamp: (int) mktime(0, 0, 0, 12, 2, 2016),
            gateway: 'invoiced',
            gatewayId: 're_1235',
            status: RefundValueObject::SUCCEEDED,
        );

        $reconciler = $this->getReconciler();

        /** @var Refund $refundModel */
        $refundModel = $reconciler->reconcile($refund, $charge);

        $this->assertInstanceOf(Refund::class, $refundModel);
        $expected = [
            'amount' => 0.5,
            'charge_id' => $charge->id,
            'created_at' => $refundModel->created_at,
            'currency' => 'usd',
            'failure_message' => null,
            'gateway' => 'invoiced',
            'gateway_id' => 're_1235',
            'id' => $refundModel->id(),
            'merchant_account_transaction_id' => null,
            'object' => 'refund',
            'status' => 'succeeded',
        ];
        $refundArray = $refundModel->toArray();
        unset($refundArray['updated_at']);
        $this->assertEquals($expected, $refundArray);

        $this->assertFalse($charge->refunded);
        $this->assertEquals(.5, $charge->amount_refunded);

        // reconciling the refund again should be blocked
        $this->assertNull($reconciler->reconcile($refund, $charge));
    }

    private function getReconciler(): RefundReconciler
    {
        return self::getService('test.refund_reconciler');
    }
}
