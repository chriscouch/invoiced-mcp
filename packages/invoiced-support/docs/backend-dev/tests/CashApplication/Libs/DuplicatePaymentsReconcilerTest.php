<?php

namespace App\Tests\CashApplication\Libs;

use App\CashApplication\Libs\DuplicatePaymentsReconciler;
use App\CashApplication\Models\Payment;
use App\Tests\AppTestCase;

class DuplicatePaymentsReconcilerTest extends AppTestCase
{
    private DuplicatePaymentsReconciler $reconciler;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        self::hasCompany();
        self::hasCustomer();
        self::hasPayment();

        self::$payment->source = Payment::SOURCE_REMITTANCE_ADVICE;
        self::$payment->date = strtotime('-1 day');
        self::$payment->saveOrFail();
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->reconciler = new DuplicatePaymentsReconciler();
    }

    public function testDetectDuplicatePayment(): void
    {
        $duplicatePayment = new Payment();
        $duplicatePayment->amount = 200;
        $duplicatePayment->currency = 'usd';

        /** @var Payment $detected */
        $detected = $this->reconciler->detectDuplicatePayment($duplicatePayment);

        $this->assertEquals(self::$payment->toArray(), $detected->toArray());
    }

    public function testDetectDuplicatePaymentMultipleMatches(): void
    {
        $secondMatch = new Payment();
        $secondMatch->amount = 200;
        $secondMatch->currency = 'usd';
        $secondMatch->source = Payment::SOURCE_REMITTANCE_ADVICE;
        $secondMatch->saveOrFail();

        $duplicatePayment = new Payment();
        $duplicatePayment->amount = 200;
        $duplicatePayment->currency = 'usd';

        /** @var Payment $detected */
        $detected = $this->reconciler->detectDuplicatePayment($duplicatePayment);

        $this->assertEquals($secondMatch->toArray(), $detected->toArray());
    }

    public function testDetectDuplicatePaymentWithCustomer(): void
    {
        self::$payment->setCustomer(self::$customer);
        self::$payment->saveOrFail();

        $secondMatch = new Payment();
        $secondMatch->amount = 200;
        $secondMatch->currency = 'usd';
        $secondMatch->source = Payment::SOURCE_REMITTANCE_ADVICE;
        $secondMatch->saveOrFail();

        $duplicatePayment = new Payment();
        $duplicatePayment->amount = 200;
        $duplicatePayment->currency = 'usd';
        $duplicatePayment->setCustomer(self::$customer);

        /** @var Payment $detected */
        $detected = $this->reconciler->detectDuplicatePayment($duplicatePayment);

        $this->assertEquals(self::$payment->toArray(), $detected->toArray());
    }

    public function testMergeDuplicatePayments(): void
    {
        self::$payment->setCustomer(self::$customer);
        self::$payment->metadata = (object) [
            'test1' => 1,
            'test2' => 2,
        ];
        self::$payment->saveOrFail();

        $newPayment = new Payment();
        $newPayment->amount = 200;
        $newPayment->currency = 'usd';
        $newPayment->reference = '1234';
        $newPayment->notes = 'test';
        $newPayment->metadata = (object) [
            'test1' => 11,
            'test3' => 3,
        ];

        $this->reconciler->mergeDuplicatePayments(self::$payment, $newPayment->toArray());

        $mergedPayment = Payment::find(self::$payment->id());

        if (null === $mergedPayment) {
            throw new \Exception('Payment should be returned');
        }
        $this->assertEquals('1234', $mergedPayment->reference);
        $this->assertEquals('test', $mergedPayment->notes);
        $this->assertEquals(self::$customer->id(), $mergedPayment->customer);
        $this->assertEquals(self::$payment->date, $mergedPayment->date);
        $this->assertEquals([
            'test1' => 11,
            'test2' => 2,
            'test3' => 3,
        ], (array) $mergedPayment->toArray()['metadata']);
    }
}
