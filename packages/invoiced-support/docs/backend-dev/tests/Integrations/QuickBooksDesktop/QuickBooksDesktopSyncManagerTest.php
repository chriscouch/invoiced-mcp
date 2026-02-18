<?php

namespace App\Tests\Integrations\QuickBooksDesktop;

use App\Core\Files\Libs\S3FileCreator;
use App\Core\Files\Models\File;

use App\Core\Statsd\StatsdClient;
use App\Core\Utils\DebugContext;
use App\Integrations\AccountingSync\Exceptions\SyncAuthException;
use App\Integrations\AccountingSync\Exceptions\SyncException;
use App\Integrations\AccountingSync\ValueObjects\SyncJob;
use App\Integrations\QuickBooksDesktop\QuickBooksDesktopSyncManager;
use App\Tests\AppTestCase;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use Mockery;
use stdClass;

class QuickBooksDesktopSyncManagerTest extends AppTestCase
{
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        self::hasCompany();
    }

    public function testGetStopEndpoint(): void
    {
        $manager = $this->getManager();
        $this->assertEquals('http://example.com/syncs/'.self::$company->id.'/123', $manager->getStopEndpoint(self::$company, '123'));
    }

    public function testGetSyncedRecordsEndpoint(): void
    {
        $manager = $this->getManager();
        $this->assertEquals('http://example.com/synced_records/'.self::$company->id.'/123', $manager->getSyncedRecordsEndpoint(self::$company, '123'));
    }

    public function testGetSkippedRecordsEndpoint(): void
    {
        $manager = $this->getManager();
        $this->assertEquals('http://example.com/skipped_records', $manager->getSkippedRecordsEndpoint());
    }

    public function testGetConnectQuickBooksDesktopEndpoint(): void
    {
        $manager = $this->getManager();
        $this->assertEquals('http://example.com/integrations/qwc/file', $manager->getConnectQuickBooksDesktopEndpoint());
    }

    public function testBuildJob(): void
    {
        $manager = $this->getManager();

        // build a job from the sync server
        $input = [
            'state' => 'pending',
            'active' => true,
            'id' => 1234,
            'type' => 'QBDINVOICE',
            'position' => 0,
            'total_count' => 0,
            'progress' => 0,
            'failed_count' => 0,
            'created_at' => 1467317767,
            'updated_at' => 1467317767,
        ];

        $result = $manager->buildJob($input);

        $this->assertInstanceOf(SyncJob::class, $result);
        $expected = [
            'id' => 1234,
            'state' => 'pending',
            'active' => true,
            'type' => 'QBDINVOICE',
            'integration' => [
                'id' => 'quickbooks_desktop',
                'name' => 'QuickBooks Desktop',
            ],
            'position' => 0,
            'total_count' => 0,
            'failed_count' => 0,
            'progress' => 0,
            'created_at' => 1467317767,
            'updated_at' => 1467317767,
        ];
        $this->assertEquals($expected, $result->toArray());
    }

    public function testBuildJobUnknownIntegration(): void
    {
        $manager = $this->getManager();

        // build a job from the sync server
        $input = [
            'state' => 'pending',
            'active' => true,
            'id' => 1234,
            'type' => 'RANDOM',
            'position' => 0,
            'total_count' => 0,
            'progress' => 0,
            'failed_count' => 0,
            'created_at' => 1467317767,
            'updated_at' => 1467317767,
        ];

        $result = $manager->buildJob($input);

        $this->assertInstanceOf(SyncJob::class, $result);
        $expected = [
            'id' => 1234,
            'state' => 'pending',
            'active' => true,
            'type' => 'RANDOM',
            'integration' => [
                'id' => 'quickbooks_desktop',
                'name' => 'QuickBooks Desktop',
            ],
            'position' => 0,
            'total_count' => 0,
            'failed_count' => 0,
            'progress' => 0,
            'created_at' => 1467317767,
            'updated_at' => 1467317767,
        ];
        $this->assertEquals($expected, $result->toArray());
    }

    public function testGetJobs(): void
    {
        $sync = new stdClass();
        $sync->id = 122;
        $sync->type = 'RANDOM';
        $sync->state = 'failed';
        $sync->position = 0;
        $sync->total_count = 0;
        $sync->progress = 0;
        $sync->failed_count = 0;
        $sync->created_at = '2016-06-24T13:43:13-05:00';
        $sync->updated_at = 1467317767;
        $sync1 = new stdClass();
        $sync1->id = 123;
        $sync1->type = 'QBDINVOICE';
        $sync1->state = 'finished';
        $sync1->position = 100;
        $sync1->total_count = 100;
        $sync1->progress = 1;
        $sync1->failed_count = 0;
        $sync1->created_at = 1467317656;
        $sync1->updated_at = 1467317767;
        $sync2 = new stdClass();
        $sync2->id = 123;
        $sync2->type = 'QBDINVOICE';
        $sync2->state = 'finished';
        $sync2->position = 100;
        $sync2->total_count = 100;
        $sync2->progress = 1;
        $sync2->failed_count = 0;
        $sync2->created_at = 1467317656;
        $sync2->updated_at = 1467317767;

        $syncs = [$sync, $sync1, $sync2];
        $mock = new MockHandler([
            new Response(200, [], (string) json_encode(['syncs' => $syncs])),
        ]);

        $handler = HandlerStack::create($mock);
        $client = new Client([
            'handler' => $handler,
            'headers' => ['User-Agent' => 'Invoiced/1.0'],
        ]);

        $result = $this->getManager()->getJobs(self::$company, $client);

        $this->assertCount(3, $result);

        $this->assertInstanceOf(SyncJob::class, $result[1]);
        $expected = [
            'id' => 123,
            'state' => 'finished',
            'type' => 'QBDINVOICE',
            'integration' => [
                'id' => 'quickbooks_desktop',
                'name' => 'QuickBooks Desktop',
            ],
            'position' => 100,
            'total_count' => 100,
            'failed_count' => 0,
            'progress' => 1,
            'created_at' => 1467317656,
            'updated_at' => 1467317767,
        ];
        $this->assertEquals($expected, $result[1]->toArray());

        $this->assertInstanceOf(SyncJob::class, $result[2]);
        $expected = [
            'id' => 123,
            'state' => 'finished',
            'type' => 'QBDINVOICE',
            'integration' => [
                'id' => 'quickbooks_desktop',
                'name' => 'QuickBooks Desktop',
            ],
            'position' => 100,
            'total_count' => 100,
            'failed_count' => 0,
            'progress' => 1,
            'created_at' => 1467317656,
            'updated_at' => 1467317767,
        ];
        $this->assertEquals($expected, $result[2]->toArray());
    }

    public function testEnableQuickBooksDesktop(): void
    {
        $creator = Mockery::mock(S3FileCreator::class);
        $file = new File();
        $file->url = 'https://invoiced-qwc.s3.us-east-2.amazonaws.com/test';
        $creator->shouldReceive('create')->andReturn($file);

        $manager = $this->getManager($creator);

        $result = [
            'username' => 'aba3',
            'password' => 'srw1111',
            'file' => [
                'name' => 'invoice.qwc',
                'payload' => '<?xml version="1.0" encoding="UTF-8"?><QBWCXML><AppName>Payment Sync</AppName><AppID>INVDPAYMENT</AppID><AppURL>https://invoiced.com/integrations/quickbookswebconnector/paymentsync</AppURL><AppDescription>Syncs payments from Invoiced to QB</AppDescription><AppSupport>https://invoiced.com</AppSupport><UserName>T2ZGPi5uqCNyutmBVBAN65htzoNUzairUAL2Ng-kNHM=</UserName><OwnerID>JQO0ZzIdsFeKmUXeSMphtrrQQ8IPUbh9z0uA4hpIDNc=</OwnerID><FileID>F2Se8_UxrMfXcJd0_O_TyFjIMk0nMDR6aLI6i-Re1oE=</FileID><QBType>QBFS</QBType></QBWCXML>',
            ],
        ];
        $mock = new MockHandler([
            new Response(200, [], (string) json_encode($result)),
        ]);

        $handler = HandlerStack::create($mock);
        $client = new Client([
            'handler' => $handler,
            'headers' => ['User-Agent' => 'Invoiced/1.0'],
        ]);

        $result = $manager->enableQuickBooksEnterprise(self::$company, $client);

        $expected = [
            'username' => 'aba3',
            'password' => 'srw1111',
            'file' => 'https://invoiced-qwc.s3.us-east-2.amazonaws.com/test',
        ];
        $this->assertEquals($expected, $result);
    }

    public function testStop(): void
    {
        $job = new stdClass();
        $job->state = 'canceled';
        $job->id = 1234;
        $job->type = 'QBDINVOICE';
        $job->position = 0;
        $job->total_count = 0;
        $job->progress = 0;
        $job->failed_count = 0;
        $job->created_at = 1467317767;
        $job->updated_at = 1467317767;
        $mock = new MockHandler([
            new Response(200, [], (string) json_encode($job)),
        ]);

        $handler = HandlerStack::create($mock);
        $client = new Client([
            'handler' => $handler,
            'headers' => ['User-Agent' => 'Invoiced/1.0'],
        ]);

        $result = $this->getManager()
            ->stopJob(self::$company, 'QBDINVOICE-ALL-3694-1234', $client);

        $this->assertInstanceOf(SyncJob::class, $result);
        $expected = [
            'id' => 1234,
            'state' => 'canceled',
            'type' => 'QBDINVOICE',
            'integration' => [
                'id' => 'quickbooks_desktop',
                'name' => 'QuickBooks Desktop',
            ],
            'position' => 0,
            'total_count' => 0,
            'failed_count' => 0,
            'progress' => 0,
            'created_at' => 1467317767,
            'updated_at' => 1467317767,
        ];
        $this->assertEquals($expected, $result->toArray());
    }

    public function testStopFail(): void
    {
        $this->expectException(SyncException::class);
        $this->expectExceptionMessage('An error occurred when stopping the sync: Fail!');

        $error = [
            'message' => 'Fail!',
        ];
        $mock = new MockHandler([
            new Response(400, [], (string) json_encode($error)),
        ]);

        $handler = HandlerStack::create($mock);
        $client = new Client([
            'handler' => $handler,
            'headers' => ['User-Agent' => 'Invoiced/1.0'],
        ]);

        $result = $this->getManager()
            ->stopJob(self::$company, 'QBDINVOICE-ALL-3694-1234', $client);
    }

    public function testStopAuthFail(): void
    {
        $this->expectException(SyncAuthException::class);
        $this->expectExceptionMessage('An error occurred when stopping the sync: Fail!');

        $error = [
            'message' => 'Fail!',
        ];
        $mock = new MockHandler([
            new Response(401, [], (string) json_encode($error)),
        ]);

        $handler = HandlerStack::create($mock);
        $client = new Client([
            'handler' => $handler,
            'headers' => ['User-Agent' => 'Invoiced/1.0'],
        ]);

        $result = $this->getManager()
            ->stopJob(self::$company, 'QBDINVOICE-ALL-3694-1234', $client);
    }

    /**
     * @doesNotPerformAssertions
     */
    public function testSkipRecord(): void
    {
        $mock = new MockHandler([
            new Response(200, [], (string) json_encode(['success' => true])),
        ]);

        $handler = HandlerStack::create($mock);
        $client = new Client([
            'handler' => $handler,
            'headers' => ['User-Agent' => 'Invoiced/1.0'],
        ]);

        $sync = $this->getManager();
        $sync->skipRecord(self::$company, 'QBOINVOICE', 'invoice', '1234', $client);
    }

    protected function getManager(?S3FileCreator $creator = null): QuickBooksDesktopSyncManager
    {
        if (!$creator) {
            $creator = Mockery::mock(S3FileCreator::class);
        }
        $manager = new QuickBooksDesktopSyncManager(
            $creator,
            'test-bucket',
            Mockery::mock(DebugContext::class),
            'http://example.com',
            'user',
            'pass'
        );

        $manager->setStatsd(new StatsdClient());

        return $manager;
    }
}
