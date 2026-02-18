<?php

namespace App\Tests\Network;

use App\AccountsPayable\Models\Vendor;
use App\AccountsReceivable\Models\Customer;
use App\Companies\Models\Company;
use App\Network\Command\AcceptNetworkInvitation;
use App\Network\Models\NetworkConnection;
use App\Network\Models\NetworkInvitation;
use App\Tests\AppTestCase;
use App\Core\Utils\InfuseUtility as Utility;

class AcceptNetworkInvitationTest extends AppTestCase
{
    private static Company $company2;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        self::$company2 = self::getTestDataFactory()->createCompany();
        self::hasCompany();
        self::hasCustomer();
    }

    public static function tearDownAfterClass(): void
    {
        parent::tearDownAfterClass();
        if (isset(self::$company2)) {
            self::$company2->delete();
        }
    }

    private function getCommand(): AcceptNetworkInvitation
    {
        return self::getService('test.accept_network_invitation');
    }

    public function testAcceptExistingCustomer(): void
    {
        $invitation = new NetworkInvitation();
        $invitation->uuid = Utility::guid();
        $invitation->from_company = self::$company;
        $invitation->to_company = self::$company2;
        $invitation->is_customer = true;
        $invitation->customer = self::$customer;
        $invitation->declined = true; // this can be true or false
        $invitation->saveOrFail();

        self::getService('test.tenant')->runAs(self::$company2, function () use ($invitation) {
            $this->getCommand()->accept($invitation);
        });

        $this->assertFalse($invitation->persisted());

        // should create a connection
        /** @var NetworkConnection $connection */
        $connection = NetworkConnection::where('vendor_id', self::$company)
            ->where('customer_id', self::$company2)
            ->oneOrNull();
        $this->assertInstanceOf(NetworkConnection::class, $connection);

        // should assign the connection to a customer on the vendor's Invoiced account
        $this->assertEquals($connection->id, Customer::findOrFail(self::$customer->id)->network_connection?->id);

        // should create a vendor on the customer's Invoiced account
        $vendor = Vendor::queryWithTenant(self::$company2)
            ->where('network_connection_id', $connection)
            ->oneOrNull();
        $this->assertInstanceOf(Vendor::class, $vendor);
        $this->assertEquals(self::$company->name, $vendor->name);
    }

    public function testAcceptNewCustomer(): void
    {
        NetworkConnection::where('vendor_id', self::$company)->delete();

        $invitation = new NetworkInvitation();
        $invitation->uuid = Utility::guid();
        $invitation->from_company = self::$company;
        $invitation->to_company = self::$company2;
        $invitation->is_customer = true;
        $invitation->declined = false;
        $invitation->saveOrFail();

        self::getService('test.tenant')->runAs(self::$company2, function () use ($invitation) {
            $this->getCommand()->accept($invitation);
        });

        $this->assertFalse($invitation->persisted());

        // should create a connection
        /** @var NetworkConnection $connection */
        $connection = NetworkConnection::where('vendor_id', self::$company)
            ->where('customer_id', self::$company2)
            ->oneOrNull();
        $this->assertInstanceOf(NetworkConnection::class, $connection);

        // should create a new customer on the vendor's Invoiced account
        $customer = Customer::where('name', self::$company2->name)->oneOrNull();
        $this->assertInstanceOf(Customer::class, $customer);
        $this->assertEquals($connection->id, $customer->network_connection?->id);
        $this->assertEquals(self::$company2->email, $customer->email);

        // should create a vendor on the customer's Invoiced account
        $vendor = Vendor::queryWithTenant(self::$company2)
            ->where('network_connection_id', $connection)
            ->oneOrNull();
        $this->assertInstanceOf(Vendor::class, $vendor);
        $this->assertEquals(self::$company->name, $vendor->name);
    }

    public function testAcceptNewVendor(): void
    {
        NetworkConnection::where('vendor_id', self::$company)->delete();

        $invitation = new NetworkInvitation();
        $invitation->uuid = Utility::guid();
        $invitation->from_company = self::$company;
        $invitation->to_company = self::$company2;
        $invitation->is_customer = false;
        $invitation->declined = false;
        $invitation->saveOrFail();

        self::getService('test.tenant')->runAs(self::$company2, function () use ($invitation) {
            $this->getCommand()->accept($invitation);
        });

        $this->assertFalse($invitation->persisted());

        // should create a connection
        /** @var NetworkConnection $connection */
        $connection = NetworkConnection::where('vendor_id', self::$company2)
            ->where('customer_id', self::$company)
            ->oneOrNull();
        $this->assertInstanceOf(NetworkConnection::class, $connection);

        // should create a new customer on the vendor's Invoiced account
        $customer = Customer::queryWithTenant(self::$company2)
            ->where('network_connection_id', $connection)
            ->oneOrNull();
        $this->assertInstanceOf(Customer::class, $customer);
        $this->assertEquals(self::$company->name, $customer->name);
        $this->assertEquals(self::$company->email, $customer->email);

        // should create a vendor on the customer's Invoiced account
        $vendor = Vendor::queryWithTenant(self::$company)
            ->where('network_connection_id', $connection)
            ->oneOrNull();
        $this->assertInstanceOf(Vendor::class, $vendor);
        $this->assertEquals(self::$company2->name, $vendor->name);
    }
}
