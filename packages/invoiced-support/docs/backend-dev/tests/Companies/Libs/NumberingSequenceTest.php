<?php

namespace App\Tests\Companies\Libs;

use App\AccountsReceivable\Models\Customer;
use App\Companies\Exception\NumberingException;
use App\Companies\Libs\NumberingSequence;
use App\Companies\Models\AutoNumberSequence;
use App\Core\Utils\Enums\ObjectType;
use App\Tests\AppTestCase;

class NumberingSequenceTest extends AppTestCase
{
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        self::hasCompany();
    }

    private function getSequence(ObjectType $type): NumberingSequence
    {
        return new NumberingSequence(self::$company, $type, self::getService('test.lock_factory'), self::getService('test.database'));
    }

    public function testApplyTemplate(): void
    {
        $this->assertEquals('CUST-00002', $this->getSequence(ObjectType::Customer)->applyTemplate(2));
        $this->assertEquals('INV-01234', $this->getSequence(ObjectType::Invoice)->applyTemplate(1234));
        $this->assertEquals('EST-00456', $this->getSequence(ObjectType::Estimate)->applyTemplate(456));
        $this->assertEquals('CN-00005', $this->getSequence(ObjectType::CreditNote)->applyTemplate(5));
    }

    public function testGetModel(): void
    {
        $sequence = $this->getSequence(ObjectType::Customer);
        $model = $sequence->getModel();
        $this->assertInstanceOf(AutoNumberSequence::class, $model);
        $this->assertEquals('customer', $model->type);
    }

    public function testReserveAndRelease(): void
    {
        // because of autoreleasing we need to maintain the sequence objects created
        // where each object represents a separate request
        $sequence1 = $this->getSequence(ObjectType::Invoice);
        $this->assertTrue($sequence1->reserve('INV-00001'));

        $sequence2 = $this->getSequence(ObjectType::Invoice);
        $this->assertFalse($sequence2->reserve('INV-00001'));
        $this->assertTrue($sequence2->reserve('INV-00002'));

        $sequence1->release('INV-00001');
        $this->assertFalse($sequence1->reserve('INV-00002'));
        $this->assertTrue($sequence2->reserve('INV-00001'));
    }

    public function testNextPreview(): void
    {
        $sequence = $this->getSequence(ObjectType::Customer);
        $this->assertEquals(1, $sequence->nextNumber());
        $this->assertEquals('CUST-00001', $sequence->nextNumberFormatted());

        for ($i = 0; $i < 10; ++$i) {
            $this->assertEquals(1, $sequence->nextNumber());
            $this->assertEquals('CUST-00001', $sequence->nextNumberFormatted(), 'Subsequent calls to next() should not change the answer until a customer has been created');
        }
    }

    public function testNextAfterCreate(): void
    {
        $sequence = $this->getSequence(ObjectType::Customer);
        $sequence->setNext(100);
        $this->assertEquals(100, $sequence->nextNumber());
        $this->assertEquals('CUST-00100', $sequence->nextNumberFormatted());

        // creating a customer should increment sequence, both on the
        // underlying model and in next()
        self::hasCustomer();
        $this->assertEquals(101, $sequence->getModel()->next, 'Should update the persisted `next` value');
        $this->assertEquals(101, $sequence->nextNumber());
        $this->assertEquals('CUST-00101', $sequence->nextNumberFormatted());
    }

    public function testNextReservation(): void
    {
        // because of autoreleasing we need to maintain the sequence objects created
        // where each object represents a separate request
        $sequences = [];

        $sequence = $this->getSequence(ObjectType::Customer);
        $sequences[] = $sequence;
        $sequence->setNext(1);
        $this->assertEquals('CUST-00001', $sequence->nextNumberFormatted(true));

        for ($i = 2; $i < 10; ++$i) {
            $sequence = $this->getSequence(ObjectType::Customer);
            $sequences[] = $sequence;
            $this->assertEquals($i, $sequence->nextNumber(true));
        }
    }

    public function testIsUniqueAfterCreate(): void
    {
        $sequence = $this->getSequence(ObjectType::Customer);

        $customer = new Customer();
        $customer->name = 'Unique Customer';
        $customer->number = 'Im unique';
        $customer->country = 'US';
        $customer->saveOrFail();

        $this->assertFalse($sequence->isUnique('Im unique'));
    }

    public function testIsUniqueReservation(): void
    {
        // because of autoreleasing we need to maintain the sequence objects created
        // where each object represents a separate request
        $sequence1 = $this->getSequence(ObjectType::Customer);
        $this->assertTrue($sequence1->reserve('Im another unique'));

        $sequence2 = $this->getSequence(ObjectType::Customer);
        $this->assertFalse($sequence2->isUnique('Im another unique'));

        $sequence1->release('Im another unique');
        $this->assertTrue($sequence2->reserve('Im another unique'));
    }

    public function testNextExhaustedMaxIterations(): void
    {
        $this->expectException(NumberingException::class);

        $sequence = $this->getSequence(ObjectType::Customer);

        // create 100 consecutively numbered customers
        // skip ORM for performance
        for ($i = 0; $i < 100; ++$i) {
            self::getService('test.database')->insert('Customers', ['name' => 'Test', 'number' => $sequence->applyTemplate(1000 + $i), 'tenant_id' => self::$company->id(), 'client_id' => uniqid()]);
        }

        $sequence->setNext(1000);
        $sequence->nextNumber();
    }

    public function testNumberingResetAfterRollback(): void
    {
        $sequence = $this->getSequence(ObjectType::Customer);
        $sequence->setNext(2000);

        // start a transaction
        $database = self::getService('test.database');
        $database->beginTransaction();

        $customer = new Customer();
        $customer->name = 'Test';
        $customer->country = 'US';
        $customer->saveOrFail();
        $this->assertEquals(2001, $sequence->nextNumber());

        // roll back the transaction
        $database->rollBack();
        NumberingSequence::resetCache();

        $this->assertEquals(2000, $sequence->nextNumber());
    }
}
