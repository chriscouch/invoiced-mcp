<?php

namespace App\Tests\Network;

use App\Companies\Models\Company;
use App\Network\Command\DeleteNetworkConnection;
use App\Network\Models\NetworkConnection;
use App\Tests\AppTestCase;

class DeleteNetworkConnectionTest extends AppTestCase
{
    private static Company $company2;
    private static NetworkConnection $connection;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        self::$company2 = self::getTestDataFactory()->createCompany();
        self::hasCompany();
        self::hasVendor();
        self::$connection = self::getTestDataFactory()->connectCompanies(self::$company2, self::$company);
        self::$vendor->network_connection = self::$connection;
        self::$vendor->saveOrFail();
    }

    public static function tearDownAfterClass(): void
    {
        parent::tearDownAfterClass();
        if (isset(self::$company2)) {
            self::$company2->delete();
        }
    }

    private function getCommand(): DeleteNetworkConnection
    {
        return self::getService('test.delete_network_connection');
    }

    public function testRemove(): void
    {
        $command = $this->getCommand();
        $command->remove(self::$connection);

        $this->assertFalse(self::$connection->persisted());
        $this->assertFalse(self::$vendor->refresh()->active);
        $this->assertNull(self::$vendor->network_connection);
    }
}
