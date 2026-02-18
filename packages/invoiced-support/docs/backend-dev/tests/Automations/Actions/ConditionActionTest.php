<?php

namespace App\Tests\Automations\Actions;

use App\AccountsReceivable\Models\Contact;
use App\AccountsReceivable\Models\Customer;
use App\AccountsReceivable\Models\Note;
use App\Automations\Actions\ConditionAction;
use App\Automations\Enums\AutomationResult;
use App\Automations\Exception\AutomationException;
use App\Automations\Models\AutomationWorkflow;
use App\Automations\ValueObjects\AutomationContext;
use App\CashApplication\Models\Payment;
use App\Chasing\Models\Task;
use App\Core\Utils\Enums\ObjectType;
use App\PaymentPlans\Models\PaymentPlan;
use App\PaymentPlans\Models\PaymentPlanInstallment;
use App\PaymentProcessing\Gateways\TestGateway;
use App\PaymentProcessing\Models\Charge;
use App\PaymentProcessing\Models\Refund;
use App\SubscriptionBilling\Models\PendingLineItem;
use App\Tests\AppTestCase;

class ConditionActionTest extends AppTestCase
{
    private static Charge $charge;
    private static Charge $charge2;
    private static Charge $charge3;
    private static Contact $contact;
    private static Note $note;
    private static Payment $payment2;
    private static PaymentPlan $paymentPlan;
    private static PendingLineItem $line;
    private static Refund $refund1;
    private static Refund $refund2;
    private static Refund $refund3;
    private static Task $task1;
    private static Task $task2;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        self::hasCompany();
        self::hasCustomer();
        self::hasInvoice();

        self::hasPayment();
        self::$payment2 = self::getTestDataFactory()->createPayment(self::$customer);
        self::$charge = self::makeCharge();
        self::$charge2 = self::makeCharge(self::$customer);
        self::$charge3 = self::makeCharge(self::$customer, self::$payment);

        self::$contact = new Contact();
        self::$contact->customer = self::$customer;
        self::$contact->name = 'test';
        self::$contact->saveOrFail();

        self::hasCreditNote();
        self::hasEstimate();

        self::$note = new Note();
        self::$note->customer = self::$customer;
        self::$note->user = null;
        self::$note->notes = 'test note';
        self::$note->saveOrFail();

        $installment1 = new PaymentPlanInstallment();
        $installment1->date = time() - 60 * 86400;
        $installment1->amount = 100;

        self::$paymentPlan = new PaymentPlan();
        self::$paymentPlan->invoice_id = (int) self::$invoice->id();
        self::$paymentPlan->installments = [
            $installment1,
        ];
        self::$invoice->attachPaymentPlan(self::$paymentPlan, false, true);

        $invoice = self::getTestDataFactory()->createInvoice(self::$customer);
        self::$line = new PendingLineItem();
        self::$line->setParent($invoice);
        self::$line->name = 'Line 1';
        self::$line->unit_cost = 100;
        self::$line->saveOrFail();

        self::$refund1 = self::makeRefund(self::$charge);
        self::$refund2 = self::makeRefund(self::$charge2);
        self::$refund3 = self::makeRefund(self::$charge3);

        self::hasPlan();
        self::hasSubscription();

        self::$task1 = self::makeTask();
        self::$task2 = self::makeTask(self::$customer);
    }

    public function testPerform(): void
    {
        $action = new ConditionAction(self::getService('test.customer_balance_expression_function_provider'));
        $context = new AutomationContext(self::$invoice, new AutomationWorkflow());
        $settings = (object) [
            'expression' => 'invoice.id == 1',
            'object_type' => 'invoice',
        ];

        $response = $action->perform($settings, $context);
        $this->assertEquals(AutomationResult::Succeeded, $response->result);
        $this->assertTrue($response->terminate);

        $settings->expression = 'invoice.id == '.self::$invoice->id;
        $response = $action->perform($settings, $context);
        $this->assertEquals(AutomationResult::Succeeded, $response->result);
        $this->assertFalse($response->terminate);

        $settings = (object) [
            'expression' => 'customer.id == '.self::$customer->id,
            'object_type' => 'customer',
        ];
        $response = $action->perform($settings, $context);
        $this->assertEquals(AutomationResult::Succeeded, $response->result);
        $this->assertFalse($response->terminate);
    }

    public function testValidateSettings(): void
    {
        $action = new ConditionAction(self::getService('test.customer_balance_expression_function_provider'));
        $settings = (object) [
            'expression' => 'task.id == 1',
            'object_type' => 'invoice',
        ];

        try {
            $action->validateSettings($settings, ObjectType::Invoice);
            $this->fail('Expected exception');
        } catch (AutomationException $e) {
            $this->assertEquals('Invalid expression', $e->getMessage());
        }

        $settings->expression = 'invoice.id == 1';
        $action->validateSettings($settings, ObjectType::Invoice);
    }

    /**
     * @dataProvider provideSettings
     */
    public function testRelations(callable $object, callable $to, string $object2, AutomationResult $result, bool $terminate): void
    {
        $context = new AutomationContext($object(), new AutomationWorkflow());
        $action = new ConditionAction(self::getService('test.customer_balance_expression_function_provider'));
        $settings = (object) [
            'expression' => $to(),
            'object_type' => $object2,
        ];
        $response = $action->perform($settings, $context);
        $this->assertEquals($result, $response->result);
        $this->assertEquals($terminate, $response->terminate);
    }

    private static function makeCharge(?Customer $customer = null, ?Payment $payment = null): Charge
    {
        $charge = new Charge();
        $charge->currency = 'usd';
        $charge->amount = self::$invoice->balance;
        $charge->status = Charge::PENDING;
        $charge->gateway = TestGateway::ID;
        $charge->gateway_id = 'ch_test' . microtime(true);
        if ($customer) {
            $charge->customer = $customer;
        }
        if ($payment) {
            $charge->payment = $payment;
        }
        $charge->saveOrFail();

        return $charge;
    }

    private static function makeRefund(Charge $charge): Refund
    {
        $refund = new Refund();
        $refund->charge = $charge;
        $refund->amount = self::$charge->amount;
        $refund->currency = self::$charge->currency;
        $refund->status = 'succeeded';
        $refund->gateway = self::$charge->gateway;
        $refund->gateway_id = 'ref_test';
        $refund->saveOrFail();

        return $refund;
    }

    private static function makeTask(?Customer $customer = null): Task
    {
        $task = new Task();
        $task->name = 'Send shut off notice';
        $task->action = 'mail';
        $task->due_date = time();
        if ($customer) {
            $task->customer = $customer;
        }
        $task->saveOrFail();

        return $task;
    }


    public function provideSettings(): array
    {
        return [
            'charge 1' => [
                fn () => self::$charge,
                fn () => 'payment.id == null',
                'payment',
                AutomationResult::Failed,
                true,
            ],
            'charge 2' => [
                fn () => self::$charge,
                fn () => 'customer.id == null',
                'customer',
                AutomationResult::Failed,
                true,
            ],
            'charge 3' => [
                fn () => self::$charge2,
                fn () => 'customer.id == '.self::$customer->id,
                'customer',
                AutomationResult::Succeeded,
                false,
            ],
            'charge 4' => [
                fn () => self::$charge3,
                fn () => 'payment.id == '.self::$payment->id,
                'payment',
                AutomationResult::Succeeded,
                false,
            ],
            'contact' => [
                fn () => self::$contact,
                fn () => 'customer.id == '.self::$customer->id,
                'customer',
                AutomationResult::Succeeded,
                false,
            ],
            'credit note' => [
                fn () => self::$creditNote,
                fn () => 'customer.id == '.self::$customer->id,
                'customer',
                AutomationResult::Succeeded,
                false,
            ],
            'estimate' => [
                fn () => self::$estimate,
                fn () => 'customer.id == '.self::$customer->id,
                'customer',
                AutomationResult::Succeeded,
                false,
            ],
            'invoice' => [
                fn () => self::$invoice,
                fn () => 'customer.id == '.self::$customer->id,
                'customer',
                AutomationResult::Succeeded,
                false,
            ],
            'note' => [
                fn () => self::$note,
                fn () => 'customer.id == '.self::$customer->id,
                'customer',
                AutomationResult::Succeeded,
                false,
            ],
            'payment 1' => [
                fn () => self::$payment2,
                fn () => 'customer.id == '.self::$customer->id,
                'customer',
                AutomationResult::Succeeded,
                false,
            ],
            'payment 2' => [
                fn () => self::$payment,
                fn () => 'customer.id == null',
                'customer',
                AutomationResult::Failed,
                true,
            ],
            'payment plan 1' => [
                fn () => self::$paymentPlan,
                fn () => 'customer.id == '.self::$customer->id,
                'customer',
                AutomationResult::Succeeded,
                false,
            ],
            'payment plan 2' => [
                fn () => self::$invoice,
                fn () => 'invoice.id == '.self::$invoice->id,
                'invoice',
                AutomationResult::Succeeded,
                false,
            ],
            'pending line item' => [
                fn () => self::$line,
                fn () => 'customer.id == '.self::$customer->id,
                'customer',
                AutomationResult::Succeeded,
                false,
            ],
            'refund 1' => [
                fn () => self::$refund1,
                fn () => 'payment.id == null',
                'payment',
                AutomationResult::Failed,
                true,
            ],
            'refund 2' => [
                fn () => self::$refund1,
                fn () => 'customer.id == null',
                'customer',
                AutomationResult::Failed,
                true,
            ],
            'refund 3' => [
                fn () => self::$refund2,
                fn () => 'customer.id == '.self::$customer->id,
                'customer',
                AutomationResult::Succeeded,
                false,
            ],
            'refund 4' => [
                fn () => self::$refund3,
                fn () => 'payment.id == '.self::$payment->id,
                'payment',
                AutomationResult::Succeeded,
                false,
            ],
            'subscription' => [
                fn () => self::$subscription,
                fn () => 'customer.id == '.self::$customer->id,
                'customer',
                AutomationResult::Succeeded,
                false,
            ],
            'task 1' => [
                fn () => self::$task1,
                fn () => 'customer.id == null',
                'customer',
                AutomationResult::Failed,
                true,
            ],
            'task 2' => [
                fn () => self::$task2,
                fn () => 'customer.id == '.self::$customer->id,
                'customer',
                AutomationResult::Succeeded,
                false,
            ],
            // value not set
            'error' => [
                fn () => self::$task2,
                fn () => 'customer.random == '.self::$customer->id,
                'customer',
                AutomationResult::Failed,
                true,
            ],
            'customer balance == 300' => [
                fn () => self::$customer,
                fn () => 'customerBalance("usd") == 300',
                'customer',
                AutomationResult::Succeeded,
                false,
            ],
            'customer balance > 300' => [
                fn () => self::$customer,
                fn () => 'customerBalance("usd") > 300',
                'customer',
                AutomationResult::Succeeded,
                true,
            ],
            'customer balance <= 301' => [
                fn () => self::$customer,
                fn () => 'customerBalance("usd") <= 301',
                'customer',
                AutomationResult::Succeeded,
                false,
            ],
            'customer balance sec' => [
                fn () => self::$customer,
                fn () => 'customerBalance("sec") == 0',
                'customer',
                AutomationResult::Succeeded,
                false,
            ],
            'customer balance USD' => [
                fn () => self::$customer,
                fn () => 'customerBalance("USD") == 300',
                'customer',
                AutomationResult::Succeeded,
                false,
            ],
            'customer balance no currency' => [
                fn () => self::$customer,
                fn () => 'customerBalance() == 300',
                'customer',
                AutomationResult::Succeeded,
                false,
            ],
            'customer invoice' => [
                fn () => self::$invoice,
                fn () => 'customerBalance() == 300',
                'invoice',
                AutomationResult::Succeeded,
                false,
            ],
            'task no customer' => [
                fn () => self::$task1,
                fn () => 'customerBalance() == null',
                'task',
                AutomationResult::Succeeded,
                false,
            ],
        ];
    }
}
