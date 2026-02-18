<?php

namespace App\Tests\CashApplication\Libs;

use App\AccountsReceivable\Models\Customer;
use App\AccountsReceivable\Models\Invoice;
use App\CashApplication\Libs\CashApplicationMatchmaker;
use App\CashApplication\Models\Payment;
use App\Tests\AppTestCase;

class CashApplicationMatchmakerTest extends AppTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        self::hasCompany();
        self::hasCustomer();
        self::hasInvoice();
        self::hasPayment();
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        self::$company->delete();
    }

    private function getMatchmaker(): CashApplicationMatchmaker
    {
        return self::getService('test.cash_application_matchmaker');
    }

    public function testShouldLookForMatches(): void
    {
        $matchmaker = $this->getMatchmaker();
        $payment = new Payment();
        $payment->applied = true;
        $this->assertFalse($matchmaker->shouldLookForMatches($payment));

        $payment = new Payment();
        $payment->voided = true;
        $this->assertFalse($matchmaker->shouldLookForMatches($payment));

        $payment = new Payment();
        $payment->customer = -1;
        $this->assertFalse($matchmaker->shouldLookForMatches($payment));

        $payment = new Payment();
        $payment->matched = true;
        $this->assertFalse($matchmaker->shouldLookForMatches($payment));

        $payment = new Payment();
        $this->assertTrue($matchmaker->shouldLookForMatches($payment));
    }

    public function testSimpleMatch(): void
    {
        self::$payment->amount = 100;
        self::$payment->save();

        $matchmaker = $this->getMatchmaker();
        $matchmaker->run(self::$payment);

        /** @var array $match */
        $match = self::getService('test.database')->createQueryBuilder()
            ->select('`invoice_id`, `payment_id`, `group_id`, `primary`, `certainty`, `short_pay`')
            ->from('InvoiceUnappliedPaymentAssociations')
            ->andWhere('`invoice_id` = '.self::$invoice->id())
            ->andWhere('`payment_id` = '.self::$payment->id())
            ->fetchAssociative();

        $this->assertEquals(1, $match['primary']);
        $this->assertEquals(100, $match['certainty']);
        $this->assertEquals(0, $match['short_pay']);
    }

    public function testShortPayMatch(): void
    {
        self::$payment->amount = 90;
        self::$payment->save();

        $matchmaker = $this->getMatchmaker();
        $matchmaker->run(self::$payment);

        /** @var array $match */
        $match = self::getService('test.database')->createQueryBuilder()
            ->select('`invoice_id`, `payment_id`, `group_id`, `primary`, `certainty`, `short_pay`')
            ->from('InvoiceUnappliedPaymentAssociations')
            ->andWhere('`invoice_id` = '.self::$invoice->id())
            ->andWhere('`payment_id` = '.self::$payment->id())
            ->fetchAssociative();

        $this->assertEquals(1, $match['primary']);
        $this->assertEquals(100, $match['certainty']);
        $this->assertEquals(1, $match['short_pay']);
    }

    public function testNoMatch(): void
    {
        self::$payment->amount = 89;
        self::$payment->save();

        $matchmaker = $this->getMatchmaker();
        $matchmaker->run(self::$payment);

        $match = self::getService('test.database')->createQueryBuilder()
            ->select('`invoice_id`, `payment_id`, `group_id`, `primary`, `certainty`, `short_pay`')
            ->from('InvoiceUnappliedPaymentAssociations')
            ->andWhere('`invoice_id` = '.self::$invoice->id())
            ->andWhere('`payment_id` = '.self::$payment->id())
            ->fetchAssociative();

        $this->assertFalse($match);
    }

    public function testGroupMatch(): void
    {
        $invoice2 = new Invoice();
        $invoice2->setCustomer(self::$customer);
        $invoice2->items = [
            [
                'name' => 'Test Item',
                'description' => 'test',
                'quantity' => 1,
                'unit_cost' => 100,
            ],
        ];
        $invoice2->saveOrFail();

        $matchmaker = $this->getMatchmaker();
        $matchmaker->run(self::$payment);

        $matches = self::getService('test.database')->createQueryBuilder()
            ->select('`invoice_id`, `payment_id`, `group_id`, `primary`, `certainty`, `short_pay`')
            ->from('InvoiceUnappliedPaymentAssociations')
            ->andWhere('`payment_id` = '.self::$payment->id())
            ->fetchAllAssociative();

        $this->assertCount(2, $matches);
        $groupId = $matches[0]['group_id'];

        foreach ($matches as $match) {
            $this->assertEquals(1, $match['primary']);
            $this->assertEquals(100, $match['certainty']);
            $this->assertEquals(0, $match['short_pay']);
            $this->assertEquals($groupId, $match['group_id']);
        }
    }

    public function testMultipleSimpleMatches(): void
    {
        $customer2 = new Customer();
        $customer2->name = 'Sherlock';
        $customer2->email = 'sherlock@example.com';
        $customer2->address1 = 'Test';
        $customer2->address2 = 'Address';
        $customer2->saveOrFail();

        $invoice2 = new Invoice();
        $invoice2->setCustomer($customer2);
        $invoice2->items = [
            [
                'name' => 'Test Item',
                'description' => 'test',
                'quantity' => 1,
                'unit_cost' => 100,
            ],
        ];
        $invoice2->saveOrFail();

        self::$payment->amount = 100;
        self::$payment->save();

        $matchmaker = $this->getMatchmaker();
        $matchmaker->run(self::$payment);

        $matches = self::getService('test.database')->createQueryBuilder()
            ->select('`invoice_id`, `payment_id`, `group_id`, `primary`, `certainty`, `short_pay`')
            ->from('InvoiceUnappliedPaymentAssociations')
            ->andWhere('`payment_id` = '.self::$payment->id())
            ->fetchAllAssociative();

        $this->assertCount(2, $matches);
        $groupId1 = $matches[0]['group_id'];
        $groupId2 = $matches[1]['group_id'];

        $this->assertNotEquals($groupId1, $groupId2);
        $this->assertEquals(self::$invoice->id(), $matches[0]['invoice_id']);
        $this->assertEquals($invoice2->id(), $matches[1]['invoice_id']);

        $this->assertEquals(1, $matches[0]['primary']);
        $this->assertEquals(0, $matches[1]['primary']);

        foreach ($matches as $match) {
            $this->assertEquals(50, $match['certainty']);
            $this->assertEquals(0, $match['short_pay']);
        }
    }

    public function testMultipleGroupMatches(): void
    {
        $customer2 = new Customer();
        $customer2->name = 'Sherlock';
        $customer2->email = 'sherlock@example.com';
        $customer2->address1 = 'Test';
        $customer2->address2 = 'Address';
        $customer2->saveOrFail();

        $invoice2 = new Invoice();
        $invoice2->setCustomer(self::$customer);
        $invoice2->items = [
            [
                'name' => 'Test Item',
                'description' => 'test',
                'quantity' => 1,
                'unit_cost' => 100,
            ],
        ];
        $invoice2->saveOrFail();

        $invoice3 = new Invoice();
        $invoice3->setCustomer($customer2);
        $invoice3->items = [
            [
                'name' => 'Test Item',
                'description' => 'test',
                'quantity' => 1,
                'unit_cost' => 100,
            ],
        ];
        $invoice3->saveOrFail();

        $invoice4 = new Invoice();
        $invoice4->setCustomer($customer2);
        $invoice4->items = [
            [
                'name' => 'Test Item',
                'description' => 'test',
                'quantity' => 1,
                'unit_cost' => 100,
            ],
        ];
        $invoice4->saveOrFail();

        $matchmaker = $this->getMatchmaker();
        $matchmaker->run(self::$payment);

        $matches = self::getService('test.database')->createQueryBuilder()
            ->select('`invoice_id`, `payment_id`, `group_id`, `primary`, `certainty`, `short_pay`')
            ->from('InvoiceUnappliedPaymentAssociations')
            ->andWhere('`payment_id` = '.self::$payment->id())
            ->fetchAllAssociative();

        $this->assertCount(4, $matches);
        $groupId1 = $matches[0]['group_id'];
        $groupId2 = $matches[2]['group_id'];

        $this->assertNotEquals($groupId1, $groupId2);
        $this->assertEquals($groupId1, $matches[1]['group_id']);
        $this->assertEquals($groupId2, $matches[3]['group_id']);

        $this->assertEquals(self::$invoice->id(), $matches[0]['invoice_id']);
        $this->assertEquals($invoice2->id(), $matches[1]['invoice_id']);
        $this->assertEquals($invoice3->id(), $matches[2]['invoice_id']);
        $this->assertEquals($invoice4->id(), $matches[3]['invoice_id']);

        $this->assertEquals(1, $matches[0]['primary']);
        $this->assertEquals(1, $matches[1]['primary']);
        $this->assertEquals(0, $matches[2]['primary']);
        $this->assertEquals(0, $matches[3]['primary']);

        foreach ($matches as $match) {
            $this->assertEquals(50, $match['certainty']);
            $this->assertEquals(0, $match['short_pay']);
        }
    }
}
