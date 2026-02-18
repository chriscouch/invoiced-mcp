<?php

namespace App\Tests;

use App\Companies\Libs\DeleteCompany;
use App\Core\Search\Libs\SearchIndexListener;
use App\ActivityLog\Libs\EventSpool;
use App\Kernel;
use App\Metadata\Libs\AttributeStorageFacade;
use App\Metadata\Libs\LegacyStorageFacade;
use Carbon\CarbonImmutable;
use Mockery\Adapter\Phpunit\MockeryTestCase;
use Monolog\Handler\NullHandler;
use Monolog\Logger;
use Psr\Log\LoggerInterface;

abstract class AppTestCase extends MockeryTestCase
{
    use TestAssertionsTrait;
    use TestDataCaseTrait;

    protected static LoggerInterface $logger;
    protected static Kernel $kernel;
    public static bool $rebootKernel = false;
    private static TestDataFactory $testDataFactory;

    public function __construct(?string $name = null, array $data = [], $dataName = '')
    {
        parent::__construct($name, $data, $dataName);
        self::getKernel();
    }

    public static function getKernel(): Kernel
    {
        if (!isset(self::$kernel) || self::$rebootKernel) {
            self::$kernel = new Kernel('test', true);
            self::$kernel->boot();
            self::getTestDataFactory(self::$kernel);
            self::$rebootKernel = false;
        }

        return self::$kernel;
    }

    public static function getService(string $id): mixed
    {
        return self::getKernel()->getContainer()->get($id);
    }

    public static function getParameter(string $id): mixed
    {
        return self::getKernel()->getContainer()->getParameter($id);
    }

    protected static function getTestDataFactory(?Kernel $kernel = null): TestDataFactory
    {
        if (!isset(self::$testDataFactory) || self::$rebootKernel || $kernel) {
            $kernel ??= self::getKernel();
            self::$testDataFactory = new TestDataFactory($kernel->getContainer());
        }

        return self::$testDataFactory;
    }

    public static function setUpBeforeClass(): void
    {
        // disable recording events and indexing models in tests
        EventSpool::disable();
        SearchIndexListener::disable();

        if (!isset(self::$logger)) {
            self::$logger = new Logger('test', [new NullHandler()]);
        }
    }

    protected function setUp(): void
    {
        // disable recording events and indexing models in tests
        // if a test needs this behavior then it must be
        // explicitly turned on
        EventSpool::disable();
        SearchIndexListener::disable();
    }

    public static function tearDownAfterClass(): void
    {
        // clear the spools as any leftover events don't matter
        self::getService('test.event_spool')->clear();
        self::getService('test.email_spool')->clear();
        self::getService('test.notification_spool')->clear();
        self::getService('test.search')->clearIndexSpools();
        self::getService('test.accounting_write_spool')->clear();

        // delete the company - this should erase any related
        // models from the DB
        if (isset(self::$company) && self::$company->persisted()) {
            (new DeleteCompany(self::getService('test.database')))->delete(self::$company);
        }

        self::getService('test.tenant')->clear();

        // Reset the system clock, in case it was changed
        CarbonImmutable::setTestNow();
        self::getService(LegacyStorageFacade::class)::$instance = null;
        self::getService(AttributeStorageFacade::class)::$instance = null;
    }
}
