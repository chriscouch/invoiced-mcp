<?php

namespace App\Tests\Integrations\QuickBooksOnline\Transformers;

use App\CashApplication\Enums\PaymentItemType;
use App\Core\I18n\ValueObjects\Money;
use App\Integrations\AccountingSync\Interfaces\AccountingRecordInterface;
use App\Integrations\AccountingSync\ValueObjects\AccountingCreditNote;
use App\Integrations\AccountingSync\ValueObjects\AccountingCustomer;
use App\Integrations\AccountingSync\ValueObjects\AccountingInvoice;
use App\Integrations\AccountingSync\ValueObjects\AccountingJsonRecord;
use App\Integrations\AccountingSync\ValueObjects\AccountingPayment;
use App\Integrations\AccountingSync\ValueObjects\AccountingPaymentItem;
use App\Integrations\Enums\IntegrationType;
use App\Integrations\QuickBooksOnline\Libs\QuickBooksApi;
use App\Integrations\QuickBooksOnline\Models\QuickBooksAccount;
use App\Integrations\QuickBooksOnline\Models\QuickBooksOnlineSyncProfile;
use App\Integrations\QuickBooksOnline\Transformers\QuickBooksPaymentTransformer;
use App\PaymentProcessing\Models\PaymentMethod;
use App\Tests\AppTestCase;

class QuickBooksPaymentTransformerTest extends AppTestCase
{
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        self::hasCompany();
    }

    public function getClient(): QuickBooksApi
    {
        $client = \Mockery::mock(QuickBooksApi::class);
        $client->shouldReceive('setAccount');

        $client->shouldReceive('get')
            ->withArgs(['Invoice', '456'])
            ->andReturn((object) ['DocNumber' => 'INV-00001']);

        $client->shouldReceive('get')
            ->withArgs(['Invoice', '67'])
            ->andReturn((object) ['DocNumber' => 'INV-00002']);

        $client->shouldReceive('get')
            ->withArgs(['Invoice', '81'])
            ->andReturn((object) ['DocNumber' => 'INV-00003']);

        $client->shouldReceive('get')
            ->withArgs(['Invoice', '90'])
            ->andReturn((object) ['DocNumber' => 'INV-00004']);

        $client->shouldReceive('get')
            ->withArgs(['CreditMemo', '68'])
            ->andReturn((object) ['DocNumber' => 'CN-00001']);

        $client->shouldReceive('get')
            ->withArgs(['CreditMemo', '89'])
            ->andReturn((object) ['DocNumber' => 'CN-00002']);

        $client->shouldReceive('get')
            ->withArgs(['CreditMemo', '92'])
            ->andReturn((object) ['DocNumber' => 'CN-00003']);

        return $client;
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
        "SyncToken": "0",
        "domain": "QBO",
        "DepositToAccountRef": {
          "value": "4"
        },
        "UnappliedAmt": 10.0,
        "TxnDate": "2021-01-18",
        "TotalAmt": 65.0,
        "ProcessPayment": false,
        "sparse": false,
        "Line": [
          {
            "Amount": 55.0,
            "LineEx": {
              "any": [
                {
                  "name": "{http://schema.intuit.com/finance/v3}NameValue",
                  "nil": false,
                  "value": {
                    "Name": "txnId",
                    "Value": "456"
                  },
                  "declaredType": "com.intuit.schema.finance.v3.NameValue",
                  "scope": "javax.xml.bind.JAXBElement$GlobalScope",
                  "globalScope": true,
                  "typeSubstituted": false
                },
                {
                  "name": "{http://schema.intuit.com/finance/v3}NameValue",
                  "nil": false,
                  "value": {
                    "Name": "txnOpenBalance",
                    "Value": "71.00"
                  },
                  "declaredType": "com.intuit.schema.finance.v3.NameValue",
                  "scope": "javax.xml.bind.JAXBElement$GlobalScope",
                  "globalScope": true,
                  "typeSubstituted": false
                },
                {
                  "name": "{http://schema.intuit.com/finance/v3}NameValue",
                  "nil": false,
                  "value": {
                    "Name": "txnReferenceNumber",
                    "Value": "1024"
                  },
                  "declaredType": "com.intuit.schema.finance.v3.NameValue",
                  "scope": "javax.xml.bind.JAXBElement$GlobalScope",
                  "globalScope": true,
                  "typeSubstituted": false
                }
              ]
            },
            "LinkedTxn": [
              {
                "TxnId": "456",
                "TxnType": "Invoice"
              }
            ]
          }
        ],
        "CustomerRef": {
          "name": "Test",
          "value": "1"
        },
        "Id": "163",
        "MetaData": {
          "CreateTime": "2021-01-18T15:08:12-08:00",
          "LastUpdatedTime": "2021-01-18T15:08:12-08:00"
        }
      }')),
                new AccountingPayment(
                    integration: IntegrationType::QuickBooksOnline,
                    accountingId: '163',
                    values: [
                        'method' => PaymentMethod::OTHER,
                        'date' => mktime(6, 0, 0, 1, 18, 2021),
                    ],
                    currency: 'usd',
                    customer: new AccountingCustomer(
                        integration: IntegrationType::QuickBooksOnline,
                        accountingId: '1',
                        values: [
                            'name' => 'Test',
                        ],
                    ),
                    appliedTo: [
                        new AccountingPaymentItem(
                            amount: new Money('usd', 5500),
                            invoice: new AccountingInvoice(
                                integration: IntegrationType::QuickBooksOnline,
                                accountingId: '456',
                                values: ['number' => 'INV-00001'],
                            )
                        ),
                    ]
                ),
            ],
            [
                new AccountingJsonRecord((object) json_decode('{
        "SyncToken": "0",
        "domain": "QBO",
        "DepositToAccountRef": {
          "value": "4"
        },
        "UnappliedAmt": 10.0,
        "TxnDate": "2021-01-18",
        "TotalAmt": 10.0,
        "ProcessPayment": false,
        "sparse": false,
        "Line": [
          {
            "Amount": 20.0,
            "LinkedTxn": [
              {
                "TxnId": "67",
                "TxnType": "Invoice"
              }
            ]
          },
          {
            "Amount": 10.0,
            "LinkedTxn": [
              {
                "TxnId": "68",
                "TxnType": "CreditMemo"
              }
            ]
          }
        ],
        "CustomerRef": {
          "name": "Test 2",
          "value": "2"
        },
        "Id": "164",
        "MetaData": {
          "CreateTime": "2021-01-18T15:08:12-08:00",
          "LastUpdatedTime": "2021-01-18T15:08:12-08:00"
        }
      }')),
                new AccountingPayment(
                    integration: IntegrationType::QuickBooksOnline,
                    accountingId: '164',
                    values: [
                        'method' => PaymentMethod::OTHER,
                        'date' => mktime(6, 0, 0, 1, 18, 2021),
                    ],
                    currency: 'usd',
                    customer: new AccountingCustomer(
                        integration: IntegrationType::QuickBooksOnline,
                        accountingId: '2',
                        values: [
                            'name' => 'Test 2',
                        ],
                    ),
                    appliedTo: [
                        new AccountingPaymentItem(
                            amount: new Money('usd', 1000),
                            type: PaymentItemType::CreditNote->value,
                            invoice: new AccountingInvoice(
                                integration: IntegrationType::QuickBooksOnline,
                                accountingId: '67',
                                values: ['number' => 'INV-00002'],
                            ),
                            creditNote: new AccountingCreditNote(
                                integration: IntegrationType::QuickBooksOnline,
                                accountingId: '68',
                                values: ['number' => 'CN-00001'],
                            ),
                            documentType: 'invoice'
                        ),
                        new AccountingPaymentItem(
                            amount: new Money('usd', 1000),
                            invoice: new AccountingInvoice(
                                integration: IntegrationType::QuickBooksOnline,
                                accountingId: '67',
                                values: ['number' => 'INV-00002'],
                            )
                        ),
                    ]
                ),
            ],
            [
                new AccountingJsonRecord((object) json_decode('{
        "SyncToken": "0",
        "domain": "QBO",
        "DepositToAccountRef": {
          "value": "4"
        },
        "TxnDate": "2021-01-18",
        "TotalAmt": 30.0,
        "ProcessPayment": false,
        "sparse": false,
        "Line": [
          {
            "Amount": 20.0,
            "LinkedTxn": [
              {
                "TxnId": "81",
                "TxnType": "Invoice"
              }
            ]
          },
          {
            "Amount": 10.0,
            "LinkedTxn": [
              {
                "TxnId": "82",
                "TxnType": "Expense"
              }
            ]
          },
          {
            "Amount": 25.0,
            "LinkedTxn": [
              {
                "TxnId": "89",
                "TxnType": "CreditMemo"
              }
            ]
          }
        ],
        "CustomerRef": {
          "name": "Test 2",
          "value": "2"
        },
        "Id": "166",
        "MetaData": {
          "CreateTime": "2021-01-18T15:08:12-08:00",
          "LastUpdatedTime": "2021-01-18T15:08:12-08:00"
        }
      }')),
                new AccountingPayment(
                    integration: IntegrationType::QuickBooksOnline,
                    accountingId: '166',
                    values: [
                        'method' => PaymentMethod::OTHER,
                        'date' => mktime(6, 0, 0, 1, 18, 2021),
                    ],
                    currency: 'usd',
                    customer: new AccountingCustomer(
                        integration: IntegrationType::QuickBooksOnline,
                        accountingId: '2',
                        values: [
                            'name' => 'Test 2',
                        ],
                    ),
                    appliedTo: [
                        new AccountingPaymentItem(
                            amount: new Money('usd', 2000),
                            type: PaymentItemType::CreditNote->value,
                            invoice: new AccountingInvoice(
                                integration: IntegrationType::QuickBooksOnline,
                                accountingId: '81',
                                values: ['number' => 'INV-00003'],
                            ),
                            creditNote: new AccountingCreditNote(
                                integration: IntegrationType::QuickBooksOnline,
                                accountingId: '89',
                                values: ['number' => 'CN-00002'],
                            ),
                            documentType: 'invoice'
                        ),
                    ]
                ),
            ],
            [
                new AccountingJsonRecord((object) json_decode('{
        "SyncToken": "0",
        "domain": "QBO",
        "DepositToAccountRef": {
          "value": "4"
        },
        "UnappliedAmt": 20.0,
        "TxnDate": "2021-01-18",
        "TotalAmt": 10.0,
        "ProcessPayment": false,
        "sparse": false,
        "Line": [
          {
            "Amount": 20.0,
            "LinkedTxn": [
              {
                "TxnId": "67",
                "TxnType": "Invoice"
              }
            ]
          },
          {
            "Amount": 20.0,
            "LinkedTxn": [
              {
                "TxnId": "90",
                "TxnType": "Invoice"
              }
            ]
          },
          {
            "Amount": 10.0,
            "LinkedTxn": [
              {
                "TxnId": "68",
                "TxnType": "CreditMemo"
              }
            ]
          },
          {
            "Amount": 10.0,
            "LinkedTxn": [
              {
                "TxnId": "92",
                "TxnType": "CreditMemo"
              }
            ]
          }
        ],
        "CustomerRef": {
          "name": "Test 2",
          "value": "2"
        },
        "Id": "201",
        "MetaData": {
          "CreateTime": "2021-01-18T15:08:12-08:00",
          "LastUpdatedTime": "2021-01-18T15:08:12-08:00"
        }
      }')),
                new AccountingPayment(
                    integration: IntegrationType::QuickBooksOnline,
                    accountingId: '201',
                    values: [
                        'method' => PaymentMethod::OTHER,
                        'date' => mktime(6, 0, 0, 1, 18, 2021),
                    ],
                    currency: 'usd',
                    customer: new AccountingCustomer(
                        integration: IntegrationType::QuickBooksOnline,
                        accountingId: '2',
                        values: [
                            'name' => 'Test 2',
                        ],
                    ),
                    appliedTo: [
                        new AccountingPaymentItem(
                            amount: new Money('usd', 1000),
                            type: PaymentItemType::CreditNote->value,
                            invoice: new AccountingInvoice(
                                integration: IntegrationType::QuickBooksOnline,
                                accountingId: '67',
                                values: ['number' => 'INV-00002'],
                            ),
                            creditNote: new AccountingCreditNote(
                                integration: IntegrationType::QuickBooksOnline,
                                accountingId: '68',
                                values: ['number' => 'CN-00001'],
                            ),
                            documentType: 'invoice'
                        ),
                        new AccountingPaymentItem(
                            amount: new Money('usd', 1000),
                            type: PaymentItemType::CreditNote->value,
                            invoice: new AccountingInvoice(
                                integration: IntegrationType::QuickBooksOnline,
                                accountingId: '67',
                                values: ['number' => 'INV-00002'],
                            ),
                            creditNote: new AccountingCreditNote(
                                integration: IntegrationType::QuickBooksOnline,
                                accountingId: '92',
                                values: ['number' => 'CN-00003'],
                            ),
                            documentType: 'invoice'
                        ),
                        new AccountingPaymentItem(
                            amount: new Money('usd', 2000),
                            invoice: new AccountingInvoice(
                                integration: IntegrationType::QuickBooksOnline,
                                accountingId: '90',
                                values: ['number' => 'INV-00004'],
                            ),
                        ),
                    ]
                ),
            ],
        ];
    }

    private function getTransformer(): QuickBooksPaymentTransformer
    {
        $client = $this->getClient();

        return new QuickBooksPaymentTransformer($client);
    }
}
