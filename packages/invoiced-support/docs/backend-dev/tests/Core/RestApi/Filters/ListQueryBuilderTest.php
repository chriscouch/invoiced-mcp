<?php

namespace App\Tests\Core\RestApi\Filters;

use App\AccountsPayable\ListQueryBuilder\VendorListQueryBuilder;
use App\AccountsPayable\Models\Vendor;
use App\AccountsReceivable\ListQueryBuilders\CreditNoteListQueryBuilder;
use App\AccountsReceivable\ListQueryBuilders\CustomerListQueryBuilder;
use App\AccountsReceivable\ListQueryBuilders\EstimateListQueryBuilder;
use App\AccountsReceivable\ListQueryBuilders\InvoiceListQueryBuilder;
use App\AccountsReceivable\Models\CreditNote;
use App\AccountsReceivable\Models\Customer;
use App\AccountsReceivable\Models\Estimate;
use App\AccountsReceivable\Models\Invoice;
use App\AccountsReceivable\Models\InvoiceDelivery;
use App\Automations\Models\AutomationWorkflow;
use App\Automations\Models\AutomationWorkflowEnrollment;
use App\CashApplication\ListQueryBuilders\PaymentListQueryBuilder;
use App\CashApplication\ListQueryBuilders\TransactionListQueryBuilder;
use App\CashApplication\Models\Payment;
use App\CashApplication\Models\Transaction;
use App\AccountsReceivable\Models\Item;
use App\Chasing\Models\PromiseToPay;
use App\Core\ListQueryBuilders\ListQueryBuilderFactory;
use App\Core\RestApi\Exception\InvalidRequest;
use App\Core\Utils\Enums\ObjectType;
use App\PaymentPlans\ListQueryBuilders\PaymentPlanListQueryBuilder;
use App\PaymentPlans\Models\PaymentPlan;
use App\PaymentPlans\Models\PaymentPlanInstallment;
use App\SubscriptionBilling\ListQueryBuilders\SubscriptionListQueryBuilder;
use App\SubscriptionBilling\Models\Subscription;
use App\Tests\AppTestCase;

class ListQueryBuilderTest extends AppTestCase
{
    private static AutomationWorkflow $workflow;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        self::hasCompany();
        self::$workflow = new AutomationWorkflow();
        self::$workflow->name = 'Event Trigger '.uniqid();
        self::$workflow->object_type = ObjectType::Customer;
        self::$workflow->enabled = true;
        self::$workflow->saveOrFail();
    }

    /**
     * @dataProvider sortProvider
     */
    public function testDefaultSort(string $class, string $sort, array $expected): void
    {
        /** @var ListQueryBuilderFactory $factory */
        $factory = self::getService('test.list_query_builder_factory');
        $builder = $factory->get($class, self::$company, []);
        $builder->initialize();
        $builder->setOptions(['sort' => $sort]);
        $query = $builder->getBuildQuery();
        $this->assertEquals($expected['sort'], $query->getSort());
        $this->assertEquals($expected['join'], $query->getJoins());
    }

    /**
     * @dataProvider sortProvider
     */
    public function testSort(string $class, string $sort, array $expected): void
    {
        /** @var ListQueryBuilderFactory $factory */
        $factory = self::getService('test.list_query_builder_factory');
        $builder = $factory->get($class, self::$company, [
            'sort' => $sort,
        ]);
        $builder->initialize();
        $builder->setSort();
        $query = $builder->getBuildQuery();
        $this->assertEquals($expected['sort'], $query->getSort());
        $this->assertEquals($expected['join'], $query->getJoins());
    }

    public function testNonValidFilter(): void
    {
        $this->expectException(InvalidRequest::class);
        /** @var ListQueryBuilderFactory $factory */
        $factory = self::getService('test.list_query_builder_factory');
        $builder = $factory->get(CreditNote::class, self::$company, [
            'filter' => [
                'not_valid' => 'not valid',
            ],
        ]);
        $builder->initialize();
    }

    public function testSpecialCaseOnPaymentPlan(): void
    {
        self::hasCustomer();
        self::hasInvoice();
        /** @var ListQueryBuilderFactory $factory */
        $factory = self::getService('test.list_query_builder_factory');
        $builder = $factory->get(PaymentPlan::class, self::$company, [
            'options' => fn () => [
                'filter' => [],
                'advanced_filter' => null,
            ],
        ]);

        $builder->initialize();

        $this->assertInstanceOf(PaymentPlanListQueryBuilder::class, $builder);
        $query = $builder->getBuildQuery();
        $this->assertEquals([
            'tenant_id' => self::$company->id,
            'id' => -1,
        ], $query->getWhere());
        $this->assertEquals([], $query->getJoins());
        $this->assertEquals(1000, $query->getLimit());
        $this->assertEquals([], $query->getSort());
        $this->assertEquals([], $query->getWith());
        $this->assertEquals(PaymentPlan::class, $query->getModel()::class);

        $paymentPlan = new PaymentPlan();
        $installment1 = new PaymentPlanInstallment();
        $installment1->date = strtotime('+1 day');
        $installment1->amount = 100;
        $paymentPlan->installments = [
            $installment1,
        ];

        self::$invoice->attachPaymentPlan($paymentPlan, false, true);
        $builder = $factory->get(PaymentPlan::class, self::$company, [
            'options' => fn () => [
                'filter' => [],
                'advanced_filter' => null,
            ],
        ]);
        $builder->initialize();
        $query = $builder->getBuildQuery();
        $this->assertEquals([
            'tenant_id' => self::$company->id,
            'invoice_id' => [self::$invoice->id],
        ], $query->getWhere());

        $this->assertEquals(self::$invoice->id, $query->execute()[0]['invoice_id']);
    }

    /**
     * @dataProvider optionsProvider
     */
    public function testInitialize(string $type, callable $options, array $expected): void
    {
        /** @var ListQueryBuilderFactory $factory */
        $factory = self::getService('test.list_query_builder_factory');
        $builder = $factory->get($type, self::$company, $options());

        $builder->initialize();

        $this->assertInstanceOf($expected['class'], $builder);
        $query = $builder->getBuildQuery();
        $this->assertEquals($expected['where'](), $query->getWhere());
        $this->assertEquals($expected['joins'], $query->getJoins());
        $this->assertEquals(1000, $query->getLimit());
        $this->assertEquals($expected['sort'], $query->getSort());
        $this->assertEquals($expected['with'], $query->getWith());
        $this->assertEquals($expected['model'], $query->getModel()::class);
    }

    public function optionsProvider(): array
    {
        return [
            'credit note no filter' => [
                'type' => CreditNote::class,
                'options' => fn () => [
                    'filter' => [],
                    'advanced_filter' => null,
                    'total' => null,
                ],
                'expected' => [
                    'class' => CreditNoteListQueryBuilder::class,
                    'where' => fn () => [
                        'tenant_id' => self::$company->id,
                    ],
                    'joins' => [],
                    'with' => ['customer'],
                    'model' => CreditNote::class,
                    'sort' => [['id', 'asc']],
                ],
            ],
            'credit note with metadata' => [
                'type' => CreditNote::class,
                'options' => fn () => [
                    'filter' => [
                        'amount_applied_to_invoice' => 100,
                        'currency' => 'usd',
                        'customer' => 'test',
                    ],
                    'advanced_filter' => '[{"field":"number","operator":"=","value":"afsfsdf"}]',

                    'total' => json_encode([
                        '=',
                        100,
                    ]),
                    'metadata' => [
                        'not valid',
                        'not valid;' => 'still not valid',
                        'valid' => 'value',
                        'valid2' => 'value2',
                    ],
                    'start_date' => 1234,
                    'end_date' => 5678,
                    'automation' => self::$workflow->id,
                ],
                'expected' => [
                    'class' => CreditNoteListQueryBuilder::class,
                    'where' => fn () => [
                        'tenant_id' => self::$company->id,
                        ['amount_applied_to_invoice', 100, '='],
                        ['currency', 'usd', '='],
                        ['customer', 'test', '='],
                        ['number', 'afsfsdf', '='],
                        ['date', 1234, '>='],
                        ['date', 5678, '<='],
                        ['total', 100, '='],
                        'EXISTS (SELECT 1 FROM Metadata WHERE `tenant_id`='.self::$company->id." AND `object_type`=\"credit_note\" AND object_id=CreditNotes.id AND `key`='valid' AND `value` = 'value')",
                        'EXISTS (SELECT 1 FROM Metadata WHERE `tenant_id`='.self::$company->id." AND `object_type`=\"credit_note\" AND object_id=CreditNotes.id AND `key`='valid2' AND `value` = 'value2')",
                        'AutomationWorkflowEnrollments.workflow_id' => self::$workflow->id,
                    ],
                    'joins' => [
                        [
                            AutomationWorkflowEnrollment::class,
                            'id',
                            'object_id',
                            'JOIN',
                        ],
                    ],
                    'with' => ['customer'],
                    'model' => CreditNote::class,
                    'sort' => [['id', 'asc']],
                ],
            ],
            'estimate no filter' => [
                'type' => Estimate::class,
                'options' => fn () => [
                    'filter' => [],
                    'advanced_filter' => null,
                    'sort' => 'id ASC',
                ],
                'expected' => [
                    'class' => EstimateListQueryBuilder::class,
                    'where' => fn () => [
                        'tenant_id' => self::$company->id,
                    ],
                    'joins' => [],
                    'with' => ['customer'],
                    'model' => Estimate::class,
                    'sort' => [['id', 'asc']],
                ],
            ],
            'estimate with metadata' => [
                'type' => Estimate::class,
                'options' => fn () => [
                    'filter' => [
                        'deposit' => 100,
                        'currency' => 'usd',
                        'customer' => 'test',
                    ],
                    'advanced_filter' => '[{"field":"number","operator":"=","value":"afsfsdf"}]',

                    'total' => json_encode([
                        '=',
                        100,
                    ]),
                    'metadata' => [
                        'not valid',
                        'not valid;' => 'still not valid',
                        'valid' => 'value',
                        'valid2' => 'value2',
                    ],
                    'start_date' => 1234,
                    'end_date' => 5678,
                    'automation' => self::$workflow->id,
                ],
                'expected' => [
                    'class' => EstimateListQueryBuilder::class,
                    'where' => fn () => [
                        'tenant_id' => self::$company->id,
                        ['deposit', 100, '='],
                        ['currency', 'usd', '='],
                        ['customer', 'test', '='],
                        ['number', 'afsfsdf', '='],
                        ['date', 1234, '>='],
                        ['date', 5678, '<='],
                        ['total', 100, '='],
                        'EXISTS (SELECT 1 FROM Metadata WHERE `tenant_id`='.self::$company->id." AND `object_type`=\"estimate\" AND object_id=Estimates.id AND `key`='valid' AND `value` = 'value')",
                        'EXISTS (SELECT 1 FROM Metadata WHERE `tenant_id`='.self::$company->id." AND `object_type`=\"estimate\" AND object_id=Estimates.id AND `key`='valid2' AND `value` = 'value2')",
                        'AutomationWorkflowEnrollments.workflow_id' => self::$workflow->id,
                    ],
                    'joins' => [
                        [
                            AutomationWorkflowEnrollment::class,
                            'id',
                            'object_id',
                            'JOIN',
                        ],
                    ],
                    'with' => ['customer'],
                    'model' => Estimate::class,
                    'sort' => [['id', 'asc']],
                ],
            ],
            'invoice no filter' => [
                'type' => Invoice::class,
                'options' => fn () => [
                    'filter' => [],
                    'advanced_filter' => null,
                    'total' => null,
                ],
                'expected' => [
                    'class' => InvoiceListQueryBuilder::class,
                    'where' => fn () => [
                        'tenant_id' => self::$company->id,
                    ],
                    'joins' => [],
                    'with' => ['customer'],
                    'model' => Invoice::class,
                    'sort' => [['id', 'asc']],
                ],
            ],
            'invoice with metadata' => [
                'type' => Invoice::class,
                'options' => fn () => [
                    'filter' => [
                        'autopay' => 1,
                        'currency' => 'usd',
                        'customer' => 'test',
                    ],
                    'advanced_filter' => '[{"field":"number","operator":"=","value":"afsfsdf"}]',

                    'total' => json_encode([
                        '=',
                        100,
                    ]),
                    'metadata' => [
                        'not valid',
                        'not valid;' => 'still not valid',
                        'valid' => 'value',
                        'valid2' => 'value2',
                    ],
                    'start_date' => 1234,
                    'end_date' => 5678,
                    'payment_plan' => true,
                    'payment_attempted' => 0,
                    'broken_promises' => 1,
                    'customer_payment_info' => 0,
                    'balance' => json_encode([
                        '=',
                        100,
                    ]),
                    'tags' => 100,
                    'chasing' => 0,
                    'cadence' => 10,
                    'automation' => self::$workflow->id,
                ],
                'expected' => [
                    'class' => InvoiceListQueryBuilder::class,
                    'where' => fn () => [
                        'tenant_id' => self::$company->id,
                        ['autopay', 1, '='],
                        ['currency', 'usd', '='],
                        ['customer', 'test', '='],
                        ['number', 'afsfsdf', '='],
                        ['date', 1234, '>='],
                        ['date', 5678, '<='],
                        ['total', 100, '='],
                        'paid' => false,
                        'closed' => false,
                        'draft' => false,
                        'voided' => false,
                        'InvoiceDeliveries.cadence_id' => 10,
                        ['payment_plan_id', null, '<>'],
                        ['balance', 100, '='],
                        'EXISTS (SELECT 1 FROM Metadata WHERE `tenant_id`='.self::$company->id." AND `object_type`=\"invoice\" AND object_id=Invoices.id AND `key`='valid' AND `value` = 'value')",
                        'EXISTS (SELECT 1 FROM Metadata WHERE `tenant_id`='.self::$company->id." AND `object_type`=\"invoice\" AND object_id=Invoices.id AND `key`='valid2' AND `value` = 'value2')",
                        "(SELECT COUNT(*) FROM InvoiceTags WHERE invoice_id=Invoices.id AND tag IN ('100')) > 0",
                        ['ExpectedPaymentDates.date', time(), '<'],
                        'AutomationWorkflowEnrollments.workflow_id' => self::$workflow->id,
                    ],
                    'joins' => [
                        [
                            PromiseToPay::class,
                            'id',
                            'invoice_id',
                            'JOIN',
                        ],
                        [
                            InvoiceDelivery::class,
                            'id',
                            'InvoiceDeliveries.invoice_id',
                            'JOIN',
                        ],
                        [
                            AutomationWorkflowEnrollment::class,
                            'id',
                            'object_id',
                            'JOIN',
                        ],
                    ],
                    'with' => ['customer'],
                    'model' => Invoice::class,
                    'sort' => [['id', 'asc']],
                ],
            ],
            'invoice special' => [
                'type' => Invoice::class,
                'options' => fn () => [
                    'payment_plan' => 'needs_approval',
                    'payment_attempted' => 1,
                    'chasing' => 1,
                    'cadence' => 10,
                ],
                'expected' => [
                    'class' => InvoiceListQueryBuilder::class,
                    'where' => fn () => [
                        'tenant_id' => self::$company->id,
                        [
                            'attempt_count',
                            0,
                            '>',
                        ],
                        'PaymentPlans.status' => 'pending_signup',
                        'InvoiceDeliveries.cadence_id' => 10,
                    ],
                    'joins' => [
                        [
                            PaymentPlan::class,
                            'payment_plan_id',
                            'PaymentPlans.id',
                            'JOIN',
                        ],
                        [
                            InvoiceDelivery::class,
                            'id',
                            'InvoiceDeliveries.invoice_id',
                            'JOIN',
                        ],
                    ],
                    'with' => ['customer'],
                    'model' => Invoice::class,
                    'sort' => [['id', 'asc']],
                ],
            ],
            'invoice special 2' => [
                'type' => Invoice::class,
                'options' => fn () => [
                    'payment_plan' => '0',
                ],
                'expected' => [
                    'class' => InvoiceListQueryBuilder::class,
                    'where' => fn () => [
                        'tenant_id' => self::$company->id,
                        ['payment_plan_id', null, '='],
                    ],
                    'joins' => [],
                    'with' => ['customer'],
                    'model' => Invoice::class,
                    'sort' => [['id', 'asc']],
                ],
            ],
            'customer no filter' => [
                'type' => Customer::class,
                'options' => fn () => [
                    'filter' => [],
                    'advanced_filter' => null,
                    'total' => null,
                ],
                'expected' => [
                    'class' => CustomerListQueryBuilder::class,
                    'where' => fn () => [
                        'tenant_id' => self::$company->id,
                    ],
                    'joins' => [],
                    'with' => ['chasing_cadence_id', 'next_chase_step_id'],
                    'model' => Customer::class,
                    'sort' => [['id', 'asc']],
                ],
            ],
            'customer with metadata' => [
                'type' => Customer::class,
                'options' => fn () => [
                    'filter' => [
                        'attention_to' => '100',
                        'currency' => 'usd',
                    ],
                    'advanced_filter' => '[{"field":"number","operator":"=","value":"afsfsdf"}]',
                    'total' => json_encode([
                        '=',
                        100,
                    ]),
                    'metadata' => [
                        'not valid',
                        'not valid;' => 'still not valid',
                        'valid' => 'value',
                        'valid2' => 'value2',
                    ],
                    'payment_source' => false,
                    'open_balance' => false,
                    'balance' => false,
                    'owner' => 0,
                    'automation' => self::$workflow->id,
                ],
                'expected' => [
                    'class' => CustomerListQueryBuilder::class,
                    'where' => fn () => [
                        'tenant_id' => self::$company->id,
                        ['attention_to', '100', '='],
                        ['currency', 'usd', '='],
                        ['number', 'afsfsdf', '='],
                        ['default_source_id', null, '='],
                        ['credit_balance', 0, '='],
                        'EXISTS (SELECT 1 FROM Metadata WHERE `tenant_id`='.self::$company->id." AND `object_type`=\"customer\" AND object_id=Customers.id AND `key`='valid' AND `value` = 'value')",
                        'EXISTS (SELECT 1 FROM Metadata WHERE `tenant_id`='.self::$company->id." AND `object_type`=\"customer\" AND object_id=Customers.id AND `key`='valid2' AND `value` = 'value2')",
                        'AutomationWorkflowEnrollments.workflow_id' => self::$workflow->id,
                        'NOT EXISTS (SELECT 1 FROM Invoices WHERE customer=Customers.id AND draft=0 AND closed=0 AND voided=0 and paid=0 AND date <= UNIX_TIMESTAMP())',
                    ],
                    'joins' => [
                        [
                            AutomationWorkflowEnrollment::class,
                            'id',
                            'object_id',
                            'JOIN',
                        ],
                    ],
                    'with' => ['chasing_cadence_id', 'next_chase_step_id'],
                    'model' => Customer::class,
                    'sort' => [['id', 'asc']],
                ],
            ],

            'customer special' => [
                'type' => Customer::class,
                'options' => fn () => [
                    'payment_source' => 1,
                    'open_balance' => 1,
                    'balance' => 1,
                    'owner' => 14,
                ],
                'expected' => [
                    'class' => CustomerListQueryBuilder::class,
                    'where' => fn () => [
                        'tenant_id' => self::$company->id,
                        ['owner_id', 14, '='],
                        ['default_source_id', null, '<>'],
                        ['credit_balance', 0, '>'],
                        'EXISTS (SELECT 1 FROM Invoices WHERE customer=Customers.id AND draft=0 AND closed=0 AND voided=0 and paid=0 AND date <= UNIX_TIMESTAMP())',
                    ],
                    'joins' => [],
                    'with' => ['chasing_cadence_id', 'next_chase_step_id'],
                    'model' => Customer::class,
                    'sort' => [['id', 'asc']],
                ],
            ],
            'Payment no filter' => [
                'type' => Payment::class,
                'options' => fn () => [
                    'filter' => [],
                    'advanced_filter' => null,
                ],
                'expected' => [
                    'class' => PaymentListQueryBuilder::class,
                    'where' => fn () => [
                        'tenant_id' => self::$company->id,
                    ],
                    'joins' => [],
                    'with' => [],
                    'model' => Payment::class,
                    'sort' => [['id', 'asc']],
                ],
            ],
            'Payment with metadata' => [
                'type' => Payment::class,
                'options' => fn () => [
                    'filter' => [
                        'balance' => 100,
                        'currency' => 'usd',
                    ],
                    'advanced_filter' => '[{"field":"method","operator":"=","value":"afsfsdf"}]',
                    'amount' => json_encode(['>=', 100]),
                    'start_date' => 1234,
                    'end_date' => 5678,
                    'automation' => self::$workflow->id,
                ],
                'expected' => [
                    'class' => PaymentListQueryBuilder::class,
                    'where' => fn () => [
                        'tenant_id' => self::$company->id,
                        ['balance', 100, '='],
                        ['currency', 'usd', '='],
                        ['method', 'afsfsdf', '='],
                        'AutomationWorkflowEnrollments.workflow_id' => self::$workflow->id,
                        ['date', 1234, '>='],
                        ['date', 5678, '<='],
                        ['amount', 100, '>='],
                    ],
                    'joins' => [
                        [
                            AutomationWorkflowEnrollment::class,
                            'id',
                            'object_id',
                            'JOIN',
                        ],
                    ],
                    'with' => [],
                    'model' => Payment::class,
                    'sort' => [['id', 'asc']],
                ],
            ],
            'Subscription no filter' => [
                'type' => Subscription::class,
                'options' => fn () => [
                    'filter' => [],
                    'advanced_filter' => null,
                ],
                'expected' => [
                    'class' => SubscriptionListQueryBuilder::class,
                    'where' => fn () => [
                        'tenant_id' => self::$company->id,
                        ['canceled', false, '='],
                        ['finished', false, '='],
                    ],
                    'joins' => [],
                    'with' => ['customer'],
                    'model' => Subscription::class,
                    'sort' => [['id', 'asc']],
                ],
            ],
            'Subscription with metadata' => [
                'type' => Subscription::class,
                'options' => fn () => [
                    'filter' => [
                        'canceled_reason' => 'test',
                        'cycles' => 1,
                    ],
                    'metadata' => [
                        'not valid',
                        'not valid;' => 'still not valid',
                        'valid' => 'value',
                        'valid2' => 'value2',
                    ],
                    'advanced_filter' => '[{"field":"paused","operator":"=","value":1}]',
                    'plan' => 1,
                    'canceled' => 1,
                    'finished' => 1,
                    'contract' => 1,
                    'automation' => self::$workflow->id,
                ],
                'expected' => [
                    'class' => SubscriptionListQueryBuilder::class,
                    'where' => fn () => [
                        'tenant_id' => self::$company->id,
                        ['canceled_reason', 'test', '='],
                        ['cycles', 1, '='],
                        ['paused', 1, '='],
                        ['canceled', true, '='],
                        ['finished', true, '='],
                        ['cycles', 0, '>'],
                        'AutomationWorkflowEnrollments.workflow_id' => self::$workflow->id,
                        'EXISTS (SELECT 1 FROM Metadata WHERE `tenant_id`='.self::$company->id." AND `object_type`=\"subscription\" AND object_id=Subscriptions.id AND `key`='valid' AND `value` = 'value')",
                        'EXISTS (SELECT 1 FROM Metadata WHERE `tenant_id`='.self::$company->id." AND `object_type`=\"subscription\" AND object_id=Subscriptions.id AND `key`='valid2' AND `value` = 'value2')",
                        "(plan='1' OR EXISTS (SELECT 1 FROM SubscriptionAddons WHERE subscription_id=Subscriptions.id AND plan='1'))",
                    ],
                    'joins' => [
                        [
                            AutomationWorkflowEnrollment::class,
                            'id',
                            'object_id',
                            'JOIN',
                        ],
                    ],
                    'with' => ['customer'],
                    'model' => Subscription::class,
                    'sort' => [['id', 'asc']],
                ],
                'Subscription special' => [
                    'type' => Subscription::class,
                    'options' => fn () => [
                        'contract' => 0,
                    ],
                    'expected' => [
                        'class' => SubscriptionListQueryBuilder::class,
                        'where' => fn () => [
                            'tenant_id' => self::$company->id,
                            ['cycles', 0, '='],
                        ],
                        'joins' => [
                            [],
                        ],
                        'with' => ['customer'],
                        'model' => Subscription::class,
                        'sort' => [['id', 'asc']],
                    ],
                ],
            ],
            'Transaction no filter' => [
                'type' => Transaction::class,
                'options' => fn () => [
                    'filter' => [],
                    'advanced_filter' => null,
                    'total' => null,
                ],
                'expected' => [
                    'class' => TransactionListQueryBuilder::class,
                    'where' => fn () => [
                        'tenant_id' => self::$company->id,
                    ],
                    'joins' => [],
                    'with' => ['customer'],
                    'model' => Transaction::class,
                    'sort' => [['id', 'asc']],
                ],
            ],
            'Transaction with metadata' => [
                'type' => Transaction::class,
                'options' => fn () => [
                    'filter' => [
                        'type' => 'test',
                        'currency' => 'usd',
                        'customer' => 'test',
                    ],
                    'advanced_filter' => '[{"field":"status","operator":"=","value":"1234"}]',
                    'amount' => json_encode([
                        '=',
                        100,
                    ]),
                    'metadata' => [
                        'not valid',
                        'not valid;' => 'still not valid',
                        'valid' => 'value',
                        'valid2' => 'value2',
                    ],
                    'start_date' => 1234,
                    'end_date' => 5678,
                ],
                'expected' => [
                    'class' => TransactionListQueryBuilder::class,
                    'where' => fn () => [
                        'tenant_id' => self::$company->id,
                        ['type', 'test', '='],
                        ['currency', 'usd', '='],
                        ['customer', 'test', '='],
                        ['status', '1234', '='],
                        ['date', 1234, '>='],
                        ['date', 5678, '<='],
                        ['amount', 100, '='],
                        'EXISTS (SELECT 1 FROM Metadata WHERE `tenant_id`='.self::$company->id." AND `object_type`=\"transaction\" AND object_id=Transactions.id AND `key`='valid' AND `value` = 'value')",
                        'EXISTS (SELECT 1 FROM Metadata WHERE `tenant_id`='.self::$company->id." AND `object_type`=\"transaction\" AND object_id=Transactions.id AND `key`='valid2' AND `value` = 'value2')",
                    ],
                    'joins' => [],
                    'with' => ['customer'],
                    'model' => Transaction::class,
                    'sort' => [['id', 'asc']],
                ],
            ],
            'Vendor no filter' => [
                'type' => Vendor::class,
                'options' => fn () => [
                    'filter' => [],
                    'advanced_filter' => null,
                ],
                'expected' => [
                    'class' => VendorListQueryBuilder::class,
                    'where' => fn () => [
                        'tenant_id' => self::$company->id,
                    ],
                    'joins' => [],
                    'with' => [],
                    'model' => Vendor::class,
                    'sort' => [['id', 'asc']],
                ],
            ],
            'Vendor with metadata' => [
                'type' => Vendor::class,
                'options' => fn () => [
                    'filter' => [
                        'city' => 'Austin',
                        'name' => 'usd',
                    ],
                    'advanced_filter' => '[{"field":"email","operator":"=","value":"1234"}]',
                    'automation' => self::$workflow->id,
                ],
                'expected' => [
                    'class' => VendorListQueryBuilder::class,
                    'where' => fn () => [
                        'tenant_id' => self::$company->id,
                        ['city', 'Austin', '='],
                        ['name', 'usd', '='],
                        ['email', '1234', '='],
                        'AutomationWorkflowEnrollments.workflow_id' => self::$workflow->id,
                    ],
                    'joins' => [
                        [
                            AutomationWorkflowEnrollment::class,
                            'id',
                            'object_id',
                            'JOIN',
                        ],
                    ],
                    'with' => [],
                    'model' => Vendor::class,
                    'sort' => [['id', 'asc']],
                ],
            ],
        ];
    }

    public function sortProvider(): array
    {
        return [
            [
                CreditNote::class,
                'id ASC',
                [
                    'sort' => [['id', 'asc']],
                    'join' => [],
                ],
            ],
            [
                CreditNote::class,
                'date ASC',
                [
                    'sort' => [['date', 'asc'], ['id', 'asc']],
                    'join' => [],
                ],
            ],
            [
                CreditNote::class,
                'Customers.date ASC',
                [
                    'sort' => [['Customers.date', 'asc'], ['id', 'asc']],
                    'join' => [[
                        Customer::class,
                        'customer',
                        'Customers.id',
                        'JOIN',
                    ]],
                ],
            ],
            [
                CreditNote::class,
                'random ASC',
                [
                    'sort' => [['id', 'asc']],
                    'join' => [],
                ],
            ],
            [
                CreditNote::class,
                'random ASC, date ASC, balance DESC, somethindg DESC',
                [
                    'sort' => [['date', 'asc'], ['balance', 'desc'], ['id', 'asc']],
                    'join' => [],
                ],
            ],
            [
                Item::class,
                'random ASC, archived ASC, currency DESC, somethindg DESC',
                [
                    'sort' => [['archived', 'asc'], ['currency', 'desc'], ['id', 'asc']],
                    'join' => [],
                ],
            ],
        ];
    }
}
