<?php

namespace App\Tests\Imports;

use App\AccountsReceivable\Models\Customer;
use App\Core\NullFileProxy;
use App\Core\Queue\Queue;
use App\Core\S3ProxyFactory;
use App\Core\Utils\Enums\ObjectType;
use App\EntryPoint\QueueJob\ImportJob;
use App\ActivityLog\Enums\EventType;
use App\ActivityLog\Models\Event;
use App\Imports\Libs\ImportLock;
use App\Imports\Models\Import;
use App\Imports\Models\ImportedObject;
use App\Tests\AppTestCase;
use App\Tests\Sending\Sms\NullLock;
use Mockery;
use Sentry\State\HubInterface;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Lock\LockInterface;

class ImportJobTest extends AppTestCase
{
    private static Import $import;
    private static array $mapping;
    private static array $lines;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        self::hasCompany();

        self::$mapping = [
            'name',
            'number',
        ];

        self::$lines = [
            [
                'Test',
                '',
            ],
            [
                'Test',
            ],
            [
                'Test 2',
                'CUST-0002',
            ],
        ];
    }

    public function testCreate(): void
    {
        $s3 = Mockery::mock(NullFileProxy::class);
        $s3->shouldReceive('putObject');
        // mock S3 operations
        $s3Factory = Mockery::mock(S3ProxyFactory::class);
        $s3Factory->shouldReceive('build')
            ->andReturn($s3);

        // mock queueing operations
        $queue = Mockery::mock(Queue::class);
        $queue->shouldReceive('enqueue')->once();

        $job = $this->getJob($s3Factory, $queue, Mockery::mock(HubInterface::class), Mockery::mock(LockFactory::class));
        $import = $job->create('customer', self::$mapping, self::$lines);
        self::$import = $import;

        $this->assertInstanceOf(Import::class, $import);
        // the import job should be queued
        $this->assertGreaterThan(0, $import->id());
        $this->assertEquals(Import::PENDING, $import->status);
    }

    /**
     * @depends testCreate
     */
    public function testExecute(): void
    {
        $s3 = Mockery::mock(NullFileProxy::class);
        $s3->shouldReceive('putObject');
        // mock S3 operations
        $s3Factory = Mockery::mock(S3ProxyFactory::class);
        $s3Factory->shouldReceive('build')
            ->andReturn($s3);

        $s3->shouldReceive('getObject')
            ->andReturn(['Body' => json_encode(['mapping' => self::$mapping, 'lines' => self::$lines])]);

        $s3->shouldReceive('deleteObject')
            ->andReturn(['DeleteMarker' => true]);

        $hub = Mockery::mock(HubInterface::class);
        $hub->shouldReceive('configureScope');


        $lockFactory = Mockery::mock(LockFactory::class);
        $lockFactory->shouldReceive('createLock')
            ->andReturn(new NullLock());
        $job = $this->getJob($s3Factory, Mockery::mock(Queue::class), $hub, $lockFactory);

        $job->execute(self::$import);

        $this->assertEquals(Import::SUCCEEDED, self::$import->refresh()->status);
        $this->assertEquals(2, self::$import->num_imported);
        $this->assertEquals(0, self::$import->num_updated);
        $this->assertEquals(0, self::$import->num_failed);
        $this->assertEquals([], self::$import->failure_detail);
        $this->assertEquals(3, self::$import->total_records);
        $this->assertEquals(3, self::$import->position);

        // should create 2 customer
        $customer = Customer::where('name', 'Test')->oneOrNull();
        $this->assertInstanceOf(Customer::class, $customer);
        $this->assertEquals('US', $customer->country);

        $customer2 = Customer::where('name', 'Test 2')->oneOrNull();
        $this->assertInstanceOf(Customer::class, $customer2);
        $this->assertEquals('US', $customer2->country);

        // should create an event
        self::getService('test.event_spool')->flush(); // write out events
        $this->assertEquals(1, Event::where('type_id', EventType::ImportFinished->toInteger())->count());

        // should create ImportedObject instances
        $importedObjects = ImportedObject::where('import', self::$import->id())->all();
        $this->assertEquals(2, count($importedObjects));

        $this->assertInstanceOf(ImportedObject::class, $importedObjects[0]);
        $this->assertEquals(self::$import->id(), $importedObjects[0]->import);
        $this->assertEquals(ObjectType::Customer->value, $importedObjects[0]->object);
        $this->assertEquals($customer->id(), $importedObjects[0]->object_id);
        $this->assertEquals(self::$company->id(), $importedObjects[0]->tenant()->id());

        $this->assertInstanceOf(ImportedObject::class, $importedObjects[1]);
        $this->assertEquals(self::$import->id(), $importedObjects[1]->import);
        $this->assertEquals(ObjectType::Customer->value, $importedObjects[1]->object);
        $this->assertEquals($customer2->id(), $importedObjects[1]->object_id);
        $this->assertEquals(self::$company->id(), $importedObjects[1]->tenant()->id());
    }

    /**
     * @depends testExecute
     */
    public function testExecuteLocked(): void
    {
        self::$import->status = Import::PENDING;
        self::$import->saveOrFail();

        $lock = new ImportLock(self::getService('test.lock_factory'), self::$company, 'customer');
        $this->assertTrue($lock->acquire(10));
        $s3 = Mockery::mock(NullFileProxy::class);
        $s3->shouldReceive('putObject');
        // mock S3 operations
        $s3Factory = Mockery::mock(S3ProxyFactory::class);
        $s3Factory->shouldReceive('build')
            ->andReturn($s3);

        $lock = Mockery::mock(LockInterface::class);
        $lock->shouldReceive('acquire')->andReturn(false);
        $lock->shouldReceive('isAcquired')->andReturn(true);
        $lock->shouldReceive('release');

        $lockFactory = Mockery::mock(LockFactory::class);
        $lockFactory->shouldReceive('createLock')
            ->andReturn($lock);
        $hub = Mockery::mock(HubInterface::class);
        $hub->shouldReceive('configureScope');
        $this->getJob($s3Factory, Mockery::mock(Queue::class), $hub, $lockFactory)->execute(self::$import);

        $this->assertEquals(Import::FAILED, self::$import->status);
        $expected = [
            [
                'reason' => 'Another import of the same type is already running. Please wait for it to finish before starting another import.',
            ],
        ];
        $this->assertEquals($expected, self::$import->failure_detail);
    }

    /**
     * @depends testExecute
     */
    public function testRequeue(): void
    {
        $s3 = Mockery::mock(NullFileProxy::class);
        $s3->shouldReceive('putObject');
        // mock S3 operations
        $s3Factory = Mockery::mock(S3ProxyFactory::class);
        $s3Factory->shouldReceive('build')
            ->andReturn($s3);
        $s3->shouldReceive('putObject');

        // mock queueing operations
        $queue = Mockery::mock(Queue::class);
        $queue->shouldReceive('enqueue')->once();


        $lock = Mockery::mock(LockInterface::class);
        $lock->shouldReceive('acquire')->andReturn(false);
        $lockFactory = Mockery::mock(LockFactory::class);
        $lockFactory->shouldReceive('createLock')
            ->andReturn($lock);
        $job = $this->getJob($s3Factory, $queue, Mockery::mock(HubInterface::class), Mockery::mock(LockFactory::class));

        $import = $job->requeue(self::$import, self::$mapping, self::$lines);

        // the import job should be re-queued
        $this->assertEquals(self::$import->id(), $import->id());
        $this->assertEquals(Import::PENDING, $import->status);
    }

    private function getJob(S3ProxyFactory $proxy, Queue $queue, HubInterface $hub, LockFactory $lock): ImportJob
    {
        return new ImportJob(
            self::getService('test.importer_factory'),
            'test',
            self::getService('test.event_spool'),
            'https://localhost',
            $lock,
            $hub,
            $queue,
            self::getService('test.mailer'),
            $proxy
        );
    }
}
