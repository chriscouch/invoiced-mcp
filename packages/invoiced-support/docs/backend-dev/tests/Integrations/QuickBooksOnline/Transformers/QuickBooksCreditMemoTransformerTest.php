<?php

namespace App\Tests\Integrations\QuickBooksOnline\Transformers;

use App\Integrations\AccountingSync\Interfaces\AccountingRecordInterface;
use App\Integrations\AccountingSync\ValueObjects\AccountingCreditNote;
use App\Integrations\AccountingSync\ValueObjects\AccountingCustomer;
use App\Integrations\AccountingSync\ValueObjects\AccountingJsonRecord;
use App\Integrations\Enums\IntegrationType;
use App\Integrations\QuickBooksOnline\Libs\QuickBooksApi;
use App\Integrations\QuickBooksOnline\Models\QuickBooksAccount;
use App\Integrations\QuickBooksOnline\Models\QuickBooksOnlineSyncProfile;
use App\Integrations\QuickBooksOnline\Transformers\QuickBooksCreditMemoTransformer;
use App\Tests\AppTestCase;
use Mockery;

class QuickBooksCreditMemoTransformerTest extends AppTestCase
{
    private static string $jsonDIR;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        self::hasCompany();
        self::$jsonDIR = dirname(__DIR__).'/json';
    }

    private function getClient(): QuickBooksApi
    {
        // Line items data is the same in the credit memo data as it is in the invoice data.
        // qbo_credit_memo_query_1 has the same lines as quickbooks_invoice_importer_result_1 etc.
        // In QBO, the line objects do not differ between invoices and credit memos
        $bundle1 = (string) file_get_contents(self::$jsonDIR.'/quickbooks_invoice_importer/quickbooks_invoice_importer_bundle_1.json');
        $bundle2 = (string) file_get_contents(self::$jsonDIR.'/quickbooks_invoice_importer/quickbooks_invoice_importer_bundle_2.json');

        $client = Mockery::mock(QuickBooksApi::class);
        $client->shouldReceive('setAccount');
        $client->shouldReceive('getItem')
            ->withArgs([201])
            ->andReturn(json_decode($bundle1)->Item);
        $client->shouldReceive('getItem')
            ->withArgs([206])
            ->andReturn(json_decode($bundle2)->Item);

        return $client;
    }

    private function getTransformer(): QuickBooksCreditMemoTransformer
    {
        $client = $this->getClient();

        return new QuickBooksCreditMemoTransformer($client);
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
        "Id": "73",
        "DocNumber": "CN-1026",
        "Balance": 0,
        "TxnDate": "2021-01-08",
        "TotalAmt": 396.0,
        "CustomerRef": {
          "name": "Test",
          "value": "1"
        },
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
            "Description": "Details...",
            "Amount": 396.00,
            "DetailType": "SalesItemLineDetail",
            "SalesItemLineDetail": {
              "ItemRef": {
                "name": "Marketing guides",
                "value": "42"
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
        "CustomField": [
          {
            "DefinitionId": "1",
            "Type": "StringType",
            "Name": "Crew #"
          }
        ],
        "TxnTaxDetail": {
          "TotalTax": 0
        }
      }')),
            new AccountingCreditNote(
                integration: IntegrationType::QuickBooksOnline,
                accountingId: '73',
                customer: new AccountingCustomer(
                    integration: IntegrationType::QuickBooksOnline,
                    accountingId: '1',
                    values: [
                        'name' => 'Test',
                    ],
                ),
                values: [
                    'currency' => 'usd',
                    'number' => 'CN-1026',
                    'date' => mktime(6, 0, 0, 1, 8, 2021),
                    'items' => [
                        [
                            'name' => 'Marketing guides',
                            'description' => 'Details...',
                            'quantity' => 4.0,
                            'unit_cost' => 99.0,
                            'metadata' => [],
                        ],
                    ],
                    'discount' => 0,
                    'metadata' => [
                        'quickbooks_location' => 'Test Location',
                    ],
                    'tax' => 0,
                ],
            ),
            ],
            [
                new AccountingJsonRecord((object) json_decode('{
        "Id": "158",
        "DocNumber": "CN-1039",
        "Balance": 52.0,
        "TxnDate": "2021-01-08",
        "TotalAmt": 52.0,
        "CustomerMemo": {
          "value": "Updated customer memo."
        },
        "CustomerRef": {
          "name": "Test",
          "value": "1"
        },
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
        "CustomField": [
          {
            "DefinitionId": "1",
            "Type": "StringType",
            "Name": "Crew #"
          }
        ],
        "TxnTaxDetail": {
          "TotalTax": 0
        }
      }')),
                new AccountingCreditNote(
                    integration: IntegrationType::QuickBooksOnline,
                    accountingId: '158',
                    customer: new AccountingCustomer(
                        integration: IntegrationType::QuickBooksOnline,
                        accountingId: '1',
                        values: [
                            'name' => 'Test',
                        ],
                    ),
                    values: [
                        'currency' => 'usd',
                        'number' => 'CN-1039',
                        'date' => mktime(6, 0, 0, 1, 8, 2021),
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
                        'discount' => 5.0,
                        'notes' => 'Updated customer memo.',
                        'tax' => 0,
                    ],
                ),
            ],
            [
                new AccountingJsonRecord((object) json_decode('{
        "Id": "201",
        "DocNumber": "CN-1040",
        "TxnDate": "2021-01-08",
        "TotalAmt": 1310.00,
        "CustomerRef": {
          "name": "Test",
          "value": "1"
        },
        "Line": [
          {
            "Id": "3",
            "LineNum": 1,
            "Amount": 1250.00,
            "DetailType": "SalesItemLineDetail",
            "SalesItemLineDetail": {
              "ItemRef": {
                "name": "Development work - develper onsite per day",
                "value": "22"
              },
              "UnitPrice": 1250.00,
              "Qty": 1,
              "TaxCodeRef": {
                "value": "TAX"
              }
            }
          },
          {
            "Id": "4",
            "LineNum": 2,
            "Amount": 100.00,
            "DetailType": "SalesItemLineDetail",
            "SalesItemLineDetail": {
              "ItemRef": {
                "name": "Missing qty",
                "value": 23
              },
              "UnitPrice": 100.00,
              "Qty": null,
              "TaxCodeRef": {
                "value": "TAX"
              }
            }
          },
          {
            "Amount": 1250.00,
            "DetailType": "SubTotalLineDetail",
            "SubTotalLineDetail": {}
          },
          {
            "Id": "1",
            "LineNum": 1,
            "Amount": 0,
            "DetailType": "GroupLineDetail",
            "GroupLineDetail": {
              "GroupItemRef": {
                "name": "Bundle",
                "value": "201"
              },
              "Quantity": 3,
              "Line": [
                {
                  "Id": "2",
                  "LineNum": 2,
                  "Amount": 57.00,
                  "DetailType": "SalesItemLineDetail",
                  "SalesItemLineDetail": {
                    "ItemRef": {
                      "name": "Amazon Echo",
                      "value": "58"
                    },
                    "UnitPrice": 19.00,
                    "Qty": 3,
                    "ItemAccountRef": {
                      "name": "Invoiced Account",
                      "value": "91"
                    },
                    "TaxCodeRef": {
                      "value": "NON"
                    }
                  }
                },
                {
                  "Id": "3",
                  "LineNum": 3,
                  "Amount": 15.00,
                  "DetailType": "SalesItemLineDetail",
                  "SalesItemLineDetail": {
                    "ItemRef": {
                      "name": "blah",
                      "value": "44"
                    },
                    "UnitPrice": 5.00,
                    "Qty": 3,
                    "ItemAccountRef": {
                      "name": "Invoiced Account",
                      "value": "91"
                    },
                    "TaxCodeRef": {
                      "value": "NON"
                    }
                  }
                },
                {
                  "Id": "4",
                  "LineNum": 4,
                  "Amount": 90.00,
                  "DetailType": "SalesItemLineDetail",
                  "SalesItemLineDetail": {
                    "ItemRef": {
                      "name": "blahasdf",
                      "value": "45"
                    },
                    "UnitPrice": 30.00,
                    "Qty": 3,
                    "ItemAccountRef": {
                      "name": "Invoiced Account",
                      "value": "91"
                    },
                    "TaxCodeRef": {
                      "value": "NON"
                    }
                  }
                }
              ]
            }
          },
          {
            "Id": "6",
            "LineNum": 6,
            "Amount": 30.00,
            "DetailType": "GroupLineDetail",
            "GroupLineDetail": {
              "GroupItemRef": {
                "name": "Single Line Item Bundle",
                "value": "206"
              },
              "Quantity": 2,
              "Line": [
                {
                  "Id": "7",
                  "LineNum": 7,
                  "Amount": 8.00,
                  "DetailType": "SalesItemLineDetail",
                  "SalesItemLineDetail": {
                    "ClassRef": {
                      "name": "Test",
                      "value": "223"
                    },
                    "ItemRef": {
                      "name": "Amazon Echo",
                      "value": "58"
                    },
                    "UnitPrice": 4.00,
                    "Qty": 2,
                    "ItemAccountRef": {
                      "name": "Invoiced Account",
                      "value": "91"
                    },
                    "TaxCodeRef": {
                      "value": "NON"
                    }
                  }
                },
                {
                  "Id": "8",
                  "LineNum": 8,
                  "Amount": 10.00,
                  "DetailType": "SalesItemLineDetail",
                  "SalesItemLineDetail": {
                    "ItemRef": {
                      "name": "blah",
                      "value": "44"
                    },
                    "UnitPrice": 5.00,
                    "Qty": 2,
                    "ItemAccountRef": {
                      "name": "Invoiced Account",
                      "value": "91"
                    },
                    "TaxCodeRef": {
                      "value": "NON"
                    }
                  }
                },
                {
                  "Id": "9",
                  "LineNum": 9,
                  "Amount": 12.00,
                  "DetailType": "SalesItemLineDetail",
                  "SalesItemLineDetail": {
                    "ItemRef": {
                      "name": "blahasdf",
                      "value": "45"
                    },
                    "UnitPrice": 6.00,
                    "Qty": 2,
                    "ItemAccountRef": {
                      "name": "Invoiced Account",
                      "value": "91"
                    },
                    "TaxCodeRef": {
                      "value": "NON"
                    }
                  }
                }
              ]
            }
          }
        ],
        "CustomField": [
          {
            "DefinitionId": "1",
            "Name": "Crew #",
            "Type": "StringType",
            "StringValue": "3"
          }
        ]
      }')),
                new AccountingCreditNote(
                    integration: IntegrationType::QuickBooksOnline,
                    accountingId: '201',
                    customer: new AccountingCustomer(
                        integration: IntegrationType::QuickBooksOnline,
                        accountingId: '1',
                        values: [
                            'name' => 'Test',
                        ],
                    ),
                    values: [
                        'number' => 'CN-1040',
                        'date' => mktime(6, 0, 0, 1, 8, 2021),
                        'items' => [
                            [
                                'name' => 'Development work - develper onsite per day',
                                'description' => '',
                                'quantity' => 1.0,
                                'unit_cost' => 1250.0,
                                'metadata' => [],
                            ],
                            [
                                'name' => 'Missing qty',
                                'description' => '',
                                'quantity' => 1.0,
                                'unit_cost' => 100.0,
                                'metadata' => [],
                            ],
                            [
                                'name' => 'Amazon Echo',
                                'description' => '',
                                'quantity' => 3.0,
                                'unit_cost' => 19.0,
                                'metadata' => [],
                            ],
                            [
                                'name' => 'blah',
                                'description' => '',
                                'quantity' => 3.0,
                                'unit_cost' => 5.0,
                                'metadata' => [],
                            ],
                            [
                                'name' => 'blahasdf',
                                'description' => '',
                                'quantity' => 3.0,
                                'unit_cost' => 30.0,
                                'metadata' => [],
                            ],
                            [
                                'name' => 'Single Line Item Bundle',
                                'description' => '',
                                'quantity' => 2.0,
                                'metadata' => [
                                    'class' => 'Test',
                                ],
                                'unit_cost' => 15.0,
                            ],
                        ],
                        'discount' => 0,
                        'metadata' => [
                            'quickbooks_crew' => '3',
                        ],
                        'tax' => 0,
                    ]
                ),
            ],
        ];
    }
}
