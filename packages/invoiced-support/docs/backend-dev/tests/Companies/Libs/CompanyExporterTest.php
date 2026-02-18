<?php

namespace App\Tests\Companies\Libs;

use App\AccountsReceivable\Models\Contact;
use App\AccountsReceivable\Models\Coupon;
use App\AccountsReceivable\Models\CreditNote;
use App\AccountsReceivable\Models\Customer;
use App\AccountsReceivable\Models\Discount;
use App\AccountsReceivable\Models\Estimate;
use App\AccountsReceivable\Models\EstimateApproval;
use App\AccountsReceivable\Models\Invoice;
use App\AccountsReceivable\Models\Item;
use App\AccountsReceivable\Models\Note;
use App\AccountsReceivable\Models\ShippingDetail;
use App\AccountsReceivable\Models\Tax;
use App\CashApplication\Models\CreditBalanceAdjustment;
use App\CashApplication\Models\Payment;
use App\Chasing\Models\ChasingCadence;
use App\Chasing\Models\ChasingCadenceStep;
use App\Chasing\Models\PromiseToPay;
use App\Chasing\Models\Task;
use App\Companies\Libs\CompanyExporter;
use App\Core\I18n\ValueObjects\Money;
use App\Core\ListQueryBuilders\ListQueryBuilderFactory;
use App\Core\Multitenant\Models\MultitenantModel;
use App\Exports\Libs\ExportStorage;
use App\Exports\Models\Export;
use App\Metadata\Libs\AttributeHelper;
use App\PaymentPlans\Models\PaymentPlan;
use App\PaymentPlans\Models\PaymentPlanApproval;
use App\PaymentPlans\Models\PaymentPlanInstallment;
use App\SalesTax\Models\TaxRate;
use App\SubscriptionBilling\Models\Plan;
use App\SubscriptionBilling\Models\Subscription;
use App\SubscriptionBilling\Models\SubscriptionAddon;
use App\SubscriptionBilling\Models\SubscriptionApproval;
use App\Tests\AppTestCase;
use Doctrine\DBAL\Connection;
use Generator;
use Mockery;
use stdClass;
use ZipArchive;

class CompanyExporterTest extends AppTestCase
{
    private static array $exportModels = [
        ChasingCadence::class,
        Contact::class,
        Coupon::class,
        CreditBalanceAdjustment::class,
        CreditNote::class,
        Customer::class,
        Estimate::class,
        Invoice::class,
        Item::class,
        Note::class,
        Payment::class,
        PaymentPlan::class,
        Plan::class,
        Subscription::class,
        Task::class,
        TaxRate::class,
    ];

    public function testExport(): void
    {
        self::hasCompany();
        for ($i = 0; $i < 3; ++$i) {
            self::hasCustomer();
            if ($i < 2) {
                self::hasCard();
                self::$customer->setDefaultPaymentSource(self::$card);
                self::$customer->metadata = (object) [
                    'a' => $i,
                    'b' => $i,
                ];
            } else {
                self::hasBankAccount();
                self::$customer->setDefaultPaymentSource(self::$bankAccount);
            }
            self::$customer->saveOrFail();
        }

        foreach (['Test1', 'Test2', 'Test3'] as $name) {
            $coupon = new Coupon();
            $coupon->id = $name;
            $coupon->name = $name;
            $coupon->value = 5;
            $coupon->metadata = (object) [
                'a' => $i,
                'b' => $i,
            ];
            $coupon->saveOrFail();
        }

        $discount = new Discount();
        $discount->amount = 50;
        $discount->rate = 'coupon';
        $discount->rate_id = $coupon->internal_id;
        $discount->saveOrFail();
        $discount2 = new Discount();
        $discount2->amount = 25;
        $discount2->saveOrFail();

        $taxRate = new TaxRate();
        $taxRate->id = 'tax';
        $taxRate->name = 'Tax';
        $taxRate->value = 5;
        $taxRate->metadata = (object) [
            'a' => $i,
            'b' => $i,
        ];
        $taxRate->saveOrFail();
        $taxRate2 = new TaxRate();
        $taxRate2->id = 'tax2';
        $taxRate2->name = 'Tax2';
        $taxRate2->value = 15;
        $taxRate2->saveOrFail();

        $tax = new Tax();
        $tax->amount = 50;
        $discount->rate = 'tax';
        $discount->rate_id = $tax->id;
        $tax->saveOrFail();
        $tax2 = new Tax();
        $tax2->amount = 25;
        $tax2->saveOrFail();

        for ($i = 0; $i < 3; ++$i) {
            self::hasInvoice();
            if ($i < 2) {
                self::$invoice->metadata = (object) [
                    'a' => $i,
                    'b' => $i,
                ];
                self::$invoice->discounts = [$discount, $discount2];
                self::$invoice->ship_to = $this->getShipTo();
                self::$invoice->save();
            }
        }
        foreach (['Test1', 'Test2', 'Test3'] as $name) {
            $cadence = new ChasingCadence();
            $cadence->name = $name;
            $cadence->time_of_day = 7;
            $cadence->steps = [
                [
                    'name' => 'First Step',
                    'schedule' => 'age:0',
                    'action' => ChasingCadenceStep::ACTION_MAIL,
                    'order' => 3,
                ],
                [
                    'name' => 'First Step',
                    'schedule' => 'age:0',
                    'action' => ChasingCadenceStep::ACTION_MAIL,
                    'order' => 1,
                ],
                [
                    'name' => 'First Step',
                    'schedule' => 'age:0',
                    'action' => ChasingCadenceStep::ACTION_MAIL,
                    'order' => 2,
                ],
            ];
            $cadence->saveOrFail();
        }
        $contact = new Contact();
        $contact->customer = self::$customer;
        $contact->email = 'distro@example.com';
        $contact->name = 'Test Distro';
        $contact->department = 'test_department';
        $contact->saveOrFail();

        for ($i = 0; $i < 3; ++$i) {
            $creditNote = new CreditNote();
            $creditNote->setCustomer(self::$customer);
            $creditNote->items = [
                [
                    'name' => 'Test Item',
                    'description' => 'test',
                    'quantity' => 1,
                    'unit_cost' => 100,
                    'discounts' => [$discount, $discount2],
                ],
                [
                    'name' => 'Test Item2',
                    'description' => 'test',
                    'quantity' => 1,
                    'unit_cost' => 50,
                    'discounts' => [$discount, $discount2],
                    'taxes' => [$tax, $tax2],
                ],
            ];
            $creditNote->discounts = [$discount, $discount2];
            $creditNote->taxes = [$tax2];
            $creditNote->saveOrFail();
        }
        $creditNote->invoice_id = self::$invoice->id;
        $creditNote->saveOrFail();
        $credits = new CreditBalanceAdjustment();
        $credits->setCustomer(self::$customer);
        $credits->amount = 50;
        $credits->saveOrFail();
        self::hasEstimate();
        $approval = new EstimateApproval();
        $approval->estimate_id = self::$estimate->id;
        $approval->ip = '127.0.0.1';
        $approval->user_agent = 'test';
        $approval->saveOrFail();
        self::$estimate->approval_id = $approval->id;
        self::$estimate->approved = 'x';
        self::$estimate->saveOrFail();
        self::hasEstimate();
        $approval = new EstimateApproval();
        $approval->estimate_id = self::$estimate->id;
        $approval->ip = '127.0.0.2';
        $approval->user_agent = 'test2';
        $approval->saveOrFail();

        self::$estimate->ship_to = $this->getShipTo();
        self::$estimate->approval_id = $approval->id;
        self::$estimate->approved = 'x';
        self::$estimate->saveOrFail();
        self::hasItem();
        $catalogItemTaxed = new Item();
        $catalogItemTaxed->id = 'test-item-3';
        $catalogItemTaxed->name = 'Test Item 3';
        $catalogItemTaxed->taxes = [$taxRate->id, $taxRate2->id];
        $catalogItemTaxed->unit_cost = 99;
        $catalogItemTaxed->metadata = (object) [
            'a' => $i,
            'b' => $i,
            'c' => 'c',
            'd' => 0.01,
            'f' => Money::fromDecimal('usd', 100.10),
        ];
        $catalogItemTaxed->saveOrFail();

        $note = new Note();
        $note->invoice = self::$invoice;
        $note->notes = 'Testing';
        $note->saveOrFail();
        for ($i = 0; $i < 3; ++$i) {
            self::hasPayment();
            if ($i < 2) {
                self::$payment->metadata = (object) [
                    'a' => $i,
                    'b' => $i,
                    'c' => 'c',
                    'd' => 0.01,
                    'f' => Money::fromDecimal('usd', 100.10),
                ];
                self::$payment->save();
            }
        }
        $installment1 = new PaymentPlanInstallment();
        $installment1->date = strtotime('-1 month');
        $installment1->amount = 50;
        $installment2 = new PaymentPlanInstallment();
        $installment2->date = time() - 1;
        $installment2->amount = 50;
        $paymentPlan = new PaymentPlan();
        $paymentPlan->installments = [$installment1, $installment2];
        $paymentPlan->invoice_id = self::$invoice->id;
        $paymentPlan->saveOrFail();
        $approval = new PaymentPlanApproval();
        $approval->payment_plan_id = (int) $paymentPlan->id();
        $approval->ip = '127.0.0.1';
        $approval->user_agent = 'test';
        $approval->saveOrFail();
        $paymentPlan->approval_id = (int) $approval->id();
        $paymentPlan->status = PaymentPlan::STATUS_ACTIVE;
        $paymentPlan->saveOrFail();

        self::hasPlan();
        self::hasSubscription();
        self::$subscription->setPaymentSource(self::$card);
        self::$subscription->metadata = (object) [
            'a' => $i,
            'b' => $i,
        ];
        self::$subscription->saveOrFail();

        self::hasSubscription();
        $approval = new SubscriptionApproval();
        $approval->subscription_id = (int) self::$subscription->id();
        $approval->ip = '127.0.0.1';
        $approval->user_agent = 'test';
        $approval->saveOrFail();

        $addon2 = new SubscriptionAddon();
        $addon2->subscription_id = (int) self::$subscription->id();
        $addon2->setPlan(self::$plan);
        $addon2->saveOrFail();

        self::$subscription->discounts = [$coupon->id];
        self::$subscription->approval_id = $approval->id;
        self::$subscription->ship_to = $this->getShipTo();
        self::$subscription->taxes = [$taxRate->id, $taxRate2->id];
        self::$subscription->setPaymentSource(self::$bankAccount);
        self::$subscription->saveOrFail();

        $task = new Task();
        $task->name = 'Send shut off notice';
        $task->action = 'mail';
        $task->due_date = time();
        $task->customer_id = (int) self::$customer->id();
        $task->saveOrFail();

        $promiseToPay = new PromiseToPay();
        $promiseToPay->invoice = self::$invoice;
        $promiseToPay->customer = self::$customer;
        $promiseToPay->currency = self::$invoice->currency;
        $promiseToPay->amount = self::$invoice->balance;
        $promiseToPay->saveOrFail();

        @mkdir('/tmp/var');

        $export = $this->getExport();

        $storage = Mockery::mock(ExportStorage::class);
        $database = self::getService('test.database');
        $helper = self::getService('test.attribute_helper');
        $factory = self::getService('test.list_query_builder_factory');
        $exporter = new class($this, $export, $database, $helper, $storage, $factory) extends CompanyExporter {
            /** @var Generator<class-string<stdClass>> */
            protected Generator $content;
            /** @var Generator<class-string<MultitenantModel>> */
            protected Generator $types;

            public function __construct(
                protected CompanyExporterTest $testEnv,
                protected Export $export,
                Connection $database,
                AttributeHelper $helper,
                ExportStorage $storage,
                ListQueryBuilderFactory $factory,
            ) {
                parent::__construct(
                    $storage,
                    '/tmp',
                    $database,
                    $helper,
                    $factory
                );

                $this->content = $this->testEnv->getContent();
                $this->types = $this->testEnv->getType();
            }

            protected function persist(Export $export, string $file): void
            {
                $zip = new ZipArchive();
                $zip->open($file);
                $zip->extractTo('/tmp/var/exports');
                $zip->close();

                $type = $this->types->current();
                $csv = (string) file_get_contents('/tmp/var/exports/'.$type::modelName().'s '.$this->export->tenant_id.'(1).json');
                $this->testEnv->assertEquals($this->content->current(), json_decode($csv, true));
                $this->types->next();
                $this->content->next();
            }
        };
        $exporter->build($export, []);
    }

    public function getContent(): Generator
    {
        foreach (self::$exportModels as $model2) {
            $finder = $model2::queryWithTenant(self::$company)
                ->all();
            $data = [];
            foreach ($finder as $model) {
                $data[] = $model->toArray();
            }
            yield json_decode((string) json_encode($data), true);
        }
    }

    /**
     * @return Generator<class-string<MultitenantModel>>
     */
    public function getType(): Generator
    {
        yield from self::$exportModels;
    }

    private function getExport(): Export
    {
        $export = Mockery::mock(Export::class.'[save,tenant]');
        $export->shouldReceive('save')
            ->andReturn(true);
        $export->shouldReceive('tenant')
            ->andReturn(self::$company);

        return $export;
    }

    private function getShipTo(): ShippingDetail
    {
        $shipTo = new ShippingDetail();
        $shipTo->name = 'Test';
        $shipTo->address1 = '1234 main st';
        $shipTo->city = 'Austin';
        $shipTo->state = 'TX';
        $shipTo->postal_code = '78701';
        $shipTo->country = 'US';

        return $shipTo;
    }
}
