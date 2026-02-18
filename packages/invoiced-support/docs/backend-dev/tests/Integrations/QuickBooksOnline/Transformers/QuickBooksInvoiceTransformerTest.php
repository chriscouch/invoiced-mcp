<?php

namespace App\Tests\Integrations\QuickBooksOnline\Transformers;

use App\Integrations\AccountingSync\Interfaces\AccountingRecordInterface;
use App\Integrations\AccountingSync\ValueObjects\AccountingCustomer;
use App\Integrations\AccountingSync\ValueObjects\AccountingInvoice;
use App\Integrations\AccountingSync\ValueObjects\AccountingJsonRecord;
use App\Integrations\Enums\IntegrationType;
use App\Integrations\QuickBooksOnline\Libs\QuickBooksApi;
use App\Integrations\QuickBooksOnline\Models\QuickBooksAccount;
use App\Integrations\QuickBooksOnline\Models\QuickBooksOnlineSyncProfile;
use App\Integrations\QuickBooksOnline\Transformers\QuickBooksInvoiceTransformer;
use App\Tests\AppTestCase;
use Mockery;

class QuickBooksInvoiceTransformerTest extends AppTestCase
{
    private static string $jsonDIR;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        self::hasCompany();
        self::$jsonDIR = dirname(__DIR__).'/json/quickbooks_invoice_importer';
    }

    private function getTransformer(): QuickBooksInvoiceTransformer
    {
        $client = $this->buildApiClient();

        return new QuickBooksInvoiceTransformer($client);
    }

    private function buildApiClient(): QuickBooksApi
    {
        $bundle1 = (string) file_get_contents(self::$jsonDIR.'/quickbooks_invoice_importer_bundle_1.json');
        $bundle2 = (string) file_get_contents(self::$jsonDIR.'/quickbooks_invoice_importer_bundle_2.json');

        $qbo = Mockery::mock(QuickBooksApi::class);
        $qbo->shouldReceive('setAccount');

        $qbo->shouldReceive('getItem')
            ->withArgs([201])
            ->andReturn(json_decode($bundle1)->Item);

        $qbo->shouldReceive('getItem')
            ->withArgs([206])
            ->andReturn(json_decode($bundle2)->Item);

        $qbo->shouldReceive('getTerm')
            ->withArgs(['3'])
            ->andReturn((object) ['Name' => 'NET 30']);

        $qbo->shouldReceive('getTerm')
            ->withArgs(['6'])
            ->andReturn((object) ['Name' => 'NET 45']);

        return $qbo;
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
                new AccountingJsonRecord((object) json_decode('{
        "Id": "456",
        "SyncToken": "0",
        "MetaData": {
          "CreateTime": "2017-01-12T10:56:57-08:00",
          "LastUpdatedTime": "2017-01-12T10:56:57-08:00"
        },
        "DocNumber": "INV-1012",
        "TxnDate": "2017-01-12",
        "CurrencyRef": {
          "name": "United States Dollar",
          "value": "USD"
        },
        "DepartmentRef": {
          "name": "Test Location",
          "value": "1"
        },
        "Line": [
          {
            "Id": "1",
            "LineNum": 1,
            "Amount": 396.00,
            "DetailType": "SalesItemLineDetail",
            "SalesItemLineDetail": {
              "ItemRef": {
                "value": "SHIPPING_ITEM_ID"
              },
              "UnitPrice": 99.00,
              "Qty": 4,
              "TaxCodeRef": {
                "value": "NON"
              }
            }
          },
          {
            "Amount": 396.00,
            "DetailType": "SubTotalLineDetail",
            "SubTotalLineDetail": {}
          }
        ],
        "TxnDetails": {
          "TotalTax": 0
        },
        "CustomerRef": {
          "name": "Test",
          "value": "1"
        },
        "SalesTermRef": {
          "value": "3"
        },
        "DueDate": "2017-02-11",
        "TotalAmt": 396.00,
        "ApplyTaxAfterDiscount": false,
        "PrintStatus": "NotSet",
        "EmailStatus": "NotSet",
        "Balance": 393.49,
        "LinkedTxn": [
          {
            "TxnId": "3833",
            "TxnType": "Payment"
          }
        ],
        "Deposit": 0,
        "AllowIPNPayment": false,
        "AllowOnlinePayment": false,
        "AllowOnlineCreditCardPayment": false,
        "AllowOnlineACHPayment": false,
        "ShipAddr": {
          "Id": "25",
          "Line1": "5647 Cypress Hill Ave.",
          "City": "Middlefield",
          "CountrySubDivisionCode": "CA",
          "PostalCode": "94303",
          "Lat": "37.4238562",
          "Long": "-122.1141681"
        },
        "CustomField": [
          {
            "DefinitionId": "1",
            "Name": "Crew #",
            "Type": "StringType"
          },
          {
            "DefinitionId": "2",
            "Name": "SalesRep",
            "Type": "StringType"
          }
        ]
      }')),
                new AccountingInvoice(
                    integration: IntegrationType::QuickBooksOnline,
                    accountingId: '456',
                    customer: new AccountingCustomer(
                        integration: IntegrationType::QuickBooksOnline,
                        accountingId: '1',
                        values: [
                            'name' => 'Test',
                        ],
                    ),
                    values: [
                        'currency' => 'usd',
                        'number' => 'INV-1012',
                        'date' => mktime(6, 0, 0, 1, 12, 2017),
                        'due_date' => mktime(18, 0, 0, 2, 11, 2017),
                        'payment_terms' => 'NET 30',
                        'items' => [
                            [
                                'name' => 'Shipping',
                                'description' => '',
                                'quantity' => 4.0,
                                'unit_cost' => 99.0,
                                'metadata' => [],
                            ],
                        ],
                        'discount' => 0,
                        'ship_to' => [
                            'name' => 'Test',
                            'address1' => '5647 Cypress Hill Ave.',
                            'address2' => null,
                            'city' => 'Middlefield',
                            'state' => 'CA',
                            'postal_code' => '94303',
                        ],
                        'metadata' => [
                            'quickbooks_location' => 'Test Location',
                        ],
                        'tax' => 0,
                    ],
                ),
            ],
            [
                new AccountingJsonRecord((object) json_decode('{
        "Id": "165",
        "SyncToken": "3",
        "MetaData": {
          "CreateTime": "2016-07-12T11:32:57-07:00",
          "LastUpdatedTime": "2017-01-12T10:36:48-08:00"
        },
        "DocNumber": "INV-0264",
        "TxnDate": "2015-12-04",
        "CurrencyRef": {
          "name": "United States Dollar",
          "value": "USD"
        },
        "Line": [
          {
            "Id": "4",
            "LineNum": 1,
            "Amount": -55.00,
            "DetailType": "SalesItemLineDetail",
            "SalesItemLineDetail": {
              "ItemRef": {
                "name": "Some Item",
                "value": "45"
              },
              "UnitPrice": -54.9999384,
              "Qty": 1.0,
              "TaxCodeRef": {
                "value": "TAX"
              }
            }
          },
          {
            "Id": "5",
            "LineNum": 2,
            "Amount": 110.00,
            "DetailType": "SalesItemLineDetail",
            "SalesItemLineDetail": {
              "ItemRef": {
                "name": "Line 2",
                "value": "46"
              },
              "UnitPrice": 109.9998768,
              "Qty": 1.0,
              "TaxCodeRef": {
                "value": "TAX"
              }
            }
          },
          {
            "Id": "6",
            "LineNum": 3,
            "Amount": 1.00,
            "DetailType": "SalesItemLineDetail",
            "SalesItemLineDetail": {
              "ItemRef": {
                "name": "Line 3",
                "value": "46"
              },
              "TaxCodeRef": {
                "value": "TAX"
              }
            }
          },
          {
            "Id": "7",
            "LineNum": 4,
            "Amount": 1.00,
            "DetailType": "DescriptionOnly",
            "Description": "Line 4",
            "DescriptionLineDetail": {
              "ClassRef": {
                "name": "Louisville",
                "value": "300000000000781137"
              },
              "ServiceDate": "2015-12-04",
              "TaxCodeRef": {
                "value": "TAX"
              }
            }
          },
          {
            "Amount": 55.00,
            "DetailType": "SubTotalLineDetail",
            "SubTotalLineDetail": {}
          },
          {
            "Amount": 5.00,
            "DetailType": "DiscountLineDetail",
            "DiscountLineDetail": {
              "PercentBased": false,
              "DiscountAccountRef": {
                "name": "Discounts given",
                "value": "86"
              }
            }
          }
        ],
        "TxnTaxDetail": {
          "TxnTaxCodeRef": {
            "value": "3"
          },
          "TotalTax": 0,
          "TaxLine": [
            {
              "Amount": 0.00,
              "DetailType": "TaxLineDetail",
              "TaxLineDetail": {
                "TaxRateRef": {
                  "value": "2"
                },
                "PercentBased": true,
                "TaxPercent": 0,
                "NetAmountTaxable": 55.00
              }
            }
          ]
        },
        "CustomerRef": {
          "name": "Test 2",
          "value": "2"
        },
        "SalesTermRef": {
          "value": "6"
        },
        "DueDate": "2015-12-07",
        "TotalAmt": 85.00,
        "ApplyTaxAfterDiscount": false,
        "PrintStatus": "NotSet",
        "EmailStatus": "NotSet",
        "Balance": 85.00,
        "Deposit": 0,
        "AllowIPNPayment": false,
        "AllowOnlinePayment": false,
        "AllowOnlineCreditCardPayment": false,
        "AllowOnlineACHPayment": false,
        "BillAddr": {
          "Id": "2",
          "Country": "US"
        },
        "CustomField": [
          {
            "DefinitionId": "1",
            "Name": "Crew #",
            "Type": "StringType"
          },
          {
            "DefinitionId": "2",
            "Name": "SalesRep",
            "Type": "StringType"
          }
        ]
      }')),
                new AccountingInvoice(
                    integration: IntegrationType::QuickBooksOnline,
                    accountingId: '165',
                    customer: new AccountingCustomer(
                        integration: IntegrationType::QuickBooksOnline,
                        accountingId: '2',
                        values: [
                            'name' => 'Test 2',
                        ],
                    ),
                    values: [
                        'currency' => 'usd',
                        'number' => 'INV-0264',
                        'date' => mktime(6, 0, 0, 12, 4, 2015),
                        'due_date' => mktime(18, 0, 0, 12, 7, 2015),
                        'payment_terms' => 'NET 45',
                        'items' => [
                            [
                                'name' => 'Some Item',
                                'description' => '',
                                'quantity' => 1.0,
                                'unit_cost' => -54.9999384,
                                'metadata' => [],
                            ],
                            [
                                'name' => 'Line 2',
                                'description' => '',
                                'quantity' => 1.0,
                                'unit_cost' => 109.9998768,
                                'metadata' => [],
                            ],
                            [
                                'name' => 'Line 3',
                                'description' => '',
                                'quantity' => 1.0,
                                'unit_cost' => 1,
                                'metadata' => [],
                            ],
                            [
                                'name' => 'Line 4',
                                'quantity' => 1.0,
                                'unit_cost' => 1,
                                'metadata' => [
                                    'service_date' => '2015-12-04',
                                    'class' => 'Louisville',
                                ],
                            ],
                        ],
                        'discount' => 5,
                        'tax' => 0,
                    ],
                ),
            ],
        ];
    }

    public function testTransform2(): void
    {
        $transformer = $this->getTransformer();
        $account = new QuickBooksAccount();
        $syncProfile = new QuickBooksOnlineSyncProfile();
        $syncProfile->read_invoices_as_drafts = false;
        $transformer->initialize($account, $syncProfile);
        $transaction = (object) [
            'Id' => 1,
            'CustomerRef' => (object) [
                'value' => 'test',
            ],
            'Line' => [],
            'CustomField' => [],
            'TotalAmt' => 100,
        ];
        $input = new AccountingJsonRecord($transaction);
        $res = $transformer->transform($input);
        $this->assertInstanceOf(AccountingInvoice::class, $res);
        $this->assertEquals([], $res->delivery);
        $this->assertNull($res->customer?->emails);

        $transaction->BillEmail = (object) [
            'Address' => 'test@test.com',
        ];

        $input = new AccountingJsonRecord($transaction);
        $res = $transformer->transform($input);
        $this->assertInstanceOf(AccountingInvoice::class, $res);
        $this->assertEquals([
            'emails' => 'test@test.com',
        ], $res->delivery);
        $this->assertEquals(['test@test.com'], $res->customer?->emails);
    }
}
