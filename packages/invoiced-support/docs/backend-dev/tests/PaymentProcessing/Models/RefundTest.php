<?php

namespace App\Tests\PaymentProcessing\Models;

use App\Core\Orm\Exception\ListenerException;
use App\Core\Orm\Exception\ModelException;
use App\Core\Orm\Model;
use App\PaymentProcessing\Gateways\AdyenGateway;
use App\PaymentProcessing\Gateways\FlywireGateway;
use App\PaymentProcessing\Models\Charge;
use App\PaymentProcessing\Models\Refund;
use App\PaymentProcessing\ValueObjects\RefundValueObject;
use App\Tests\ModelTestCase;

/**
 * @extends ModelTestCase<Refund>
 */
class RefundTest extends ModelTestCase
{
    private static Charge $charge;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        self::hasCustomer();

        self::$charge = new Charge();
        self::$charge->customer = self::$customer;
        self::$charge->currency = 'usd';
        self::$charge->amount = 100;
        self::$charge->status = Charge::PENDING;
        self::$charge->gateway = FlywireGateway::ID;
        self::$charge->gateway_id = 'PTU146221637';
        self::$charge->last_status_check = 0;
        self::$charge->saveOrFail();
    }

    protected function getModelCreate(): Refund
    {
        $refund = new Refund();
        $refund->charge = self::$charge;
        $refund->amount = 100;
        $refund->currency = 'usd';
        $refund->status = 'succeeded';
        $refund->gateway = FlywireGateway::ID;
        $refund->gateway_id = 'RPTUE0D63641';

        return $refund;
    }

    protected function getExpectedToArray($model, array &$output): array
    {
        return [
            'amount' => 100.0,
            'charge_id' => self::$charge->id,
            'created_at' => $model->created_at,
            'currency' => 'usd',
            'failure_message' => null,
            'gateway' => 'flywire',
            'gateway_id' => 'RPTUE0D63641',
            'id' => $model->id,
            'merchant_account_transaction_id' => null,
            'object' => 'refund',
            'status' => 'succeeded',
            'updated_at' => $model->updated_at,
        ];
    }

    protected function getModelEdit($model): Model
    {
        $model->status = 'failed';

        return $model;
    }

    public function testRefundStatusValidationAllValidStatuses(): void
    {
        // Test all valid statuses are accepted
        $validStatuses = [
            RefundValueObject::SUCCEEDED,
            RefundValueObject::PENDING,
            RefundValueObject::FAILED,
            RefundValueObject::VOIDED,
        ];

        foreach ($validStatuses as $index => $status) {
            $charge = new Charge();
            $charge->customer = self::$customer;
            $charge->currency = 'usd';
            $charge->amount = 100;
            $charge->status = Charge::SUCCEEDED;
            $charge->gateway = AdyenGateway::ID;
            $charge->gateway_id = 'TEST_CHARGE_STATUS_' . $index;
            $charge->saveOrFail();

            $refund = new Refund();
            $refund->charge = $charge;
            $refund->currency = 'usd';
            $refund->amount = 50;
            $refund->status = $status;
            $refund->gateway = AdyenGateway::ID;
            $refund->gateway_id = 'TEST_REFUND_STATUS_' . $index;

            // This should not throw an exception
            $refund->saveOrFail();

            // Verify the status was saved correctly
            $this->assertEquals($status, $refund->status);
        }
    }

    public function testRefundStatusValidationRejectsInvalidStatus(): void
    {
        $charge = new Charge();
        $charge->customer = self::$customer;
        $charge->currency = 'usd';
        $charge->amount = 100;
        $charge->status = Charge::SUCCEEDED;
        $charge->gateway = AdyenGateway::ID;
        $charge->gateway_id = 'TEST_CHARGE_INVALID';
        $charge->saveOrFail();

        // Test invalid status
        $refund = new Refund();
        $refund->charge = $charge;
        $refund->currency = 'usd';
        $refund->amount = 50;
        $refund->status = 'invalid_status';
        $refund->gateway = AdyenGateway::ID;
        $refund->gateway_id = 'TEST_REFUND_INVALID';

        // This should throw a validation exception
        $this->expectException(ModelException::class);
        $refund->saveOrFail();
    }

    public function testRefundStatusTransitionToVoided(): void
    {
        $charge = new Charge();
        $charge->customer = self::$customer;
        $charge->currency = 'usd';
        $charge->amount = 100;
        $charge->status = Charge::SUCCEEDED;
        $charge->gateway = AdyenGateway::ID;
        $charge->gateway_id = 'TEST_CHARGE_TRANSITION';
        $charge->saveOrFail();

        // Create a succeeded refund
        $refund = new Refund();
        $refund->charge = $charge;
        $refund->currency = 'usd';
        $refund->amount = 50;
        $refund->status = RefundValueObject::SUCCEEDED;
        $refund->gateway = AdyenGateway::ID;
        $refund->gateway_id = 'TEST_REFUND_TRANSITION';
        $refund->saveOrFail();

        // Verify initial status
        $this->assertEquals(RefundValueObject::SUCCEEDED, $refund->status);

        // Transition to voided
        $refund->status = RefundValueObject::VOIDED;
        $refund->saveOrFail();

        // Verify the status was updated
        $this->assertEquals(RefundValueObject::VOIDED, $refund->status);

        // Verify persistence
        $refund->refresh();
        $this->assertEquals(RefundValueObject::VOIDED, $refund->status);
    }
}
