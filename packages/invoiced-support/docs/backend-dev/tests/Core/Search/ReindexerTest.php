<?php

namespace App\Tests\Core\Search;

use App\AccountsPayable\Models\Vendor;
use App\AccountsReceivable\Models\Contact;
use App\AccountsReceivable\Models\CreditNote;
use App\AccountsReceivable\Models\Customer;
use App\AccountsReceivable\Models\Estimate;
use App\AccountsReceivable\Models\Invoice;
use App\CashApplication\Models\Payment;
use App\Core\Search\Interfaces\DriverInterface;
use App\Core\Search\Interfaces\IndexInterface;
use App\Core\Search\Interfaces\ReindexingStrategyInterface;
use App\Core\Search\Libs\IndexRegistry;
use App\Core\Search\Libs\Reindexer;
use App\Core\Search\Libs\Search;
use App\Sending\Email\Models\EmailParticipant;
use App\SubscriptionBilling\Models\Subscription;
use App\Tests\AppTestCase;
use Mockery;

class ReindexerTest extends AppTestCase
{
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        self::hasCompany();
        EmailParticipant::query()->delete();
    }

    public function testGetIndexableObjectsForCompany(): void
    {
        $registry = new IndexRegistry();

        $expected = [
            EmailParticipant::class,
            Contact::class,
            CreditNote::class,
            Customer::class,
            Invoice::class,
            Payment::class,
            Subscription::class,
            Estimate::class,
            Vendor::class,
        ];
        $this->assertEquals($expected, $registry->getIndexableObjectsForCompany(self::$company));

        self::$company->features->enable('subscriptions');

        $expected = [
            EmailParticipant::class,
            Contact::class,
            CreditNote::class,
            Customer::class,
            Invoice::class,
            Payment::class,
            Subscription::class,
            Estimate::class,
            Vendor::class,
        ];
        $this->assertEquals($expected, $registry->getIndexableObjectsForCompany(self::$company));

        self::$company->features->enable('estimates');

        $expected = [
            EmailParticipant::class,
            Contact::class,
            CreditNote::class,
            Customer::class,
            Invoice::class,
            Payment::class,
            Subscription::class,
            Estimate::class,
            Vendor::class,
        ];
        $this->assertEquals($expected, $registry->getIndexableObjectsForCompany(self::$company));
    }

    public function testGetIndexableObjects(): void
    {
        $registry = new IndexRegistry();
        $expected = [
            EmailParticipant::class,
            Contact::class,
            CreditNote::class,
            Customer::class,
            Invoice::class,
            Payment::class,
            Subscription::class,
            Estimate::class,
            Vendor::class,
        ];
        $this->assertEquals($expected, $registry->getIndexableObjects());
    }

    public function testRun(): void
    {
        $index = Mockery::mock(IndexInterface::class);
        $index->shouldReceive('getName')
            ->andReturn('index_name');
        $index->shouldReceive('exists')
            ->andReturn(true)
            ->times(9);

        $driver = Mockery::mock(DriverInterface::class);
        $driver->shouldReceive('getIndex')
            ->andReturn($index);
        $driver->shouldReceive('createIndex')
            ->andReturn($index)
            ->times(9);
        $reindexer = $this->getReindexer($driver);

        $strategy = Mockery::mock(ReindexingStrategyInterface::class);
        $strategy->shouldReceive('run')
            ->times(9);

        $reindexer->run(self::$company, $strategy);

        $this->assertGreaterThan(0, self::$company->search_last_reindexed);
    }

    private function getReindexer(DriverInterface $driver): Reindexer
    {
        $search = Mockery::mock(Search::class);
        $search->shouldReceive('getDriver')
            ->andReturn($driver);

        return new Reindexer($search, new IndexRegistry(), self::getService('test.tenant'));
    }
}
