<?php

namespace App\Tests\Automations\Actions;

use App\AccountsReceivable\Models\Contact;
use App\AccountsReceivable\Models\Coupon;
use App\AccountsReceivable\Models\CreditNote;
use App\AccountsReceivable\Models\Invoice;
use App\AccountsReceivable\Models\InvoiceDelivery;
use App\AccountsReceivable\Models\LineItem;
use App\Automations\Actions\CreateObjectAction;
use App\Automations\Enums\AutomationResult;
use App\Automations\Exception\AutomationException;
use App\Automations\Models\AutomationWorkflow;
use App\Automations\ValueObjects\AutomationContext;
use App\Chasing\Models\InvoiceChasingCadence;
use App\Chasing\Models\Task;
use App\Core\Utils\Enums\ObjectType;
use App\SalesTax\Models\TaxRate;
use App\Tests\AppTestCase;
use Carbon\CarbonImmutable;
use Symfony\Component\Translation\Translator;

class CreateObjectActionTest extends AppTestCase
{
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        self::hasCompany();
    }

    private function getAction(): CreateObjectAction
    {
        return new CreateObjectAction(new Translator('en'), self::getService('test.automation_normalizer'), self::getService('test.twig_renderer_factory'));
    }

    public function testPerformFail(): void
    {
        self::hasCustomer();
        $action = $this->getAction();

        $settings = (object) [
            'fields' => [
            ],
            'object_type' => 'contact',
        ];
        $context = new AutomationContext(self::$customer, new AutomationWorkflow());
        $this->expectException(AutomationException::class);
        $action->perform($settings, $context);
    }

    public function testPerform(): void
    {
        self::hasCustomer();
        $action = $this->getAction();
        $context = new AutomationContext(self::$customer, new AutomationWorkflow());
        $settings = (object) [
            'fields' => [
                (object) [
                    'name' => 'sms_enabled',
                    'value' => true,
                ],
                (object) [
                    'name' => 'name',
                    'value' => '{{customer.name}}',
                ],
                (object) [
                    'name' => 'email',
                    'value' => 'test@test.com',
                ],
                (object) [
                    'name' => 'country',
                    'value' => 'US',
                ],
                (object) [
                    'name' => 'customer',
                    'value' => '{{customer.id}}',
                ],
            ],
            'object_type' => 'contact',
        ];

        $response = $action->perform($settings, $context);

        $contacts = Contact::where('customer_id', self::$customer->id)->execute();
        $this->assertCount(1, $contacts);
        $this->assertEquals($contacts[0]->sms_enabled, true);
        $this->assertEquals($contacts[0]->name, self::$customer->name);
        $this->assertEquals($contacts[0]->email, 'test@test.com');
        $this->assertEquals(AutomationResult::Succeeded, $response->result);
    }

    public function testCreateCreditNote(): void
    {
        self::hasCustomer();
        $action = $this->getAction();
        $context = new AutomationContext(self::$customer, new AutomationWorkflow());
        $settings = (object) [
            'fields' => [
                (object) [
                    'name' => 'customer',
                    'value' => '{{customer.id}}',
                ],
                (object) [
                    'name' => 'line_items',
                    'value' => json_encode([
                        [
                            'name' => 'item 1',
                            'quantity' => 1,
                            'unit_cost' => 2,
                            'amount' => 2,
                        ],
                        [
                            'name' => 'item 2',
                            'quantity' => 2,
                            'unit_cost' => 3,
                            'amount' => 6,
                        ],
                    ]),
                ],
            ],
            'object_type' => 'credit_note',
        ];

        $response = $action->perform($settings, $context);
        $this->assertEquals(AutomationResult::Succeeded, $response->result);
        /** @var CreditNote[] $cns */
        $cns = CreditNote::where('customer', self::$customer->id)->execute();
        $this->assertCount(1, $cns);
        $items = LineItem::where('credit_note_id', $cns[0]->id)->execute();
        $this->assertCount(2, $items);
        $this->assertEquals('item 1', $items[0]->name);
        $this->assertEquals(1, $items[0]->quantity);
        $this->assertEquals(2, $items[0]->unit_cost);
        $this->assertEquals(2, $items[0]->amount);
        $this->assertEquals('item 2', $items[1]->name);
        $this->assertEquals(2, $items[1]->quantity);
        $this->assertEquals(3, $items[1]->unit_cost);
        $this->assertEquals(6, $items[1]->amount);
    }

    public function testCreateTask(): void
    {
        self::hasCustomer();
        $action = $this->getAction();
        $context = new AutomationContext(self::$customer, new AutomationWorkflow());
        $settings = (object) [
            'fields' => [
                (object) [
                    'name' => 'customer',
                    'value' => '{{customer.id}}',
                ],
                (object) [
                    'name' => 'due_date',
                    'value' => 7,
                ],
                (object) [
                    'name' => 'name',
                    'value' => 'auto_task',
                ],
                (object) [
                    'name' => 'action',
                    'value' => 'email',
                ],
            ],
            'object_type' => 'task',
        ];

        $response = $action->perform($settings, $context);
        $this->assertEquals(AutomationResult::Succeeded, $response->result);
        /** @var Task[] $tasks */
        $tasks = Task::where('customer_id', self::$customer)->execute();
        $this->assertCount(1, $tasks);
        $this->assertTrue(CarbonImmutable::now()->addDays(7)->isSameDay(CarbonImmutable::createFromTimestamp($tasks[0]->due_date)));
        $this->assertEquals('email', $tasks[0]->action);
        $this->assertEquals('auto_task', $tasks[0]->name);
    }

    public function testCreateInvoiceWithPaymentPlan(): void
    {
        self::hasCustomer();

        // create coupons
        $coupon1 = new Coupon();
        $coupon1->id = 'discount';
        $coupon1->name = 'Discount';
        $coupon1->value = 5;
        $coupon1->saveOrFail();

        // create taxes
        $tax1 = new TaxRate();
        $tax1->id = 'vat';
        $tax1->name = 'VAT';
        $tax1->value = 15;
        $tax1->saveOrFail();

        $cadence1 = new InvoiceChasingCadence();
        $cadence1->name = 'Chasing Cadence';
        $cadence1->default = false;
        $cadence1->chase_schedule = [
            [
                'trigger' => InvoiceChasingCadence::ON_ISSUE,
                'options' => [
                    'hour' => 4,
                    'email' => true,
                    'sms' => false,
                    'letter' => false,
                ],
            ],
        ];
        $cadence1->saveOrFail();

        $action = $this->getAction();
        $context = new AutomationContext(self::$customer, new AutomationWorkflow());
        $settings = (object) [
            'fields' => [
                (object) [
                    'name' => 'date',
                    'value' => '2022-01-01',
                ],
                (object) [
                    'name' => 'payment_terms',
                    'value' => 'NET 30',
                ],
                (object) [
                    'name' => 'autopay',
                    'value' => 'false',
                ],
                (object) [
                    'name' => 'needs_attention',
                    'value' => 'true',
                ],
                (object) [
                    'name' => 'amount_paid',
                    'value' => '3.82',
                ],
                (object) [
                    'name' => 'customer',
                    'value' => '{{customer.id}}',
                ],
                (object) [
                    'name' => 'payment_plan',
                    'value' => json_encode([
                        'installments' => [
                            [
                                'amount' => 437,
                                'date' => 1234568,
                            ],
                            [
                                'amount' => 2.59,
                                'date' => 1234569,
                            ],
                        ],
                    ]),
                ],
                (object) [
                    'name' => 'line_items',
                    'value' => json_encode([
                        [
                            'name' => 'item 1',
                            'quantity' => 1,
                            'unit_cost' => 400,
                            'amount' => 400,
                        ],
                        [
                            'name' => 'item 2',
                            'quantity' => 2,
                            'unit_cost' => 3,
                            'amount' => 6,
                        ],
                    ]),
                ],
                (object) [
                    'name' => 'discounts',
                    'value' => json_encode([
                        [
                            'coupon' => [
                                'id' => $coupon1->id,
                            ],
                        ],
                        [
                            'amount' => 1,
                        ],
                    ]),
                ],
                (object) [
                    'name' => 'taxes',
                    'value' => json_encode([
                        [
                            'tax_rate' => [
                                'id' => $tax1->id,
                            ],
                        ],
                        [
                            'amount' => 1,
                        ],
                    ]),
                ],
                (object) [
                    'name' => 'chase',
                    'value' => 'true',
                ],
                (object) [
                    'name' => 'delivery',
                    'value' => json_encode([
                        'cadence_id' => $cadence1->id,
                    ]),
                ],
            ],
            'object_type' => 'invoice',
        ];

        $response = $action->perform($settings, $context);
        $this->assertEquals(AutomationResult::Succeeded, $response->result);
        /** @var Invoice[] $invoices */
        $invoices = Invoice::where('customer', self::$customer->id)->execute();
        $this->assertCount(1, $invoices);
        $items = LineItem::where('invoice_id', $invoices[0]->id)->execute();
        $this->assertCount(2, $items);

        $this->assertEquals(mktime(0, 0, 0, 1, 1, 2022), $invoices[0]->date);
        $this->assertEquals('Payment Plan', $invoices[0]->payment_terms);
        $this->assertFalse($invoices[0]->autopay);
        $this->assertTrue($invoices[0]->needs_attention);
        $this->assertEquals('item 1', $items[0]->name);
        $this->assertEquals(1, $items[0]->quantity);
        $this->assertEquals(400, $items[0]->unit_cost);
        $this->assertEquals(400, $items[0]->amount);
        $this->assertEquals('item 2', $items[1]->name);
        $this->assertEquals(2, $items[1]->quantity);
        $this->assertEquals(3, $items[1]->unit_cost);
        $this->assertEquals(6, $items[1]->amount);

        $plan = $invoices[0]->paymentPlan();
        $this->assertNotNull($plan);
        $this->assertEquals(437, $plan->installments[0]->amount);
        $this->assertEquals(1234568, $plan->installments[0]->date);
        $this->assertEquals(2.59, $plan->installments[1]->amount);
        $this->assertEquals(1234569, $plan->installments[1]->date);

        $this->assertEquals(true, $invoices[0]->chase);
        $this->assertEquals([
            [
                'amount' => 20.3,
                'coupon' => 'discount',
            ],
            [
                'amount' => 1,
                'coupon' => null,
            ],
        ], array_map(fn ($item) => [
            'amount' => $item['amount'],
            'coupon' => $item['coupon'] ? $item['coupon']['id'] : null,
        ], $invoices[0]->discounts()));
        $this->assertEquals([
            [
                'tax' => $tax1->id,
                'amount' => 57.71,
            ],
            [
                'amount' => 1,
                'tax' => null,
            ],
        ], array_map(fn ($item) => [
            'amount' => $item['amount'],
            'tax' => $item['tax_rate'] ? $item['tax_rate']['id'] : null,
        ], $invoices[0]->taxes()));
        $this->assertEquals($cadence1->id, InvoiceDelivery::one()->cadence_id);

        $settings->fields = array_filter($settings->fields, fn ($item) => 'delivery' !== $item->name);
        $settings->fields[] = (object) [
            'name' => 'delivery',
            'value' => '{
                "disabled": false,
                "emails": null,
                "chase_schedule": [{
                    "trigger": 5,
                    "options": {
                        "email": false,
                        "sms": true,
                        "letter": false,
                        "hour": 7,
                        "days": 2
                    }
                }]
            }', ];
        $action->perform($settings, $context);
        $delivery = InvoiceDelivery::where('cadence_id', null)->one();
        $this->assertNull($delivery->cadence_id);
        $this->assertEquals(5, $delivery->chase_schedule[0]['trigger']);
    }

    public function testFailure(): void
    {
        $action = $this->getAction();
        $context = new AutomationContext(self::$customer, new AutomationWorkflow());
        $settings = (object) [
            'fields' => [
                (object) [
                    'name' => 'customer',
                    'value' => '{{customer.id}}',
                ],
                (object) [
                    'name' => 'payment_plan',
                    'value' => json_encode([
                        'installments' => [
                            [
                                'amount' => 2,
                                'date' => 1234569,
                            ],
                        ],
                    ]),
                ],
                (object) [
                    'name' => 'line_items',
                    'value' => json_encode([
                        [
                            'name' => 'item 1',
                            'quantity' => 1,
                            'unit_cost' => 2,
                            'amount' => 2,
                        ],
                        [
                            'name' => 'item 2',
                            'quantity' => 2,
                            'unit_cost' => 3,
                            'amount' => 6,
                        ],
                    ]),
                ],
                (object) [
                    'name' => 'metadata.test',
                    'value' => 'test',
                ],
            ],
            'object_type' => 'invoice',
        ];

        $this->expectException(AutomationException::class);
        $action->perform($settings, $context);
    }

    public function testCreditNote(): void
    {
        self::hasCustomer();
        self::hasCustomField('invoice');
        $action = $this->getAction();
        $context = new AutomationContext(self::$customer, new AutomationWorkflow());
        $settings = (object) [
            'fields' => [
                (object) [
                    'name' => 'date',
                    'value' => '2022-01-01',
                ],
                (object) [
                    'name' => 'payment_terms',
                    'value' => 'NET 30',
                ],
                (object) [
                    'name' => 'autopay',
                    'value' => 'false',
                ],
                (object) [
                    'name' => 'needs_attention',
                    'value' => 'true',
                ],
                (object) [
                    'name' => 'amount_paid',
                    'value' => '4.22',
                ],
                (object) [
                    'name' => 'customer',
                    'value' => '{{customer.id}}',
                ],
                (object) [
                    'name' => 'payment_plan',
                    'value' => json_encode([
                        'installments' => [
                            [
                                'amount' => 1,
                                'date' => 1234568,
                            ],
                            [
                                'amount' => 2.78,
                                'date' => 1234569,
                            ],
                        ],
                    ]),
                ],
                (object) [
                    'name' => 'line_items',
                    'value' => json_encode([
                        [
                            'name' => 'item 1',
                            'quantity' => 1,
                            'unit_cost' => 2,
                            'amount' => 2,
                        ],
                        [
                            'name' => 'item 2',
                            'quantity' => 2,
                            'unit_cost' => 3,
                            'amount' => 6,
                        ],
                    ]),
                ],
                (object) [
                    'name' => 'metadata.test',
                    'value' => 'test',
                ],
            ],
            'object_type' => 'invoice',
        ];

        $response = $action->perform($settings, $context);
        $this->assertEquals(AutomationResult::Succeeded, $response->result);
        /** @var Invoice[] $invoices */
        $invoices = Invoice::where('customer', self::$customer->id)->execute();
        $this->assertCount(1, $invoices);
        $items = LineItem::where('invoice_id', $invoices[0]->id)->execute();
        $this->assertCount(2, $items);

        $this->assertEquals(mktime(0, 0, 0, 1, 1, 2022), $invoices[0]->date);
        $this->assertEquals('Payment Plan', $invoices[0]->payment_terms);
        $this->assertFalse($invoices[0]->autopay);
        $this->assertTrue($invoices[0]->needs_attention);
        $this->assertEquals('test', $invoices[0]->metadata->test);
        $this->assertEquals(4.22, $invoices[0]->amount_paid);
        $this->assertEquals('item 1', $items[0]->name);
        $this->assertEquals(1, $items[0]->quantity);
        $this->assertEquals(2, $items[0]->unit_cost);
        $this->assertEquals(2, $items[0]->amount);
        $this->assertEquals('item 2', $items[1]->name);
        $this->assertEquals(2, $items[1]->quantity);
        $this->assertEquals(3, $items[1]->unit_cost);
        $this->assertEquals(6, $items[1]->amount);

        $plan = $invoices[0]->paymentPlan();
        $this->assertNotNull($plan);
        $this->assertEquals(1, $plan->installments[0]->amount);
        $this->assertEquals(1234568, $plan->installments[0]->date);
        $this->assertEquals(2.78, $plan->installments[1]->amount);
        $this->assertEquals(1234569, $plan->installments[1]->date);
    }

    public function testValidateSettings(): void
    {
        $action = $this->getAction();
        $settings = (object) [
            'fields' => [
                (object) [
                    'name' => 'due_date',
                    'value' => CarbonImmutable::now()->toIso8601String(),
                ],
            ],
            'object_type' => 'invoice',
        ];

        try {
            $action->validateSettings($settings, ObjectType::Customer);
            $this->fail('Expected exception');
        } catch (AutomationException $e) {
            $this->assertEquals('Missing required field mapping: currency', $e->getMessage());
        }

        $settings->fields[] = (object) [
            'name' => 'currency',
            'value' => 'usd',
        ];
        try {
            $action->validateSettings($settings, ObjectType::Customer);
            $this->fail('Expected exception');
        } catch (AutomationException $e) {
            $this->assertEquals('Missing required field mapping: customer', $e->getMessage());
        }

        $settings->fields[] = (object) [
            'name' => 'customer',
            'value' => '{{customer.id}}',
        ];
        $action->validateSettings($settings, ObjectType::Customer);
    }
}
