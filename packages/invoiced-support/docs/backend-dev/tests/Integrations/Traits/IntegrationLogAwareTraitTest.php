<?php

namespace App\Tests\Integrations\Traits;

use App\Integrations\Libs\CloudWatchHandler;
use App\Tests\AppTestCase;
use App\Integrations\Traits\IntegrationLogAwareTrait;
use Aws\CloudWatchLogs\CloudWatchLogsClient;
use Mockery;
use Monolog\Handler\NullHandler;

class IntegrationLogAwareTraitTest extends AppTestCase
{
    use IntegrationLogAwareTrait;

    const API_KEY = '__api_key';

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        self::hasCompany();
    }

    public function testGetCloudWatchHandler(): void
    {
        self::$company->features->enable('log_test');
        $handler = $this->getCloudWatchHandler('test', Mockery::mock(CloudWatchLogsClient::class), self::$company);
        $this->assertInstanceOf(CloudWatchHandler::class, $handler);

        self::$company->features->disable('log_test'); // Disable feature for next assertions.

        $this->assertFalse(self::$company->features->has('log_test'));
        $handler = $this->getCloudWatchHandler('test', Mockery::mock(CloudWatchLogsClient::class), self::$company);
        $this->assertInstanceOf(NullHandler::class, $handler);
    }
}
