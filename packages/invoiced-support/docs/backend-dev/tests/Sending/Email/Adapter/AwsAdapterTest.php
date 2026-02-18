<?php

namespace App\Tests\Sending\Email\Adapter;

use App\Core\Entitlements\Enums\QuotaType;
use App\Core\Mailer\EmailBlockList;
use App\Sending\Email\Adapter\AwsAdapter;
use App\Sending\Email\Exceptions\SendEmailException;
use Aws\Command;
use Aws\Ses\Exception\SesException;
use Aws\Ses\SesClient;
use Mockery;
use Monolog\Handler\NullHandler;
use Monolog\Logger;

class AwsAdapterTest extends AbstractAdapterTest
{
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        self::$company->quota->set(QuotaType::CustomerEmailDailyLimit, 10);
    }

    public function testError(): void
    {
        $this->expectException(SendEmailException::class);

        $ses = Mockery::mock(SesClient::class);
        $ses->shouldReceive('sendRawEmail')->andThrow(new SesException('test', new Command('autopay')));
        $adapter = $this->makeAdapter($ses);
        $email = $this->getEmail(false);
        $adapter->send($email);
    }

    protected function getAdapter(): AwsAdapter
    {
        $ses = Mockery::mock(SesClient::class);
        $ses->shouldReceive('getRegion')->andReturn('us-east-2');
        $ses->shouldReceive('sendRawEmail')->andReturn(['MessageId' => '1234']);

        return $this->makeAdapter($ses);
    }

    private function makeAdapter(SesClient $ses): AwsAdapter
    {
        $adapter = new AwsAdapter($ses, new EmailBlockList(self::getService('test.database')));
        $adapter->setLogger(new Logger('test', [new NullHandler()]));

        return $adapter;
    }
}
