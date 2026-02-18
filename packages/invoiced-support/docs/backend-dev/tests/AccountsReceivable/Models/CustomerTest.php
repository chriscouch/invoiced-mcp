<?php

namespace App\Tests\AccountsReceivable\Models;

use App\AccountsReceivable\Models\AccountsReceivableSettings;
use App\AccountsReceivable\Models\Contact;
use App\AccountsReceivable\Models\CreditNote;
use App\AccountsReceivable\Models\Customer;
use App\AccountsReceivable\Models\Estimate;
use App\AccountsReceivable\Models\Invoice;
use App\CashApplication\Models\Transaction;
use App\Companies\Models\Company;
use App\Companies\Models\Member;
use App\Companies\Models\Role;
use App\Core\Authentication\Models\User;
use App\Core\Orm\ACLModelRequester;
use App\Core\Orm\Error;
use App\Core\Orm\Exception\ModelException;
use App\Core\Orm\Model;
use App\Core\Search\Libs\SearchDocumentFactory;
use App\Core\Utils\Enums\ObjectType;
use App\CustomerPortal\Models\SignUpPage;
use App\ActivityLog\Enums\EventType;
use App\ActivityLog\Libs\EventSpool;
use App\ActivityLog\Models\Event;
use App\PaymentProcessing\Gateways\StripeGateway;
use App\PaymentProcessing\Models\BankAccount;
use App\PaymentProcessing\Models\Card;
use App\PaymentProcessing\Models\MerchantAccount;
use App\PaymentProcessing\Models\PaymentMethod;
use App\Sending\Email\Models\EmailParticipant;
use App\SubscriptionBilling\Models\Subscription;
use App\Tests\AppTestCase;
use stdClass;

class CustomerTest extends AppTestCase
{
    private static Customer $customer2;
    private static Customer $customer3;
    private static ?Model $requester;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        self::hasCompany();
        self::hasPlan();
        self::hasTaxRate();

        self::$requester = ACLModelRequester::get();
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        ACLModelRequester::set(self::$requester);
    }

    public function testGetLocale(): void
    {
        $customer = new Customer();
        $customer->country = 'US';
        $customer->tenant_id = (int) self::$company->id();
        $this->assertEquals('en_US', $customer->getLocale());

        $customer->country = '';
        $this->assertEquals('en', $customer->getLocale());

        $customer->country = 'FR';
        $this->assertEquals('en_FR', $customer->getLocale());

        $customer->language = 'fr';
        $this->assertEquals('fr_FR', $customer->getLocale());

        $customer = new Customer();
        $customer->country = 'US';
        $company = new Company(['id' => -1]);
        $company->language = 'es';
        $customer->tenant_id = -1;
        $customer->setRelation('tenant_id', $company);
        $this->assertEquals('es_US', $customer->getLocale());
    }

    public function testMoneyFormat(): void
    {
        $customer = new Customer();
        $customer->tenant_id = (int) self::$company->id();
        $customer->country = 'US';
        $expected = ['use_symbol' => true, 'locale' => 'en_US'];
        $this->assertEquals($expected, $customer->moneyFormat());

        $customer = new Customer();
        $customer->tenant_id = (int) self::$company->id();
        $customer->language = 'hi';
        $customer->country = 'IN';
        $expected = ['use_symbol' => true, 'locale' => 'hi_IN'];
        $this->assertEquals($expected, $customer->moneyFormat());

        $customer = new Customer();
        $customer->tenant_id = -1;
        $company = new Company();
        $customer->setRelation('tenant_id', $company);
        $customer->country = 'GB';
        $company->show_currency_code = true;
        $expected = ['use_symbol' => false, 'locale' => 'en_GB'];
        $this->assertEquals($expected, $customer->moneyFormat());
    }

    public function testGetBillToCustomer(): void
    {
        $customer = new Customer();
        $this->assertEquals($customer, $customer->getBillToCustomer());

        $customer->bill_to_parent = true;
        $this->assertEquals($customer, $customer->getBillToCustomer());

        $customer->parent_customer = -1;
        $parentCustomer = new Customer(['id' => -1]);
        $customer->setRelation('parent_customer', $parentCustomer);

        $this->assertEquals($parentCustomer, $customer->getBillToCustomer());

        $parentCustomer->bill_to_parent = true;
        $parentCustomer->parent_customer = -2;
        $parentCustomer2 = new Customer(['id' => -2]);
        $parentCustomer->setRelation('parent_customer', $parentCustomer2);
        $this->assertEquals($parentCustomer2, $customer->getBillToCustomer());
    }

    public function testAddress(): void
    {
        $customer = new Customer();
        $customer->tenant_id = (int) self::$company->id();
        $customer->name = 'Sherlock Holmes';
        $customer->attention_to = 'Test';
        $customer->address1 = '221B Baker St';
        $customer->address2 = 'Unit 1';
        $customer->city = 'London';
        $customer->state = 'England';
        $customer->country = 'GB';
        $customer->postal_code = '1234';
        $customer->tax_id = '12345';
        $customer->type = 'company';
        $this->assertEquals('Sherlock Holmes
221B Baker St
Unit 1
London
1234
United Kingdom
VAT Reg No: 12345', $customer->address());

        $this->assertEquals('221B Baker St
Unit 1
London
1234
United Kingdom
VAT Reg No: 12345', $customer->address);
    }

    public function testAddressMissingCountry(): void
    {
        $customer = new Customer();
        $customer->tenant_id = (int) self::$company->id();
        $customer->name = 'Invoiced, Inc.';
        $customer->address1 = '5301 Southwest Parkway';
        $customer->address2 = 'Suite 470';
        $customer->city = 'Austin';
        $customer->state = 'TX';
        $customer->postal_code = '78735';
        $this->assertEquals('Invoiced, Inc.
5301 Southwest Parkway
Suite 470
Austin, TX 78735', $customer->address());

        $this->assertEquals('5301 Southwest Parkway
Suite 470
Austin, TX 78735', $customer->address);
    }

    public function testUrl(): void
    {
        $customer = new Customer();
        $customer->tenant_id = (int) self::$company->id();
        $customer->client_id = 'test';
        $this->assertEquals('http://invoiced.localhost:1234/statements/'.self::$company->identifier.'/test', $customer->statement_url);
    }

    public function testUrlNoCustomerPortal(): void
    {
        self::$company->features->disable('billing_portal');
        $customer = new Customer();
        $customer->tenant_id = (int) self::$company->id();
        $customer->client_id = 'test';
        $this->assertEquals('http://invoiced.localhost:1234/statements/'.self::$company->identifier.'/test', $customer->statement_url);
        self::$company->features->enable('billing_portal');
    }

    public function testEventAssociations(): void
    {
        $customer = new Customer();

        $this->assertEquals([], $customer->getEventAssociations());
    }

    public function testEventObject(): void
    {
        $customer = new Customer();

        $expected = array_merge($customer->toArray(), [
            'ach_gateway' => null,
            'cc_gateway' => null,
            'late_fee_schedule' => null,
            'network_connection' => null,
            'owner' => null,
        ]);
        $this->assertEquals($expected, $customer->getEventObject());
    }

    public function testSignUpUrl(): void
    {
        $customer = new Customer();
        $customer->tenant_id = (int) self::$company->id();
        $customer->client_id = 'cust_client_id';
        $this->assertNull($customer->sign_up_url);

        $page = new SignUpPage();
        $page->tenant_id = (int) self::$company->id();
        $page->client_id = 'page_client_id';
        $customer->sign_up_page = 10;
        $customer->setRelation('sign_up_page_id', $page);
        $this->assertEquals('http://'.self::$company->username.'.invoiced.localhost:1234/sign_up/cust_client_id', $customer->sign_up_url);
    }

    public function testCreateInvalidTaxes(): void
    {
        $customer = new Customer();
        $customer->name = 'Invalid Taxes Test';
        $customer->taxes = [['not_a_tax']];
        $this->assertFalse($customer->save());
    }

    public function testCreateInvalidOwner(): void
    {
        $customer = new Customer();
        $customer->name = 'Invalid Owner';
        $owner = new User();
        $owner->id = -1;
        $customer->owner = $owner;
        $this->assertFalse($customer->save());
    }

    public function testCreateInvalidParentCustomer(): void
    {
        $customer = new Customer();
        $customer->name = 'Invalid Parent';
        $customer->parent_customer = -1;
        $this->assertFalse($customer->save());
    }

    public function testCreate(): void
    {
        EventSpool::enable();

        self::$customer = new Customer();
        $this->assertTrue(self::$customer->create([
            'name' => 'Sherlock Holmes',
            'number' => 'CUST-001',
            'type' => 'person',
            'payment_terms' => 'NET 30',
            'taxes' => [self::$taxRate->id, self::$taxRate->id],
            'address1' => '221B Baker St',
            'address2' => 'Unit 1',
            'city' => 'London',
            'state' => 'England',
            'postal_code' => '1234',
            'country' => 'GB',
            'phone' => '1234567890',
            'email' => 'sherlock@example.com',
            'tax_id' => 12345,
        ]));

        $this->assertEquals(self::$company->id(), self::$customer->tenant_id);
        $this->assertEquals('CUST-001', self::$customer->number);
        $participant = EmailParticipant::where('tenant_id', self::$customer->tenant_id)
            ->where('email_address', self::$customer->email)
            ->one();
        $this->assertEquals(self::$customer->name, $participant->name);

        self::$company->accounts_receivable_settings->payment_terms = 'NET 7';
        $this->assertTrue(self::$company->accounts_receivable_settings->save());

        self::$customer2 = new Customer();
        $this->assertTrue(self::$customer2->create([
            'name' => 'Sherlock Holmes 2',
            'address1' => '221B Baker St',
            'city' => 'London',
            'state' => 'England',
            'postal_code' => '1234',
            'country' => 'GB',
            'phone' => '1234567890',
            'email' => 'sherlock2@example.com', ]));
        $this->assertEquals('CUST-00001', self::$customer2->number);
        $this->assertEquals('NET 7', self::$customer2->payment_terms);

        self::$customer3 = new Customer();
        $this->assertTrue(self::$customer3->create([
            'name' => 'Sherlock Holmes 3',
            'address1' => '221B Baker St',
            'city' => 'London',
            'state' => 'England',
            'postal_code' => '1234',
            'country' => 'GB',
            'phone' => '1234567890',
            'owner' => self::getService('test.user_context')->get(),
            'parent_customer' => self::$customer2->id(),
        ]));
        $this->assertEquals('CUST-00002', self::$customer3->number);
        $cnt = EmailParticipant::where('tenant_id', self::$customer3->tenant_id)
            ->where('name', self::$customer3->name)
            ->count();
        $this->assertEquals(0, $cnt);
    }

    /**
     * @depends testCreate
     */
    public function testNextCustomerNumberCollisions(): void
    {
        $sequence = self::$customer->getNumberingSequence();
        $sequence->setNext(100);

        // create some customers to test collision prevention
        for ($i = 0; $i < 10; ++$i) {
            $customer = new Customer();
            $customer->name = 'Number Collision Test';
            $customer->number = 'CUST-00'.(100 + $i);
            $this->assertTrue($customer->save());
        }

        // test next customer #
        $this->assertEquals(110, $sequence->nextNumber());
    }

    /**
     * @depends testCreate
     */
    public function testCreateNonUnique(): void
    {
        $customer = new Customer();
        $errors = $customer->getErrors();

        // should not be able to create customer with non-unique #
        $customer->name = 'Sherlock Holmes 2';
        $customer->number = 'CUST-001';

        // we need to release lock for proper testing
        $sequence = self::$customer->getNumberingSequence();
        $sequence->release($customer->number);
        $this->assertFalse($customer->save());

        $this->assertCount(1, $errors);
        $this->assertEquals('The given customer number has already been taken: CUST-001', $errors->all()[0]);
    }

    public function testCreateAutomaticFail(): void
    {
        self::getService('test.event_spool')->flush();
        EventSpool::enable();

        $customer = new Customer();
        $errors = $customer->getErrors();

        $customer->name = 'AutoPay Test';
        $customer->autopay = true;
        $this->assertFalse($customer->save());

        $this->assertCount(1, $errors);
        $this->assertEquals('Sorry, this business does not support AutoPay. Please enable a supported payment method in Settings > Payments first.', $errors->all()[0]);

        // should not create any events
        $this->assertEquals(0, Event::where('object_type_id', ObjectType::Customer->value)->where('object_id', $customer)->count());
    }

    public function testCreateStripeTokenFail(): void
    {
        $this->expectException(ModelException::class);
        $this->expectExceptionMessage('Sorry, we could not save the provided payment information because this business does not support saving payment sources with the card payment method.');
        self::getService('test.transaction_manager')->perform(function () {
            $customer = new Customer();
            $customer->stripe_token = 'tok_test'; /* @phpstan-ignore-line */
            $customer->name = 'Stripe Token Test';
            $customer->saveOrFail();
        });
    }

    public function testCreateNameMissing(): void
    {
        $customer = new Customer();
        $errors = $customer->getErrors();

        $this->assertFalse($customer->create(['name' => '']));

        $this->assertCount(1, $errors);
        $this->assertEquals('Name must be a string between 1 and 255 characters.', $errors->all()[0]);
    }

    /**
     * @depends testCreate
     */
    public function testEventCreated(): void
    {
        $this->assertHasEvent(self::$customer, EventType::CustomerCreated);
    }

    /**
     * @depends testCreate
     */
    public function testToArray(): void
    {
        $expected = [
            'id' => self::$customer->id(),
            'object' => 'customer',
            'name' => 'Sherlock Holmes',
            'number' => 'CUST-001',
            'type' => 'person',
            'language' => null,
            'autopay' => false,
            'autopay_delay_days' => -1,
            'payment_terms' => 'NET 30',
            'payment_source' => null,
            'taxable' => true,
            'taxes' => [self::$taxRate->toArray()],
            'avalara_entity_use_code' => null,
            'avalara_exemption_number' => null,
            'sign_up_page' => null,
            'chase' => true,
            'chasing_cadence' => null,
            'next_chase_step' => null,
            'credit_hold' => false,
            'credit_limit' => null,
            'parent_customer' => null,
            'bill_to_parent' => false,
            'consolidated' => false,
            'attention_to' => null,
            'address1' => '221B Baker St',
            'address2' => 'Unit 1',
            'city' => 'London',
            'state' => 'England',
            'postal_code' => '1234',
            'country' => 'GB',
            'email' => 'sherlock@example.com',
            'phone' => '1234567890',
            'tax_id' => '12345',
            'notes' => null,
            'statement_url' => 'http://invoiced.localhost:1234/statements/'.self::$company->identifier.'/'.self::$customer->client_id,
            'statement_pdf_url' => 'http://invoiced.localhost:1234/statements/'.self::$company->identifier.'/'.self::$customer->client_id.'/pdf',
            'sign_up_url' => null,
            'owner_id' => null,
            'metadata' => new stdClass(),
            'created_at' => self::$customer->created_at,
            'updated_at' => self::$customer->updated_at,
            'currency' => null,
            'ach_gateway_id' => null,
            'cc_gateway_id' => null,
            'convenience_fee' => true,
            'surcharging' => true,
            'active' => true,
            'late_fee_schedule_id' => null,
            'network_connection_id' => null,
            'ach_gateway_object' => null,
            'cc_gateway_object' => null
        ];

        $this->assertEquals($expected, self::$customer->toArray());
    }

    /**
     * @depends testCreate
     */
    public function testToSearchDocument(): void
    {
        $expected = [
            'name' => 'Sherlock Holmes',
            'number' => 'CUST-001',
            'autopay' => false,
            'payment_terms' => 'NET 30',
            'payment_source' => null,
            'currency' => null,
            'chase' => true,
            'attention_to' => null,
            'address1' => '221B Baker St',
            'address2' => 'Unit 1',
            'city' => 'London',
            'state' => 'England',
            'postal_code' => '1234',
            'country' => 'GB',
            'email' => 'sherlock@example.com',
            'phone' => '1234567890',
            'tax_id' => '12345',
            'metadata' => [],
            '_customer' => self::$customer->id(),
        ];

        $this->assertEquals($expected, (new SearchDocumentFactory())->make(self::$customer));
    }

    /**
     * @depends testCreate
     */
    public function testEdit(): void
    {
        EventSpool::enable();

        self::$customer->phone = '8675309';
        $this->assertTrue(self::$customer->save());
    }

    /**
     * @depends testEdit
     */
    public function testEventEdited(): void
    {
        $this->assertHasEvent(self::$customer, EventType::CustomerUpdated);
    }

    /**
     * @depends testCreate
     */
    public function testEditNonUnique(): void
    {
        // should not be able to edit customers with non-unique #
        self::$customer2->number = 'CUST-001';
        $this->assertFalse(self::$customer2->save());
    }

    public function testEditDoubleTaxes(): void
    {
        self::$customer->taxes = [self::$taxRate->id, self::$taxRate->id];
        $this->assertTrue(self::$customer->save());
        $this->assertEquals([self::$taxRate->id], self::$customer->taxes);
    }

    /**
     * @depends testCreate
     */
    public function testSetDefaultPaymentSource(): void
    {
        EventSpool::enable();

        self::hasCard();
        $this->assertTrue(self::$customer->setDefaultPaymentSource(self::$card));

        $this->assertEquals('card', self::$customer->default_source_type);
        $this->assertEquals(self::$card->id(), self::$customer->default_source_id);

        self::getService('test.event_spool')->flush(); // write out events

        // should generate an event
        $event = Event::where('type_id', EventType::CustomerUpdated->toInteger())
            ->where('object_type_id', ObjectType::Customer->value)
            ->where('object_id', self::$customer)
            ->sort('id DESC')
            ->oneOrNull();

        // with a changed payment source
        $this->assertInstanceOf(Event::class, $event);
        $storage = self::getService('test.event_storage');
        $event->hydrateFromStorage($storage);
        $this->assertTrue(property_exists($event->previous, 'payment_source')); /* @phpstan-ignore-line */
    }

    /**
     * @depends testCreate
     */
    public function testPaymentSources(): void
    {
        self::hasBankAccount();

        $sources = self::$customer->paymentSources();

        $this->assertCount(2, $sources);
        $this->assertInstanceOf(Card::class, $sources[0]);
        $this->assertEquals(self::$card->id(), $sources[0]->id());
        $this->assertInstanceOf(BankAccount::class, $sources[1]);
        $this->assertEquals(self::$bankAccount->id(), $sources[1]->id());
    }

    /**
     * @depends testCreate
     */
    public function testSetAutoPayFail(): void
    {
        $errors = self::$customer->getErrors();

        self::$customer->autopay = true;
        $this->assertFalse(self::$customer->save());
        self::$customer->clearCache();

        $this->assertCount(1, $errors);
        $this->assertEquals('Sorry, this business does not support AutoPay. Please enable a supported payment method in Settings > Payments first.', $errors->all()[0]);
    }

    /**
     * @depends testCreate
     */
    public function testSaveStripeTokenFail(): void
    {
        $errors = self::$customer->getErrors();

        self::$customer->stripe_token = 'tok_test'; /* @phpstan-ignore-line */
        $this->assertFalse(self::$customer->save());

        $this->assertCount(1, $errors);
        $this->assertEquals('Sorry, we could not save the provided payment information because this business does not support saving payment sources with the card payment method.', $errors->all()[0]);
    }

    /**
     * @depends testCreate
     */
    public function testQuery(): void
    {
        $customers = Customer::all();

        $this->assertCount(13, $customers);

        // look for our 3 known customers
        $find = [self::$customer->id(), self::$customer2->id(), self::$customer3->id()];
        foreach ($customers as $customer) {
            if (false !== ($key = array_search($customer->id(), $find))) {
                unset($find[$key]);
            }
        }
        $this->assertCount(0, $find);
    }

    /**
     * @depends testCreate
     */
    public function testQueryCustomFieldRestriction(): void
    {
        $member = new Member();
        $member->setUser(self::getService('test.user_context')->get());
        $member->restriction_mode = Member::CUSTOM_FIELD_RESTRICTION;
        $member->restrictions = ['territory' => ['Texas']];

        ACLModelRequester::set($member);

        $this->assertEquals(0, Customer::count());

        // update the customer territory
        self::$customer->metadata = (object) ['territory' => 'Texas'];
        self::$customer->saveOrFail();

        $customers = Customer::all();
        $this->assertCount(1, $customers);
        $this->assertEquals(self::$customer->id(), $customers[0]->id());
    }

    /**
     * @depends testCreate
     */
    public function testQueryOwnerRestriction(): void
    {
        $member = new Member();
        $member->role = Role::ADMINISTRATOR;
        $member->setUser(self::getService('test.user_context')->get());
        $member->restriction_mode = Member::OWNER_RESTRICTION;

        ACLModelRequester::set($member);

        $this->assertEquals(1, Customer::count());

        $this->assertEquals(0, Customer::where('owner_id', null)->count());
    }

    /**
     * @depends testCreate
     */
    public function testFindClientId(): void
    {
        $this->assertNull(Customer::findClientId(''));
        $this->assertNull(Customer::findClientId('1234'));

        $this->assertEquals(self::$customer->id(), Customer::findClientId(self::$customer->client_id)->id()); /* @phpstan-ignore-line */

        $old = self::$customer->client_id;
        self::$customer->refreshClientId();
        $this->assertNotEquals($old, self::$customer->client_id);

        // set client ID in the past
        self::$customer->refreshClientId(false, strtotime('-1 year'));
        /** @var Customer $obj */
        $obj = Customer::findClientId(self::$customer->client_id);

        // set the client ID to expire soon
        self::$customer->refreshClientId(false, strtotime('+29 days'));
        /** @var Customer $obj */
        $obj = Customer::findClientId(self::$customer->client_id);
    }

    /**
     * @depends testCreate
     */
    public function testContacts(): void
    {
        $contacts = self::$customer->contacts();

        $this->assertCount(1, $contacts);
        $this->assertEquals('Sherlock Holmes', $contacts[0]->name);
        $this->assertEquals('sherlock@example.com', $contacts[0]->email);
        $this->assertTrue($contacts[0]->primary);
    }

    /**
     * @depends testCreate
     */
    public function testEmailContacts(): void
    {
        $this->assertEquals([[
            'name' => 'Sherlock Holmes',
            'email' => 'sherlock@example.com', ]], self::$customer->emailContacts());
    }

    /**
     * @depends testCreate
     */
    public function testEmailAddress(): void
    {
        $customer = new Customer(['id' => -1]);
        $this->assertNull($customer->emailAddress());

        $this->assertEquals('sherlock@example.com', self::$customer->emailAddress());

        $this->assertNull(self::$customer3->emailAddress());

        // test with a single contact, but no main email address
        $contact = new Contact();
        $contact->customer = self::$customer3;
        $contact->name = 'Test';
        $contact->email = 'test@example.com';
        $this->assertTrue($contact->save());

        $this->assertEquals('test@example.com', self::$customer3->emailAddress());

        // test with 2 contacts, but no main email address
        $contact = new Contact();
        $contact->customer = self::$customer3;
        $contact->name = 'Test 2';
        $contact->email = 'test2@example.com';
        $this->assertTrue($contact->save());

        $this->assertEquals('test@example.com', self::$customer3->emailAddress());
    }

    public function testCalculatePrimaryCurrency(): void
    {
        self::$company->features->enable('multi_currency');
        $customer = new Customer();
        $customer->name = 'Multicurrency';
        $customer->saveOrFail();

        $invoice = new Invoice();
        $invoice->setCustomer($customer);
        $invoice->items = [['unit_cost' => 5]];
        $invoice->saveOrFail();

        $this->assertEquals('usd', $customer->calculatePrimaryCurrency());

        // $customer->currency should be saved as the last calculated currency
        $this->assertEquals('usd', $customer->currency);

        $invoice = new Invoice();
        $invoice->setCustomer($customer);
        $invoice->items = [['unit_cost' => 5]];
        $invoice->currency = 'eur';
        $invoice->saveOrFail();

        $customer->currency = null;
        $this->assertEquals('eur', $customer->calculatePrimaryCurrency());

        $invoice = new Invoice();
        $invoice->setCustomer($customer);
        $invoice->items = [['unit_cost' => 5]];
        $invoice->currency = 'usd';
        $invoice->saveOrFail();

        $customer->currency = null;
        $this->assertEquals('usd', $customer->calculatePrimaryCurrency());
    }

    /**
     * @depends testQueryCustomFieldRestriction
     */
    public function testMetadata(): void
    {
        self::$customer->refresh();
        $metadata = self::$customer->metadata;
        $metadata->test = true;
        self::$customer->metadata = $metadata;
        $this->assertTrue(self::$customer->save());
        $this->assertEquals((object) ['test' => true, 'territory' => 'Texas'], self::$customer->metadata);

        self::$customer->metadata = (object) ['internal.id' => '12345'];
        $this->assertTrue(self::$customer->save());
        $this->assertEquals((object) ['internal.id' => '12345'], self::$customer->metadata);

        self::$customer->metadata = (object) ['array' => [], 'object' => new stdClass()];
        $this->assertTrue(self::$customer->save());
        $this->assertEquals((object) ['array' => [], 'object' => new stdClass()], self::$customer->metadata);


        $metadata = [
            'key1' => 'value1',
            'key2' => 'value1',
            'key3' => 'value1',
            'key4' => 'value1',
            'key5' => 'value1',
            'key6' => 'value1',
            'key7' => 'value1',
            'key8' => 'value1',
            'key9' => 'value1',
            'key10' => 'value1',
            'key11' => 'value1',
        ];

        self::$customer->metadata = (object) $metadata;
        try {
            self::$customer->saveOrFail();
            $this->fail('ModelException should not be thrown');
        } catch (ModelException $e) {
            $this->assertEquals('Failed to save Customer: There can only be up to 10 metadata values. 11 values were provided.', $e->getMessage());
        }

        unset($metadata['key11']);
        $metadata[StripeGateway::METADATA_STRIPE_CUSTOMER] = 'abc';
        self::$customer->metadata = (object) $metadata;
        self::$customer->saveOrFail();
        $customer = Customer::findOrFail(self::$customer->id());
        $this->assertEquals([
            'key1' => 'value1',
            'key2' => 'value1',
            'key3' => 'value1',
            'key4' => 'value1',
            'key5' => 'value1',
            'key6' => 'value1',
            'key7' => 'value1',
            'key8' => 'value1',
            'key9' => 'value1',
            'key10' => 'value1',
            StripeGateway::METADATA_STRIPE_CUSTOMER => 'abc',
        ], (array) $customer->metadata);
    }

    /**
     * @depends testCreate
     */
    public function testBadMetadata(): void
    {
        self::$customer->metadata = (object) [str_pad('', 41) => 'fail'];
        $this->assertFalse(self::$customer->save());

        self::$customer->metadata = (object) ['fail' => str_pad('', 256)];
        $this->assertFalse(self::$customer->save());

        self::$customer->metadata = (object) array_fill(0, 11, 'fail');
        $this->assertFalse(self::$customer->save());
    }

    /**
     * @depends testCreate
     */
    public function testDelete(): void
    {
        EventSpool::enable();

        $this->assertTrue(self::$customer->delete());

        $deleteModels = [
            Estimate::class,
            Invoice::class,
            Transaction::class,
            Subscription::class,
        ];

        foreach ($deleteModels as $m) {
            $this->assertEquals(0, $m::where('customer', self::$customer->id())->count());
        }
    }

    /**
     * @depends testDelete
     */
    public function testEventDeleted(): void
    {
        $this->assertHasEvent(self::$customer, EventType::CustomerDeleted);
    }

    public function testCreateInheritAutoPay(): void
    {
        self::$company->accounts_receivable_settings->default_collection_mode = AccountsReceivableSettings::COLLECTION_MODE_AUTO;
        self::$company->accounts_receivable_settings->save();

        $customer = new Customer();
        $errors = $customer->getErrors();

        $customer->name = 'Test';
        $this->assertFalse($customer->save());

        $this->assertCount(1, $errors);
        $this->assertEquals('Sorry, this business does not support AutoPay. Please enable a supported payment method in Settings > Payments first.', $errors->all()[0]);
    }

    public function testCreateInheritConsolidatedInvoicing(): void
    {
        self::$company->accounts_receivable_settings->default_collection_mode = AccountsReceivableSettings::COLLECTION_MODE_MANUAL;
        self::$company->accounts_receivable_settings->default_consolidated_invoicing = true;
        self::$company->accounts_receivable_settings->saveOrFail();

        $customer = new Customer();
        $customer->name = 'Test';
        $customer->saveOrFail();

        $this->assertTrue($customer->consolidated);
    }

    public function testResetAutopayInvoices(): void
    {
        self::acceptsCreditCards();
        self::hasCustomer();
        self::hasInvoice();
        $voidedInvoice = self::$invoice;
        $voidedInvoice->autopay = false;
        $voidedInvoice->save();
        self::hasInvoice();

        $invoice = self::$invoice;
        $invoice->autopay = false;
        $invoice->save();

        $customer = self::$customer;

        $customer->autopay = true;
        $customer->saveOrFail();
        $invoice = $invoice->refresh();
        $this->assertFalse($invoice->autopay);

        $invoice->autopay = true;
        $invoice->payment_terms = 'NET 30';
        $invoice->due_date = time();
        $invoice->save();

        $voidedInvoice->autopay = true;
        $voidedInvoice->saveOrFail();
        $voidedInvoice->void();
        $voidedInvoice = $voidedInvoice->refresh();

        $invoice = $invoice->refresh();
        $this->assertTrue($invoice->autopay);

        $customer = $customer->refresh();
        $customer->autopay = false;
        $customer->save();
        $invoice = $invoice->refresh();
        $customer = $customer->refresh();

        $this->assertFalse($customer->autopay);
        $this->assertTrue($voidedInvoice->autopay);
        $this->assertFalse($invoice->autopay);
        $this->assertEmpty($invoice->payment_terms);
        $this->assertNotNull($invoice->due_date);
    }

    public function testIsParentOf(): void
    {
        $customer = new Customer();
        $customer->name = 'Customer 1';
        $customer->saveOrFail();

        $customer2 = new Customer();
        $customer2->name = 'Customer 2';
        $customer2->saveOrFail();

        $this->assertFalse($customer->isParentOf($customer));
        $this->assertFalse($customer->isParentOf($customer2));
        $this->assertFalse($customer2->isParentOf($customer));

        $customer3 = new Customer();
        $customer3->name = 'Customer 3';
        $customer3->setParentCustomer($customer);
        $customer3->saveOrFail();

        $customer4 = new Customer();
        $customer4->name = 'Customer 4';
        $customer4->setParentCustomer($customer3);
        $customer4->saveOrFail();

        $customer5 = new Customer();
        $customer5->name = 'Customer 5';
        $customer5->setParentCustomer($customer4);
        $customer5->saveOrFail();

        $customer6 = new Customer();
        $customer6->name = 'Customer 6';
        $customer6->setParentCustomer($customer5);
        $customer6->saveOrFail();

        // check each hierarchy level
        $this->assertTrue($customer->isParentOf($customer3));
        $this->assertFalse($customer3->isParentOf($customer));

        $this->assertTrue($customer->isParentOf($customer4));
        $this->assertTrue($customer3->isParentOf($customer4));
        $this->assertFalse($customer4->isParentOf($customer));

        $this->assertTrue($customer->isParentOf($customer5));
        $this->assertTrue($customer4->isParentOf($customer5));
        $this->assertTrue($customer3->isParentOf($customer5));
        $this->assertFalse($customer5->isParentOf($customer));

        $this->assertTrue($customer->isParentOf($customer6));
        $this->assertTrue($customer3->isParentOf($customer6));
        $this->assertTrue($customer4->isParentOf($customer6));
        $this->assertTrue($customer5->isParentOf($customer6));
        $this->assertFalse($customer6->isParentOf($customer));
    }

    public function testSetParentToSelf(): void
    {
        $customer = new Customer();
        $customer->name = 'Customer 1';
        $customer->saveOrFail();

        $customer->setParentCustomer($customer);
        $this->expectException(ModelException::class);
        $this->expectExceptionMessage('Cannot set parent customer as self.');

        $customer->saveOrFail();
    }

    public function testSetParentCycle(): void
    {
        $customer = new Customer();
        $customer->name = 'Customer 1';
        $customer->saveOrFail();

        $customer2 = new Customer();
        $customer2->name = 'Customer 2';
        $customer2->setParentCustomer($customer);
        $customer2->saveOrFail();

        $customer3 = new Customer();
        $customer3->name = 'Customer 3';
        $customer3->setParentCustomer($customer2);
        $customer3->saveOrFail();

        $customer->setParentCustomer($customer2);
        $this->expectException(ModelException::class);
        $this->expectExceptionMessage('Failed to save Customer: Cannot set sub-customer as a parent.');
        $customer->saveOrFail();

        $customer->setParentCustomer($customer3);
        $this->expectException(ModelException::class);
        $this->expectExceptionMessage('Failed to save Customer: Cannot set sub-customer as a parent.');
        $customer->saveOrFail();
    }

    public function testGetPreferredMerchantAccount(): void
    {
        $merchantAccount = new MerchantAccount();
        $merchantAccount->name = 'Account: stripe';
        $merchantAccount->top_up_threshold_num_of_days = 14;
        $merchantAccount->gateway = StripeGateway::ID;
        $merchantAccount->gateway_id = 'xxxxxxxxxxxxxxxxxx';
        $merchantAccount->credentials = (object) ['secret' => 'xxxxxxxxxxxxxxxxxx'];
        $merchantAccount->saveOrFail();

        $this->assertNull(self::$customer->merchantAccount(PaymentMethod::ACH));
        $this->assertNull(self::$customer->merchantAccount(PaymentMethod::CREDIT_CARD));

        self::$customer->ach_gateway = $merchantAccount;
        self::$customer->cc_gateway = $merchantAccount;
        $this->assertInstanceOf(MerchantAccount::class, self::$customer->merchantAccount(PaymentMethod::ACH));
        $this->assertInstanceOf(MerchantAccount::class, self::$customer->merchantAccount(PaymentMethod::CREDIT_CARD));
    }

    public function testSetInactive(): void
    {
        $customer = new Customer();
        $customer->name = 'Customer';
        $customer->saveOrFail();

        $subscription = self::getService('test.create_subscription')
            ->create([
                'customer' => $customer,
                'plan' => self::$plan,
                'start_date' => (int) mktime(0, 0, 0, (int) date('m'), (int) date('d'), (int) date('Y')),
            ]);

        // Customer has active subscription. It
        // should not be able to be set to inactive.
        $customer->active = false;
        $this->assertFalse($customer->save());
        $this->assertEquals('Customers with an active subscription cannot be deactivated', (string) $customer->getErrors());

        // Cancelling / finishing the subscription should
        // allow the customer to be deactivated.
        self::getService('test.cancel_subscription')->cancel($subscription);
        $this->assertTrue($customer->save());
    }

    public function testHardDelete(): void
    {
        $customer = new Customer();
        $customer->name = 'Customer';
        $customer->saveOrFail();

        // open invoice prevents hard delete
        $invoice = new Invoice();
        $invoice->setCustomer($customer);
        $invoice->items = [['unit_cost' => 100]];
        $invoice->saveOrFail();

        // active subscription prevents hard delete
        $subscription = self::getService('test.create_subscription')
            ->create([
                'customer' => $customer,
                'plan' => self::$plan,
                'start_date' => (int) mktime(0, 0, 0, (int) date('m'), (int) date('d'), (int) date('Y')),
            ]);

        $this->assertFalse($customer->delete());
        /** @var Error $error */
        $error = $customer->getErrors()[0];
        $this->assertEquals('Customers with transactions cannot be deleted', $error->getMessage());

        // delete all transactions to test
        // the active subscription error
        Invoice::where('customer', $customer->id())->delete();
        CreditNote::where('customer', $customer->id())->delete();
        Estimate::where('customer', $customer->id())->delete();

        $this->assertFalse($customer->delete());
        /** @var Error $error */
        $error = $customer->getErrors()[0];
        $this->assertEquals('Customers with an active subscription cannot be deleted', $error->getMessage());

        self::getService('test.cancel_subscription')->cancel($subscription);

        $this->assertTrue($customer->delete());
    }

    public function testEditEmptyNumber(): void
    {
        $customer = new Customer();
        $customer->name = 'testEditEmptyNumber';
        $customer->saveOrFail();

        $customer->number = null; /* @phpstan-ignore-line */
        $this->assertFalse($customer->save());
    }
}
