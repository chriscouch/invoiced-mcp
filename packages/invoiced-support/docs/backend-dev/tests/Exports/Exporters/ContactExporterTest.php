<?php

namespace App\Tests\Exports\Exporters;

use App\AccountsReceivable\Models\Contact;
use App\Exports\Interfaces\ExporterInterface;
use App\Exports\Libs\ExportStorage;
use stdClass;

class ContactExporterTest extends AbstractCsvExporterTest
{
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        self::hasCustomer();
        self::$customer->metadata = new stdClass();
        self::$customer->metadata->test = 1234;
        self::$customer->saveOrFail();

        $contact = new Contact();
        $contact->customer = self::$customer;
        $contact->name = 'My Contact';
        $contact->email = 'contact@example.com';
        $contact->phone = '12345678';
        $contact->saveOrFail();

        $contact2 = new Contact();
        $contact2->customer = self::$customer;
        $contact2->name = 'Contact 2';
        $contact2->saveOrFail();
    }

    public function testBuild(): void
    {
        $expected = 'customer.name,customer.number,name,title,department,email,address1,address2,city,state,postal_code,country,phone,contact_role,created_at
Sherlock,CUST-00001,"My Contact",,,contact@example.com,,,,,,US,12345678,,'.date('Y-m-d').'
,,"Contact 2",,,,,,,,,US,,,'.date('Y-m-d').'
';
        $this->verifyBuild($expected);
    }

    protected function getExporter(ExportStorage $storage): ExporterInterface
    {
        return $this->getExporterById('contact_csv', $storage);
    }
}
