<?php

namespace App\Tests\CustomerPortal;

use App\AccountsReceivable\Libs\CustomerHierarchy;
use App\AccountsReceivable\Models\Contact;
use App\AccountsReceivable\Models\Customer;
use App\Core\Authentication\Models\User;
use App\CustomerPortal\Libs\CustomerPortal;
use App\CustomerPortal\Libs\CustomerPortalSecurityChecker;
use App\Tests\AppTestCase;
use Mockery;

class CustomerPortalSecurityCheckerTest extends AppTestCase
{
    private static CustomerPortal $portal;
    private static User $user;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        self::hasCompany();
        self::hasCustomer();

        self::$portal = new CustomerPortal(self::$company, new CustomerHierarchy(self::getService('test.database')));
        self::getService('test.customer_portal_context')->set(self::$portal);

        self::$user = self::getService('test.user_context')->get();
        self::getService('test.user_context')->set(new User(['id' => -3]));
    }

    public static function tearDownAfterClass(): void
    {
        self::getService('test.user_context')->set(self::$user);
        parent::tearDownAfterClass();
    }

    private function getChecker(): CustomerPortalSecurityChecker
    {
        return self::getService('test.customer_portal_security_checker');
    }

    public function testCanAccessCustomerAsSignedInCustomer(): void
    {
        $checker = $this->getChecker();

        $this->assertFalse($checker->canAccessCustomer(self::$customer));

        self::$portal->setSignedInCustomer(self::$customer);
        $this->assertTrue($checker->canAccessCustomer(self::$customer));
    }

    public function testCanAccessCustomerAsInvoicedUser(): void
    {
        $checker = $this->getChecker();

        self::$portal->setSignedInCustomer(null);
        $this->assertFalse($checker->canAccessCustomer(self::$customer));

        self::getService('test.user_context')->set(self::$user);
        $this->assertTrue($checker->canAccessCustomer(self::$customer));
    }

    public function testGetCustomersForEmail(): void
    {
        $checker = $this->getChecker();
        $hierarchy = new CustomerHierarchy(self::getService('test.database'));
        self::$user->setIsFullySignedIn(false);
        $email = self::$user->email;

        $h = Mockery::mock(CustomerHierarchy::class);
        $portal = new CustomerPortal(self::$company, $h);

        $portal->setSignedInEmail('notfound');
        $this->assertEquals([], $checker->getCustomersForEmail($portal, $hierarchy));

        $portal->setSignedInEmail(self::$customer->email);
        $customers = $checker->getCustomersForEmail($portal, $hierarchy);
        $this->assertCount(1, $customers);
        $this->assertInstanceOf(Customer::class, $customers[0]);
        $this->assertEquals(self::$customer->id, $customers[0]->id());

        $user = self::getService('test.user_context')->get();
        $user->setIsFullySignedIn();
        $this->assertEquals([], $checker->getCustomersForEmail($portal, $hierarchy));

        $user->email = self::$customer->email;
        $customers = $checker->getCustomersForEmail($portal, $hierarchy);
        $this->assertCount(1, $customers);
        $this->assertInstanceOf(Customer::class, $customers[0]);
        $this->assertEquals(self::$customer->id, $customers[0]->id());

        $customer = new Customer();
        $customer->name = 'Sherlock2';
        $customer->saveOrFail();

        $contact = new Contact();
        $contact->customer = $customer;
        $contact->name = 'Test';
        $contact->email = self::$customer->email;
        $this->assertTrue($contact->save());

        $customers = $checker->getCustomersForEmail($portal, $hierarchy);
        $this->assertCount(2, $customers);
        $this->assertInstanceOf(Customer::class, $customers[1]);
        $this->assertEquals($customer->id, $customers[1]->id());

        $customer2 = new Customer();
        $customer2->name = 'Sherlock4';
        $customer2->parent_customer = $customer->id;
        $customer2->saveOrFail();

        $customers = $checker->getCustomersForEmail($portal, $hierarchy);
        $this->assertCount(3, $customers);
        $this->assertInstanceOf(Customer::class, $customers[2]);
        $this->assertEquals($customer2->id, $customers[2]->id());

        $user->email = $email;
    }
}
