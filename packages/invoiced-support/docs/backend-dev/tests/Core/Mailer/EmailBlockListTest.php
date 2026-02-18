<?php

namespace App\Tests\Core\Mailer;

use App\Core\Mailer\EmailBlockList;
use App\Sending\Email\Exceptions\EmailLimitException;
use App\Sending\Email\ValueObjects\Email;
use App\Sending\Email\ValueObjects\NamedAddress;
use App\Tests\AppTestCase;
use Doctrine\DBAL\Connection;

class EmailBlockListTest extends AppTestCase
{
    public function testCheckForBlockedAddress(): void
    {
        /** @var Connection $connection */
        $connection = self::getService('test.database');
        $blockList = new EmailBlockList($connection);

        $connection->executeStatement("REPLACE INTO BlockListEmailAddresses
            (reason, email, complaint_count) VALUES 
            (2, 'valid1@email.blocklist.test.com', 1),
            (1, 'invalid1@email.blocklist.test.com', 1),
            (2, 'invalid2@email.blocklist.test.com', 4)
        ");

        $email = new Email();
        $email->to([
            new NamedAddress('invalid1@email.blocklist.test.com'),
        ]);
        $email->cc([
            new NamedAddress('invalid1@email.blocklist.test.com'),
        ]);
        $email->cc([
            new NamedAddress('invalid2@email.blocklist.test.com'),
        ]);

        try {
            $blockList->checkForBlockedAddress($email);
            $this->fail('Expected exception');
        } catch (EmailLimitException $e) {
            $this->assertEquals('Sending emails to invalid1@email.blocklist.test.com, invalid2@email.blocklist.test.com is blocked', $e->getMessage());
        }

        $email->to([
            new NamedAddress('invalid1@email.blocklist.test.com'),
            new NamedAddress('invalid2@email.blocklist.test.com'),
        ]);
        $email->cc([
            new NamedAddress('invalid1@email.blocklist.test.com'),
            new NamedAddress('valid1@email.blocklist.test.com'),
            new NamedAddress('valid2@email.blocklist.test.com'),
        ]);
        $email->bcc([
            new NamedAddress('valid1@email.blocklist.test.com'),
            new NamedAddress('valid2@email.blocklist.test.com'),
        ]);

        $blockList->checkForBlockedAddress($email);

        $this->assertEquals([], $email->getTo());
        $this->assertEquals([
            new NamedAddress('valid1@email.blocklist.test.com'),
            new NamedAddress('valid2@email.blocklist.test.com'),
        ], $email->getCc());
        $this->assertEquals([
            new NamedAddress('valid1@email.blocklist.test.com'),
            new NamedAddress('valid2@email.blocklist.test.com'),
        ], $email->getBcc());
    }
}
