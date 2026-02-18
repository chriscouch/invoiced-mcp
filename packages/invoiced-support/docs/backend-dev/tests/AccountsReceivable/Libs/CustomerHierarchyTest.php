<?php

namespace App\Tests\AccountsReceivable\Libs;

use App\AccountsReceivable\Libs\CustomerHierarchy;
use App\AccountsReceivable\Models\Customer;
use App\Tests\AppTestCase;

class CustomerHierarchyTest extends AppTestCase
{
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        self::hasCompany();
    }

    private function getHierarchy(): CustomerHierarchy
    {
        return new CustomerHierarchy(self::getService('test.database'));
    }

    public function testGetParentIds(): void
    {
        $customer = new Customer();
        $customer->name = 'Customer 1';
        $customer->saveOrFail();

        $customer2 = new Customer();
        $customer2->name = 'Customer 2';
        $customer2->setParentCustomer($customer);
        $customer2->saveOrFail();

        $customer3 = new Customer();
        $customer3->name = 'Customer 3';
        $customer3->setParentCustomer($customer2);
        $customer3->saveOrFail();

        $customer4 = new Customer();
        $customer4->name = 'Customer 4';
        $customer4->setParentCustomer($customer3);
        $customer4->saveOrFail();

        $customer5 = new Customer();
        $customer5->name = 'Customer 5';
        $customer5->setParentCustomer($customer4);
        $customer5->saveOrFail();

        $hierarchy = $this->getHierarchy();
        $this->assertEquals([], $hierarchy->getParentIds($customer));
        $this->assertEquals([$customer->id()], $hierarchy->getParentIds($customer2));
        $this->assertEquals([$customer->id(), $customer2->id()], $hierarchy->getParentIds($customer3));
        $this->assertEquals([$customer->id(), $customer2->id(), $customer3->id()], $hierarchy->getParentIds($customer4));
        $this->assertEquals([$customer->id(), $customer2->id(), $customer3->id(), $customer4->id()], $hierarchy->getParentIds($customer5));
    }

    public function testGetSubCustomerIds(): void
    {
        $customer = new Customer();
        $customer->name = 'Customer 1';
        $customer->saveOrFail();

        $customer2 = new Customer();
        $customer2->name = 'Customer 2';
        $customer2->setParentCustomer($customer);
        $customer2->saveOrFail();

        $customer3 = new Customer();
        $customer3->name = 'Customer 3';
        $customer3->setParentCustomer($customer2);
        $customer3->saveOrFail();

        $customer4 = new Customer();
        $customer4->name = 'Customer 4';
        $customer4->setParentCustomer($customer3);
        $customer4->saveOrFail();

        $customer5 = new Customer();
        $customer5->name = 'Customer 5';
        $customer5->setParentCustomer($customer4);
        $customer5->saveOrFail();

        $hierarchy = $this->getHierarchy();
        $this->assertEquals([$customer2->id(), $customer3->id(), $customer4->id(), $customer5->id()], $hierarchy->getSubCustomerIds($customer));
        $this->assertEquals([$customer3->id(), $customer4->id(), $customer5->id()], $hierarchy->getSubCustomerIds($customer2));
        $this->assertEquals([$customer4->id(), $customer5->id()], $hierarchy->getSubCustomerIds($customer3));
        $this->assertEquals([$customer5->id()], $hierarchy->getSubCustomerIds($customer4));
        $this->assertEquals([], $hierarchy->getSubCustomerIds($customer5));
    }

    public function testGetDepthFromRoot(): void
    {
        // TEST TREE STRUCTURE:
        //
        //               $customer
        //       $customer2    $customer3
        //    $customer4           $customer5
        //                            $customer6

        $customer = new Customer();
        $customer->name = 'Customer 1';
        $customer->saveOrFail();

        $customer2 = new Customer();
        $customer2->name = 'Customer 2';
        $customer2->setParentCustomer($customer);
        $customer2->saveOrFail();

        $customer3 = new Customer();
        $customer3->name = 'Customer 3';
        $customer3->setParentCustomer($customer);
        $customer3->saveOrFail();

        $customer4 = new Customer();
        $customer4->name = 'Customer 4';
        $customer4->setParentCustomer($customer2);
        $customer4->saveOrFail();

        $customer5 = new Customer();
        $customer5->name = 'Customer 5';
        $customer5->setParentCustomer($customer3);
        $customer5->saveOrFail();

        $customer6 = new Customer();
        $customer6->name = 'Customer 6';
        $customer6->setParentCustomer($customer5);
        $customer6->saveOrFail();

        $hierarchy = $this->getHierarchy();
        $this->assertEquals(1, $hierarchy->getDepthFromRoot($customer));
        $this->assertEquals(2, $hierarchy->getDepthFromRoot($customer2));
        $this->assertEquals(2, $hierarchy->getDepthFromRoot($customer3));
        $this->assertEquals(3, $hierarchy->getDepthFromRoot($customer4));
        $this->assertEquals(3, $hierarchy->getDepthFromRoot($customer5));
        $this->assertEquals(4, $hierarchy->getDepthFromRoot($customer6));
    }

    public function testGetMaxDepthFromCustomer(): void
    {
        // TEST TREE STRUCTURE:
        //
        //               $customer
        //       $customer2    $customer3
        //    $customer4           $customer5
        //                            $customer6

        $customer = new Customer();
        $customer->name = 'Customer 1';
        $customer->saveOrFail();

        $customer2 = new Customer();
        $customer2->name = 'Customer 2';
        $customer2->setParentCustomer($customer);
        $customer2->saveOrFail();

        $customer3 = new Customer();
        $customer3->name = 'Customer 3';
        $customer3->setParentCustomer($customer);
        $customer3->saveOrFail();

        $customer4 = new Customer();
        $customer4->name = 'Customer 4';
        $customer4->setParentCustomer($customer2);
        $customer4->saveOrFail();

        $customer5 = new Customer();
        $customer5->name = 'Customer 5';
        $customer5->setParentCustomer($customer3);
        $customer5->saveOrFail();

        $customer6 = new Customer();
        $customer6->name = 'Customer 6';
        $customer6->setParentCustomer($customer5);
        $customer6->saveOrFail();

        $hierarchy = $this->getHierarchy();
        $this->assertEquals(4, $hierarchy->getMaxDepthFromCustomer($customer));
        $this->assertEquals(2, $hierarchy->getMaxDepthFromCustomer($customer2));
        $this->assertEquals(3, $hierarchy->getMaxDepthFromCustomer($customer3));
        $this->assertEquals(1, $hierarchy->getMaxDepthFromCustomer($customer4));
        $this->assertEquals(2, $hierarchy->getMaxDepthFromCustomer($customer5));
        $this->assertEquals(1, $hierarchy->getMaxDepthFromCustomer($customer6));
    }
}
