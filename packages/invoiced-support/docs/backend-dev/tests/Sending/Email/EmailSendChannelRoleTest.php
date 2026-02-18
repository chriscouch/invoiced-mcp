<?php

namespace App\Tests\Sending\Email;

use App\AccountsReceivable\Models\Contact;
use App\AccountsReceivable\Models\ContactRole;
use App\AccountsReceivable\Models\ReceivableDocument;
use App\Sending\Email\EmailFactory\DocumentEmailFactory;
use App\Sending\Email\Libs\EmailSendChannel;
use App\Sending\Email\Libs\EmailSender;
use App\Sending\Models\ScheduledSend;
use App\Tests\AppTestCase;
use Closure;

class EmailSendChannelRoleTest extends AppTestCase
{
    private static ContactRole $contactRole;
    private static ContactRole $contactRole2;
    private static Contact $contact;
    private static Contact $contact2;
    private static Contact $contact3;
    private static Contact $contact4;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        self::hasCompany();
        self::hasCustomer();
        self::hasInvoice();

        self::$contactRole = new ContactRole();
        self::$contactRole->name = 'Test Role';
        self::$contactRole->saveOrFail();

        self::$contactRole2 = new ContactRole();
        self::$contactRole2->name = 'Test Role2';
        self::$contactRole2->saveOrFail();

        self::$contact = new Contact();
        self::$contact->customer = self::$customer;
        self::$contact->role = self::$contactRole2;
        self::$contact->email = 'test@test3.com';
        self::$contact->name = 'Another Role';
        self::$contact->saveOrFail();

        self::$contact2 = new Contact();
        self::$contact2->customer = self::$customer;
        self::$contact2->role = self::$contactRole;
        self::$contact2->email = 'test@test.com';
        self::$contact2->name = 'Contact Name';
        self::$contact2->saveOrFail();

        self::$contact3 = new Contact();
        self::$contact3->customer = self::$customer;
        self::$contact3->role = self::$contactRole;
        self::$contact3->email = 'test@test2.com';
        self::$contact3->name = 'Contact Name2';
        self::$contact3->saveOrFail();

        self::$contact4 = new Contact();
        self::$contact4->customer = self::$customer;
        self::$contact4->role = self::$contactRole;
        self::$contact4->name = 'Without Email';
        self::$contact4->saveOrFail();
    }

    private function getChannel(): EmailSendChannel
    {
        return new EmailSendChannel(\Mockery::mock(DocumentEmailFactory::class), \Mockery::mock(EmailSender::class), self::getService('test.transaction_manager'));
    }

    /**
     * @dataProvider getRoles
     */
    public function testBuildToRoleScheduledSend(Closure $input, array $expected): void
    {
        $channel = $this->getChannel();
        $send = new ScheduledSend();
        $send->invoice = self::$invoice;
        $send->channel = ScheduledSend::EMAIL_CHANNEL;
        $send->parameters = $input(self::$contactRole);
        $send->saveOrFail();

        // should build 'to' value from ScheduledSend 'to' value
        /** @var ReceivableDocument $document */
        $document = $send->getDocument();
        $this->assertEquals($expected, array_values($channel->buildTo($send->getParameters(), $document, null)));
    }

    public function getRoles(): array
    {
        $toDefault = [
            'name' => 'John Doe',
            'email' => 'john@example.com',
        ];

        return [
            'Both role and to' => [
                fn (ContactRole $role) => [
                    'to' => [
                        $toDefault,
                    ],
                    'role' => $role->id(),
                ],
                [
                    [
                        'name' => 'Contact Name',
                        'email' => 'test@test.com',
                    ],
                    [
                        'name' => 'Contact Name2',
                        'email' => 'test@test2.com',
                    ],
                ],
            ],
            'Role null and to' => [
                fn (ContactRole $role) => [
                    'to' => [
                        $toDefault,
                    ],
                    'role' => null,
                ],
                [
                    $toDefault,
                ],
            ],
            'Role not existing and to' => [
                fn (ContactRole $role) => [
                    'to' => [
                        $toDefault,
                    ],
                    'role' => -1,
                ],
                [
                ],
            ],
            'Only to' => [
                fn (ContactRole $role) => [
                    'to' => [
                        $toDefault,
                    ],
                ],
                [
                    $toDefault,
                ],
            ],
            'None' => [
                fn (ContactRole $role) => [],
                [
                    [
                        'name' => 'Another Role',
                        'email' => 'test@test3.com',
                    ],
                    [
                        'name' => 'Contact Name',
                        'email' => 'test@test.com',
                    ],
                    [
                        'name' => 'Contact Name2',
                        'email' => 'test@test2.com',
                    ],
                    [
                        'name' => 'Sherlock',
                        'email' => 'sherlock@example.com',
                    ],
                ],
            ],
        ];
    }
}
