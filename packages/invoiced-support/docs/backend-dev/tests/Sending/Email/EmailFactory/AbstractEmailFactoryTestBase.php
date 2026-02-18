<?php

namespace App\Tests\Sending\Email\EmailFactory;

use App\Sending\Email\EmailFactory\AbstractEmailFactory;
use App\Sending\Email\Exceptions\SendEmailException;
use App\Sending\Email\Models\EmailTemplate;
use App\Sending\Email\ValueObjects\NamedAddress;
use App\Tests\AppTestCase;

abstract class AbstractEmailFactoryTestBase extends AppTestCase
{
    abstract public function getFactory(): AbstractEmailFactory;

    public function testTo(): void
    {
        $factory = $this->getFactory();

        $to = [
            [
                'name' => 'Test',
                'email' => 'test@example.com',
            ],
            // duplicates should be eliminated
            [
                'name' => 'Test',
                'email' => 'test@example.com',
            ],
            [
                'name' => 'Test',
                'email' => 'test@example.com',
            ],
            [
                'name' => 'Test',
                'email' => 'test@example.com',
            ],
            [
                'name' => 'Someone Else',
                'email' => 'TEST@ExAmPlE.com',
            ],
            [
                'name' => 'Not Duplicate',
                'email' => 'test2@example.com',
            ],
        ];

        $expected = [
            new NamedAddress('test@example.com', 'Test'),
            new NamedAddress('test2@example.com', 'Not Duplicate'),
        ];
        $this->assertEquals($expected, $factory->generateTo($to, self::$company, self::$customer));
    }

    public function testToMax(): void
    {
        $factory = $this->getFactory();

        $to = [
            [
                'name' => 'Test',
                'email' => 'test@example.com',
            ],
            [
                'name' => 'Test',
                'email' => 'test2@example.com',
            ],
            [
                'name' => 'Test',
                'email' => 'test3@example.com',
            ],
            [
                'name' => 'Test',
                'email' => 'test4@example.com',
            ],
            [
                'name' => 'Test',
                'email' => 'test5@example.com',
            ],
        ];

        // should not throw any exception
        $to = $factory->generateTo($to, self::$company, self::$customer);

        $expected = [
            new NamedAddress('test@example.com', 'Test'),
            new NamedAddress('test2@example.com', 'Test'),
            new NamedAddress('test3@example.com', 'Test'),
            new NamedAddress('test4@example.com', 'Test'),
            new NamedAddress('test5@example.com', 'Test'),
        ];
        $this->assertEquals($expected, $to);
    }

    public function testToTooMany(): void
    {
        self::$company->features->disable('unlimited_recipients');

        $this->expectException(SendEmailException::class);
        $this->expectExceptionMessage('Cannot send to more than 5 recipients at a time.');

        $factory = $this->getFactory();

        $to = [
            [
                'name' => 'Test',
                'email' => 'test@example.com',
            ],
            [
                'name' => 'Test',
                'email' => 'test2@example.com',
            ],
            [
                'name' => 'Test',
                'email' => 'test3@example.com',
            ],
            [
                'name' => 'Test',
                'email' => 'test4@example.com',
            ],
            [
                'name' => 'Test',
                'email' => 'test5@example.com',
            ],
            [
                'name' => 'Test',
                'email' => 'test6@example.com',
            ],
        ];

        $factory->generateTo($to, self::$company, self::$customer);
    }

    public function testToNoLimit(): void
    {
        self::$company->features->enable('unlimited_recipients');

        $factory = $this->getFactory();

        $to = [
            [
                'name' => 'Test',
                'email' => 'test@example.com',
            ],
            [
                'name' => 'Test',
                'email' => 'test2@example.com',
            ],
            [
                'name' => 'Test',
                'email' => 'test3@example.com',
            ],
            [
                'name' => 'Test',
                'email' => 'test4@example.com',
            ],
            [
                'name' => 'Test',
                'email' => 'test5@example.com',
            ],
            [
                'name' => 'Test',
                'email' => 'test6@example.com',
            ],
        ];

        $addresses = $factory->generateTo($to, self::$company, self::$customer);

        $this->assertCount(6, $addresses);
    }

    public function testSetToInvalid(): void
    {
        $factory = $this->getFactory();

        $this->expectException(SendEmailException::class);
        $this->expectExceptionMessage('Invalid email address: blah@blah');

        $factory->generateTo([['name' => 'test', 'email' => 'blah@blah']], self::$company, self::$customer);
    }

    public function testToWithoutToName(): void
    {
        $template = new EmailTemplate();
        $template->id = 'new_invoice_email';

        $factory = $this->getFactory();

        $to = $factory->generateTo([
            ['email' => 'cust@example.com', 'name' => null],
        ], self::$company, self::$customer);
        $this->assertEquals([new NamedAddress('cust@example.com', 'Sherlock')], $to);

        $to = $factory->generateTo([
            ['email' => 'cust@example.com'],
        ], self::$company, self::$customer);
        $this->assertEquals([new NamedAddress('cust@example.com', 'Sherlock')], $to);

        $to = $factory->generateTo([
            ['email' => 'cust@example.com', 'name' => 'test'],
        ], self::$company, self::$customer);
        $this->assertEquals([new NamedAddress('cust@example.com', 'test')], $to);
    }
}
