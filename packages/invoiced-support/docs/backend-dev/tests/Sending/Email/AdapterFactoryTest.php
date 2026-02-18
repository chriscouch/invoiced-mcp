<?php

namespace App\Tests\Sending\Email;

use App\Core\Mailer\EmailBlockList;
use App\Core\Statsd\StatsdClient;
use App\Core\Utils\DebugContext;
use App\Sending\Email\Adapter\AwsAdapter;
use App\Sending\Email\Adapter\FailoverAdapter;
use App\Sending\Email\Adapter\InvoicedInboxAdapter;
use App\Sending\Email\Adapter\NullAdapter;
use App\Sending\Email\Adapter\SmtpAdapter;
use App\Sending\Email\Exceptions\SendEmailException;
use App\Sending\Email\Interfaces\EmailInterface;
use App\Sending\Email\Libs\AdapterFactory;
use App\Sending\Email\ValueObjects\NamedAddress;
use App\Tests\AppTestCase;
use Aws\CloudWatchLogs\CloudWatchLogsClient;
use Mockery;
use Symfony\Component\RateLimiter\Storage\CacheStorage;

class AdapterFactoryTest extends AppTestCase
{
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        self::hasCompany();
        self::hasInbox();
    }

    private function getFactory(string $environment = 'production'): AdapterFactory
    {
        $storage = new CacheStorage(self::getService('test.cache'));
        $factory = new AdapterFactory(
            $environment,
            'invoicedmail.com',
            Mockery::mock(AwsAdapter::class),
            Mockery::mock(InvoicedInboxAdapter::class),
            Mockery::mock(CloudWatchLogsClient::class),
            new DebugContext('test'),
            '["smtp://apikey:password@smtp.sendgrid.net:587"]',
            $storage,
            self::getService('test.lock_factory'),
            new EmailBlockList(self::getService('test.database'))
        );
        $factory->setLogger(self::$logger);
        $factory->setStatsd(new StatsdClient());

        return $factory;
    }

    private function makeEmail(string $to = 'test@example.com'): EmailInterface
    {
        $email = Mockery::mock(EmailInterface::class);
        $email->shouldReceive('getTo')
            ->andReturn([new NamedAddress($to)]);
        $email->shouldReceive('getCc')->andReturn([]);
        $email->shouldReceive('getBcc')->andReturn([]);
        $email->shouldReceive('getCompany')
            ->andReturn(self::$company);

        return $email;
    }

    public function testAdapterDev(): void
    {
        $factory = $this->getFactory('dev');
        $email = $this->makeEmail();
        $adapter = $factory->get($email);
        $this->assertInstanceOf(SmtpAdapter::class, $adapter);
    }

    public function testAdapterInvoicedInbox(): void
    {
        $factory = $this->getFactory();
        $email = $this->makeEmail(self::$inbox->external_id.'@test.invoicedmail.com');
        $adapter = $factory->get($email);
        $this->assertInstanceOf(InvoicedInboxAdapter::class, $adapter);
    }

    public function testAdapterInvoiced(): void
    {
        $factory = $this->getFactory();
        $email = $this->makeEmail();
        $adapter = $factory->get($email);
        $this->assertInstanceOf(FailoverAdapter::class, $adapter);
        $adapters = $adapter->getAdapters();
        $this->assertCount(2, $adapters);
        $this->assertInstanceOf(AwsAdapter::class, $adapters[0]);
        $this->assertInstanceOf(SmtpAdapter::class, $adapters[1]);
    }

    public function testAdapterNull(): void
    {
        self::$company->accounts_receivable_settings->email_provider = 'null';
        self::$company->accounts_receivable_settings->saveOrFail();

        $factory = $this->getFactory();
        $email = $this->makeEmail();
        $adapter = $factory->get($email);
        $this->assertInstanceOf(NullAdapter::class, $adapter);
    }

    public function testAdapterSmtp(): void
    {
        self::hasSmtpAccount();
        self::$company->accounts_receivable_settings->email_provider = 'smtp';
        self::$company->accounts_receivable_settings->saveOrFail();

        $factory = $this->getFactory();
        $email = $this->makeEmail();
        $adapter = $factory->get($email);
        $this->assertInstanceOf(FailoverAdapter::class, $adapter);

        self::$smtpAccount->fallback_on_failure = false;
        self::$smtpAccount->save();

        $email = $this->makeEmail();
        $adapter = $factory->get($email);
        $this->assertInstanceOf(SmtpAdapter::class, $adapter);
    }

    public function testAdapterSendingDisabled(): void
    {
        $this->expectException(SendEmailException::class);

        self::$company->features->disable('email_sending');

        $factory = $this->getFactory();
        $email = $this->makeEmail();
        $factory->get($email);
    }
}
