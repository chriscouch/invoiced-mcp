<?php

namespace App\Tests\Sending\Email\Adapter;

use App\Core\Mailer\EmailBlockList;
use App\Core\Utils\DebugContext;
use App\Sending\Email\Adapter\SmtpAdapter;
use App\Sending\Email\Exceptions\SendEmailException;
use App\Sending\Email\Models\SmtpAccount;
use Aws\CloudWatchLogs\CloudWatchLogsClient;
use Mockery;
use Symfony\Component\Mailer\Exception\TransportException;
use Symfony\Component\Mailer\MailerInterface;

class SmtpAdapterTest extends AbstractAdapterTest
{
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        self::hasSmtpAccount();
    }

    public function testError(): void
    {
        $this->expectException(SendEmailException::class);

        $mailer = Mockery::mock(MailerInterface::class);
        $mailer->shouldReceive('send')->andThrow(new TransportException('test'));
        $adapter = $this->getAdapter();
        $adapter->setMailer($mailer);
        $email = $this->getEmail(false);
        $adapter->send($email);
    }

    protected function getAdapter(bool $withMailer = true): SmtpAdapter
    {
        $account = new SmtpAccount([
            'host' => 'host',
            'username' => 'username',
            'password' => 'password',
            'port' => 1234,
            'encryption' => 'tls',
            'auth_mode' => 'login',
        ]);

        $adapter = new SmtpAdapter($account, Mockery::mock(CloudWatchLogsClient::class), new DebugContext('test'), new EmailBlockList(self::getService('test.database')), false);
        $adapter->setLogger(self::$logger);

        $mailer = Mockery::mock(MailerInterface::class);
        $mailer->shouldReceive('send');
        $adapter->setMailer($mailer);

        return $adapter;
    }
}
