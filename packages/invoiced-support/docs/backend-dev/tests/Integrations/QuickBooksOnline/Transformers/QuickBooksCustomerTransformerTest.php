<?php

namespace App\Tests\Integrations\QuickBooksOnline\Transformers;

use App\Integrations\AccountingSync\Interfaces\AccountingRecordInterface;
use App\Integrations\AccountingSync\ValueObjects\AccountingCustomer;
use App\Integrations\AccountingSync\ValueObjects\AccountingJsonRecord;
use App\Integrations\Enums\IntegrationType;
use App\Integrations\QuickBooksOnline\Models\QuickBooksAccount;
use App\Integrations\QuickBooksOnline\Models\QuickBooksOnlineSyncProfile;
use App\Integrations\QuickBooksOnline\Transformers\QuickBooksCustomerTransformer;
use App\Tests\AppTestCase;

class QuickBooksCustomerTransformerTest extends AppTestCase
{
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        self::hasCompany();
    }

    private function getTransformer(): QuickBooksCustomerTransformer
    {
        return new QuickBooksCustomerTransformer();
    }

    /**
     * @dataProvider transformProvider
     */
    public function testTransform(AccountingRecordInterface $input, mixed $expected): void
    {
        $transformer = $this->getTransformer();
        $account = new QuickBooksAccount();
        $syncProfile = new QuickBooksOnlineSyncProfile();
        $syncProfile->read_pdfs = false;
        $syncProfile->read_invoices_as_drafts = false;
        $transformer->initialize($account, $syncProfile);

        $this->assertEquals($expected, $transformer->transform($input));
    }

    public function transformProvider(): array
    {
        return [
            [
                new AccountingJsonRecord(
                    (object) json_decode('{
        "Id": "2",
        "SyncToken": "6",
        "MetaData": {
          "CreatedTime": "2016-07-12T11:25:09-07:00",
          "LastUpdatedTime": "2017-03-04T08:38:37-08:00"
        },
        "FullyQualifiedName": "Test",
        "DisplayName": "Test",
        "CurrencyRef": {
          "name": "United States Dollar",
          "value": "USD"
        },
        "PrintOnCheckName": "Test",
        "Active": true,
        "PrimaryEmailAddr": {
          "Address": "test@example.com"
        },
        "DefaultTaxCodeRef": {
          "value": 3
        },
        "Taxable": true,
        "PrimaryPhone": {
          "FreeFormNumber": "(123) 456-7890"
        },
        "BillAddr": {
          "Id": "2",
          "Line1": "1234 Main St",
          "City": "Austin",
          "CountrySubDivisionCode": "TX",
          "PostalCode": "123456",
          "Country": "US"
        },
        "Job": false,
        "BillWithParent": false,
        "Balance": 0,
        "BalanceWithJobs": 0,
        "PreferredDeliveryMethod": "None"
      }'),
                ),
                new AccountingCustomer(
                    integration: IntegrationType::QuickBooksOnline,
                    accountingId: '2',
                    values: [
                        'name' => 'Test',
                        'active' => true,
                        'address1' => '1234 Main St',
                        'address2' => null,
                        'city' => 'Austin',
                        'state' => 'TX',
                        'postal_code' => '123456',
                        'country' => 'US',
                        'phone' => '(123) 456-7890',
                        'currency' => 'usd',
                    ],
                    emails: ['test@example.com'],
                ),
            ],
            [
                new AccountingJsonRecord(
                    (object) json_decode('{
        "Id": "3",
        "SyncToken": "6",
        "MetaData": {
          "CreatedTime": "2016-07-12T11:25:09-07:00",
          "LastUpdatedTime": "2017-03-04T08:38:37-08:00"
        },
        "FullyQualifiedName": "Test 2",
        "DisplayName": "Test 2",
        "PrintOnCheckName": "Test 2",
        "Active": true,
        "DefaultTaxCodeRef": {
          "value": 3
        },
        "ParentRef": {
          "value": "2"
        },
        "Taxable": true,
        "Job": false,
        "BillWithParent": false,
        "Balance": 0,
        "BalanceWithJobs": 0,
        "PreferredDeliveryMethod": "None",
        "Notes": "Test note"
      }'),
                ),
            new AccountingCustomer(
                integration: IntegrationType::QuickBooksOnline,
                accountingId: '3',
                values: [
                    'name' => 'Test 2',
                    'active' => true,
                    'notes' => 'Test note',
                    'address1' => null,
                    'address2' => null,
                    'city' => null,
                    'state' => null,
                    'postal_code' => null,
                ],
                parentCustomer: new AccountingCustomer(
                    integration: IntegrationType::QuickBooksOnline,
                    accountingId: '2',
                    values: [],
                )
            ),
            ],
            [
                new AccountingJsonRecord(
                    (object) json_decode('{
        "Id": "4",
        "SyncToken": "6",
        "MetaData": {
          "CreatedTime": "2016-07-12T11:25:09-07:00",
          "LastUpdatedTime": "2017-03-04T08:38:37-08:00"
        },
        "FullyQualifiedName": "After",
        "DisplayName": "After",
        "PrintOnCheckName": "After",
        "Active": true,
        "PrimaryEmailAddr": {
          "Address": "after@example.com, after2@example.com"
        },
        "DefaultTaxCodeRef": {
          "value": 3
        },
        "Taxable": true,
        "Job": false,
        "BillWithParent": false,
        "Balance": 0,
        "BalanceWithJobs": 0,
        "PreferredDeliveryMethod": "None"
      }'),
                ),
            new AccountingCustomer(
                integration: IntegrationType::QuickBooksOnline,
                accountingId: '4',
                values: [
                    'name' => 'After',
                    'active' => true,
                    'address1' => null,
                    'address2' => null,
                    'city' => null,
                    'state' => null,
                    'postal_code' => null,
                ],
                emails: [
                    'after@example.com',
                    'after2@example.com',
                ],
            ),
                    ],
        ];
    }
}
