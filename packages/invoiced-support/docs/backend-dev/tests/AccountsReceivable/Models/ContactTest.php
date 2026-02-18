<?php

namespace App\Tests\AccountsReceivable\Models;

use App\AccountsReceivable\Models\Contact;
use App\Core\Search\Libs\SearchDocumentFactory;
use App\Core\Utils\ModelNormalizer;
use App\ActivityLog\Enums\EventType;
use App\ActivityLog\Libs\EventSpool;
use App\Sending\Email\Models\EmailParticipant;
use App\Tests\AppTestCase;

class ContactTest extends AppTestCase
{
    private static Contact $contact;
    private static Contact $contact2;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        self::hasCompany();
        self::hasCustomer();
    }

    public function testEventAssociations(): void
    {
        $contact = new Contact();
        $contact->customer_id = 1234;

        $this->assertEquals([
            ['customer', 1234],
        ], $contact->getEventAssociations());
    }

    public function testEventObject(): void
    {
        $contact = new Contact();
        $contact->customer = self::$customer;

        $this->assertEquals(array_merge($contact->toArray(), [
            'customer' => ModelNormalizer::toArray(self::$customer),
            'role' => null,
        ]), $contact->getEventObject());
    }

    public function testCreateMissingCustomer(): void
    {
        $contact = new Contact();
        $contact->name = 'Test';
        $this->assertFalse($contact->save());
    }

    public function testAddress(): void
    {
        $contact = new Contact();
        $contact->tenant_id = (int) self::$company->id();
        $contact->name = 'Sherlock Holmes';
        $contact->address1 = '221B Baker St';
        $contact->address2 = 'Unit 1';
        $contact->city = 'London';
        $contact->state = 'England';
        $contact->postal_code = '1234';
        $contact->country = 'GB';
        $this->assertEquals('221B Baker St
Unit 1
London
1234
United Kingdom', $contact->address);
    }

    public function testCreate(): void
    {
        EventSpool::enable();

        self::$contact = new Contact();
        $this->assertTrue(self::$contact->create([
            'customer' => self::$customer,
            'name' => 'Sherlock',
            'email' => 'notfound12349823408@example.com',
            'primary' => true,
        ]));
        $participant = EmailParticipant::where('tenant_id', self::$contact->tenant_id)
            ->where('email_address', self::$contact->email)
            ->one();
        $this->assertEquals(self::$contact->name, $participant->name);

        self::$contact2 = new Contact();
        $this->assertTrue(self::$contact2->create([
            'customer' => self::$customer,
            'name' => 'Current User',
            'email' => self::getService('test.user_context')->get()->email,
        ]));

        $contact3 = new Contact();
        $this->assertTrue($contact3->create([
            'customer' => self::$customer,
            'name' => 'Empty Email',
        ]));
        $cnt = EmailParticipant::where('tenant_id', $contact3->tenant_id)
            ->where('name', $contact3->name)
            ->count();
        $this->assertEquals(0, $cnt);
        $contact3->delete();
    }

    /**
     * @depends testCreate
     */
    public function testEventCreated(): void
    {
        $this->assertHasEvent(self::$contact, EventType::ContactCreated);
    }

    /**
     * @depends testCreate
     */
    public function testEdit(): void
    {
        EventSpool::enable();

        self::$contact->primary = false;
        $this->assertTrue(self::$contact->save());

        self::$contact->email = self::getService('test.user_context')->get()->email;
        $this->assertTrue(self::$contact->save());
    }

    /**
     * @depends testEdit
     */
    public function testEventEdited(): void
    {
        $this->assertHasEvent(self::$contact, EventType::ContactUpdated);
    }

    /**
     * @depends testCreate
     */
    public function testToArray(): void
    {
        $expected = [
            'id' => self::$contact->id(),
            'customer_id' => self::$customer->id,
            'object' => 'contact',
            'email' => 'test@example.com',
            'name' => 'Sherlock',
            'title' => null,
            'department' => null,
            'primary' => false,
            'phone' => null,
            'sms_enabled' => false,
            'address1' => null,
            'address2' => null,
            'city' => null,
            'state' => null,
            'postal_code' => null,
            'country' => 'US',
            'send_new_invoices' => false,
            'role_id' => null,
            'created_at' => self::$contact->created_at,
            'updated_at' => self::$contact->updated_at,
        ];

        $this->assertEquals($expected, self::$contact->toArray());
    }

    /**
     * @depends testCreate
     */
    public function testToSearchDocument(): void
    {
        $expected = [
            'name' => 'Sherlock',
            'email' => 'test@example.com',
            'phone' => null,
            'address1' => null,
            'address2' => null,
            'city' => null,
            'state' => null,
            'postal_code' => null,
            'country' => 'US',
            '_customer' => self::$customer->id(),
            'customer' => [
                'name' => self::$customer->name,
            ],
        ];

        $this->assertEquals($expected, (new SearchDocumentFactory())->make(self::$contact));
    }

    /**
     * @depends testCreate
     */
    public function testQuery(): void
    {
        $contacts = Contact::all();

        $this->assertCount(2, $contacts);

        // look for our known contacts
        $find = [self::$contact->id(), self::$contact2->id()];
        foreach ($contacts as $contact) {
            if (false !== ($key = array_search($contact->id(), $find))) {
                unset($find[$key]);
            }
        }
        $this->assertCount(0, $find);
    }

    /**
     * @depends testCreate
     */
    public function testDelete(): void
    {
        EventSpool::enable();
        $this->assertTrue(self::$contact->delete());
    }

    /**
     * @depends testDelete
     */
    public function testEventDeleted(): void
    {
        $this->assertHasEvent(self::$contact, EventType::ContactDeleted);
    }
}
