<?php

namespace App\Tests\Integrations\AccountingSync;

use App\Integrations\AccountingSync\Exceptions\SyncException;
use App\Integrations\AccountingSync\Interfaces\AccountingReaderInterface;
use App\Integrations\AccountingSync\ReadSync\ReadSync;
use App\Integrations\AccountingSync\ValueObjects\ReadQuery;
use App\Integrations\Intacct\Models\IntacctAccount;
use App\Integrations\Intacct\Models\IntacctSyncProfile;
use App\Tests\AppTestCase;
use Carbon\CarbonImmutable;
use Mockery;

class ReadSyncTest extends AppTestCase
{
    private static IntacctSyncProfile $syncProfile;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        self::hasCompany();
        self::$syncProfile = new IntacctSyncProfile();
        self::$syncProfile->saveOrFail();
    }

    private function getSync(): ReadSync
    {
        return new ReadSync();
    }

    private function getAccount(): IntacctAccount
    {
        return new IntacctAccount();
    }

    private function getSyncProfile(): IntacctSyncProfile
    {
        return self::$syncProfile;
    }

    public function testSyncOngoingSuccess(): void
    {
        $sync = $this->getSync();
        $account = $this->getAccount();
        $syncProfile = $this->getSyncProfile();
        $syncProfile->read_cursor = 1;
        $syncProfile->last_synced = 1;
        $syncProfile->saveOrFail();

        $reader1 = Mockery::mock(AccountingReaderInterface::class);
        $reader1->shouldReceive('isEnabled')->andReturn(true)->once();
        $reader1->shouldReceive('syncAll')->once();
        $reader2 = Mockery::mock(AccountingReaderInterface::class);
        $reader2->shouldReceive('isEnabled')->andReturn(true)->once();
        $reader2->shouldReceive('syncAll')->once();
        $reader3 = Mockery::mock(AccountingReaderInterface::class);
        $reader3->shouldReceive('isEnabled')->andReturn(false)->once();
        $readers = [$reader1, $reader2, $reader3];

        $sync->syncOngoing($account, $syncProfile, $readers);

        $this->assertGreaterThan(1, $syncProfile->read_cursor);
        $this->assertGreaterThan(1, $syncProfile->last_synced);
    }

    public function testSyncOngoingFail(): void
    {
        $sync = $this->getSync();
        $account = $this->getAccount();
        $syncProfile = $this->getSyncProfile();
        $syncProfile->read_cursor = 1;
        $syncProfile->last_synced = 1;
        $syncProfile->saveOrFail();

        $reader1 = Mockery::mock(AccountingReaderInterface::class);
        $reader1->shouldReceive('isEnabled')->andReturn(true)->once();
        $reader1->shouldReceive('syncAll')->once();
        $reader2 = Mockery::mock(AccountingReaderInterface::class);
        $reader2->shouldReceive('isEnabled')->andReturn(true)->once();
        $reader2->shouldReceive('syncAll')->andThrow(SyncException::class);
        $reader3 = Mockery::mock(AccountingReaderInterface::class);
        $reader3->shouldReceive('isEnabled')->andReturn(true)->once();
        $reader3->shouldNotReceive('syncAll');
        $readers = [$reader1, $reader2, $reader3];

        $sync->syncOngoing($account, $syncProfile, $readers);

        // If any individual reader fails then the read cursor should not be updated
        $this->assertEquals(1, $syncProfile->read_cursor);
        $this->assertGreaterThan(1, $syncProfile->last_synced);
    }

    public function testSyncHistoricalSuccess(): void
    {
        $sync = $this->getSync();
        $account = $this->getAccount();
        $syncProfile = $this->getSyncProfile();
        $syncProfile->read_cursor = 1;
        $syncProfile->last_synced = 1;
        $syncProfile->saveOrFail();

        $query = new ReadQuery(
            startDate: new CarbonImmutable('2023-01-01'),
            endDate: new CarbonImmutable('2023-01-31'),
            openItemsOnly: true,
        );

        $reader1 = Mockery::mock(AccountingReaderInterface::class);
        $reader1->shouldReceive('syncAll')->once();
        $reader2 = Mockery::mock(AccountingReaderInterface::class);
        $reader2->shouldReceive('syncAll')->once();
        $reader3 = Mockery::mock(AccountingReaderInterface::class);
        $reader3->shouldReceive('syncAll')->once();
        $readers = [$reader1, $reader2, $reader3];

        $sync->syncHistorical($account, $syncProfile, $readers, $query);

        $this->assertEquals(1, $syncProfile->read_cursor);
        $this->assertGreaterThan(1, $syncProfile->last_synced);
    }

    public function testSyncHistoricalFail(): void
    {
        $sync = $this->getSync();
        $account = $this->getAccount();
        $syncProfile = $this->getSyncProfile();
        $syncProfile->read_cursor = 1;
        $syncProfile->last_synced = 1;
        $syncProfile->saveOrFail();

        $query = new ReadQuery(
            startDate: new CarbonImmutable('2023-01-01'),
            endDate: new CarbonImmutable('2023-01-31'),
            openItemsOnly: true,
        );

        $reader1 = Mockery::mock(AccountingReaderInterface::class);
        $reader1->shouldReceive('syncAll')->once();
        $reader2 = Mockery::mock(AccountingReaderInterface::class);
        $reader2->shouldReceive('syncAll')->andThrow(SyncException::class);
        $reader3 = Mockery::mock(AccountingReaderInterface::class);
        $reader3->shouldNotReceive('syncAll');
        $readers = [$reader1, $reader2, $reader3];

        $sync->syncHistorical($account, $syncProfile, $readers, $query);

        $this->assertEquals(1, $syncProfile->read_cursor);
        $this->assertGreaterThan(1, $syncProfile->last_synced);
    }
}
