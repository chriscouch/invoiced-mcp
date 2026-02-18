<?php

namespace App\Tests\AccountsReceivable\Libs;

use App\AccountsReceivable\Libs\IssuedDocumentNotifier;
use App\AccountsReceivable\Models\CreditNote;
use App\AccountsReceivable\Models\Estimate;
use App\AccountsReceivable\Models\Invoice;
use App\AccountsReceivable\Models\InvoiceDelivery;
use App\CashApplication\Models\Transaction;
use App\Chasing\Models\InvoiceChasingCadence;
use App\EntryPoint\CronJob\IssuedDocumentNotices;
use App\ActivityLog\Libs\EventSpool;
use App\PaymentPlans\Models\PaymentPlan;
use App\PaymentPlans\Models\PaymentPlanInstallment;
use App\PaymentProcessing\Gateways\TestGateway;
use App\Sending\Email\Models\EmailTemplate;
use App\Sending\Email\Models\EmailTemplateOption;
use App\Tests\AppTestCase;
use App\Core\Orm\Iterator;
use Carbon\CarbonImmutable;
use Doctrine\DBAL\Connection;

class IssuedDocumentNotifierTest extends AppTestCase
{
    private static Invoice $autoPayInvoice;
    private static Invoice $paymentPlanInvoice;
    private static Invoice $sentInvoice;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        EmailTemplateOption::queryWithoutMultitenancyUnsafe()->delete();

        self::hasCompany();
        self::hasCustomer();
        self::hasBankAccount();
        self::hasInvoice();
        self::hasCreditNote();
        self::acceptsCreditCards(TestGateway::ID);

        // These should be picked up by the notifier
        self::$customer->setDefaultPaymentSource(self::$bankAccount);
        self::$customer->saveOrFail();
        self::$invoice = new Invoice();
        self::$invoice->date = strtotime('-5 minutes');
        self::$invoice->setCustomer(self::$customer);
        self::$invoice->items = [['unit_cost' => 100]];
        self::$invoice->saveOrFail();

        self::$creditNote = new CreditNote();
        self::$creditNote->date = strtotime('-5 minutes');
        self::$creditNote->setCustomer(self::$customer);
        self::$creditNote->setInvoice(self::$invoice);
        self::$creditNote->items = [['unit_cost' => 50]];
        self::$creditNote->saveOrFail();

        self::$estimate = new Estimate();
        self::$estimate->date = strtotime('-5 minutes');
        self::$estimate->setCustomer(self::$customer);
        self::$estimate->items = [['unit_cost' => 50]];
        self::$estimate->saveOrFail();

        self::$paymentPlanInvoice = new Invoice();
        self::$paymentPlanInvoice->date = strtotime('-5 minutes');
        self::$paymentPlanInvoice->setCustomer(self::$customer);
        self::$paymentPlanInvoice->items = [['unit_cost' => 100]];
        self::$paymentPlanInvoice->saveOrFail();

        $installment1 = new PaymentPlanInstallment();
        $installment1->date = strtotime('+2 days');
        $installment1->amount = 50;
        $installment2 = new PaymentPlanInstallment();
        $installment2->date = strtotime('+1 month');
        $installment2->amount = 50;
        $paymentPlan = new PaymentPlan();
        $paymentPlan->installments = [
            $installment1,
            $installment2,
        ];
        self::$paymentPlanInvoice->attachPaymentPlan($paymentPlan, true, true);

        // These should not be sent out
        $draftInvoice = new Invoice();
        $draftInvoice->date = strtotime('-5 minutes');
        $draftInvoice->setCustomer(self::$customer);
        $draftInvoice->items = [['unit_cost' => 100]];
        $draftInvoice->draft = true;
        $draftInvoice->saveOrFail();

        $paidInvoice = new Invoice();
        $paidInvoice->date = strtotime('-1 month');
        $paidInvoice->setCustomer(self::$customer);
        $paidInvoice->saveOrFail();

        $badDebtInvoice = new Invoice();
        $badDebtInvoice->date = strtotime('-1 month');
        $badDebtInvoice->setCustomer(self::$customer);
        $badDebtInvoice->items = [['unit_cost' => 100]];
        $badDebtInvoice->closed = true;
        $badDebtInvoice->date_bad_debt = time();
        $badDebtInvoice->saveOrFail();

        $voidedInvoice = new Invoice();
        $voidedInvoice->date = strtotime('-1 month');
        $voidedInvoice->setCustomer(self::$customer);
        $voidedInvoice->items = [['unit_cost' => 100]];
        $voidedInvoice->saveOrFail();
        $voidedInvoice->void();

        self::$autoPayInvoice = new Invoice();
        self::$autoPayInvoice->date = strtotime('-1 month');
        self::$autoPayInvoice->setCustomer(self::$customer);
        self::$autoPayInvoice->items = [['unit_cost' => 100]];
        self::$autoPayInvoice->autopay = true;
        self::$autoPayInvoice->saveOrFail();

        $sentInvoice = new Invoice();
        $sentInvoice->date = strtotime('-1 month');
        $sentInvoice->setCustomer(self::$customer);
        $sentInvoice->items = [['unit_cost' => 100]];
        $sentInvoice->sent = true;
        $sentInvoice->last_sent = strtotime('-1 month');
        $sentInvoice->saveOrFail();
        self::$sentInvoice = $sentInvoice;

        $futureInvoice = new Invoice();
        $futureInvoice->date = time();
        $futureInvoice->setCustomer(self::$customer);
        $futureInvoice->items = [['unit_cost' => 100]];
        $futureInvoice->sent = true;
        $futureInvoice->saveOrFail();

        $pendingInvoice = new Invoice();
        $pendingInvoice->date = strtotime('-1 month');
        $pendingInvoice->setCustomer(self::$customer);
        $pendingInvoice->items = [['unit_cost' => 100]];
        $pendingInvoice->saveOrFail();

        $pendingPayment = new Transaction();
        $pendingPayment->setInvoice($pendingInvoice);
        $pendingPayment->status = Transaction::STATUS_PENDING;
        $pendingPayment->amount = 100;
        $pendingPayment->saveOrFail();

        $expiredEstimate = new Estimate();
        $expiredEstimate->date = strtotime('-1 month');
        $expiredEstimate->expiration_date = strtotime('-1 minute');
        $expiredEstimate->setCustomer(self::$customer);
        $expiredEstimate->items = [['unit_cost' => 100]];
        $expiredEstimate->saveOrFail();

        $voidedEstimate = new Estimate();
        $voidedEstimate->date = strtotime('-1 month');
        $voidedEstimate->setCustomer(self::$customer);
        $voidedEstimate->items = [['unit_cost' => 100]];
        $voidedEstimate->saveOrFail();
        $voidedEstimate->void();

        // enable new invoice email
        $emailTemplate = new EmailTemplate();
        $emailTemplate->id = EmailTemplate::NEW_INVOICE;
        $emailTemplate->subject = 'subject';
        $emailTemplate->body = 'test';
        $options = $emailTemplate->options;
        $options[EmailTemplateOption::SEND_ON_ISSUE] = true;
        $emailTemplate->options = $options;
        $emailTemplate->saveOrFail();

        // enable credit note email
        $emailTemplate = new EmailTemplate();
        $emailTemplate->id = EmailTemplate::CREDIT_NOTE;
        $emailTemplate->subject = 'subject';
        $emailTemplate->body = 'test';
        $options = $emailTemplate->options;
        $options[EmailTemplateOption::SEND_ON_ISSUE] = true;
        $emailTemplate->options = $options;
        $emailTemplate->saveOrFail();

        // enable payment plan email
        $emailTemplate = new EmailTemplate();
        $emailTemplate->id = EmailTemplate::PAYMENT_PLAN;
        $emailTemplate->subject = 'subject';
        $emailTemplate->body = 'test';
        $options = $emailTemplate->options;
        $options[EmailTemplateOption::SEND_ON_ISSUE] = true;
        $options[EmailTemplateOption::SEND_REMINDER_DAYS] = 3;
        $emailTemplate->options = $options;
        $emailTemplate->saveOrFail();

        // enable estimate email
        $emailTemplate = new EmailTemplate();
        $emailTemplate->id = EmailTemplate::ESTIMATE;
        $emailTemplate->subject = 'subject';
        $emailTemplate->body = 'test';
        $options = $emailTemplate->options;
        $options[EmailTemplateOption::SEND_ON_ISSUE] = true;
        $options[EmailTemplateOption::SEND_REMINDER_DAYS] = 3;
        $emailTemplate->options = $options;
        $emailTemplate->saveOrFail();

        /** @var Connection $connection */
        $connection = self::getService('test.database');
        $connection->update('Invoices', ['updated_at' => CarbonImmutable::now()->subMinutes(6)], ['tenant_id' => self::$company->id()]);
        $connection->update('CreditNotes', ['updated_at' => CarbonImmutable::now()->subMinutes(6)], ['tenant_id' => self::$company->id()]);
        $connection->update('Estimates', ['updated_at' => CarbonImmutable::now()->subMinutes(6)], ['tenant_id' => self::$company->id()]);

        // non affected items
        self::getTestDataFactory()->createInvoice(self::$customer);
        self::getTestDataFactory()->createCreditNote(self::$customer);
        self::getTestDataFactory()->createEstimate(self::$customer);
    }

    private function getNotifier(): IssuedDocumentNotifier
    {
        return new IssuedDocumentNotifier();
    }

    private function getJob(): IssuedDocumentNotices
    {
        return self::getService('test.issued_document_notices');
    }

    public function testGetWithNotifications(): void
    {
        $options = $this->getJob()->getWithNotifications();
        $this->assertInstanceOf(Iterator::class, $options);

        $options = $options->toArray();

        $this->assertCount(6, $options);
        foreach ($options as $option) {
            $this->assertInstanceOf(EmailTemplateOption::class, $option);
        }
        $this->assertEquals(EmailTemplate::CREDIT_NOTE, $options[0]->template);
        $this->assertEquals(EmailTemplate::ESTIMATE, $options[1]->template);
        $this->assertEquals(EmailTemplate::ESTIMATE, $options[2]->template);
        $this->assertEquals(EmailTemplate::NEW_INVOICE, $options[3]->template);
        $this->assertEquals(EmailTemplate::PAYMENT_PLAN, $options[4]->template);
        $this->assertEquals(EmailTemplate::PAYMENT_PLAN, $options[5]->template);
    }

    public function testGetInvoices(): void
    {
        $notifier = $this->getNotifier();

        $invoices = $notifier->getInvoices(self::$company, true, 3, true)->execute(); /* @phpstan-ignore-line */
        $this->assertCount(3, $invoices);
        usort($invoices, fn ($a, $b) => $a->id() <=> $b->id());
        $this->assertEquals(self::$invoice->id(), $invoices[0]->id());
        $this->assertEquals(self::$autoPayInvoice->id(), $invoices[1]->id());
        $this->assertEquals(self::$sentInvoice->id(), $invoices[2]->id());
        $invoices = $notifier->getInvoices(self::$company, true, 3, false)->all(); /* @phpstan-ignore-line */
        $this->assertCount(2, $invoices);
        $this->assertEquals(self::$invoice->id(), $invoices[0]->id());
        $this->assertEquals(self::$sentInvoice->id(), $invoices[1]->id());

        $invoices = $notifier->getInvoices(self::$company, true, 0, true)->all(); /* @phpstan-ignore-line */
        $this->assertCount(2, $invoices);
        $this->assertEquals(self::$invoice->id(), $invoices[0]->id());
        $this->assertEquals(self::$autoPayInvoice->id(), $invoices[1]->id());

        $invoices = $notifier->getInvoices(self::$company, true, 0, false)->all(); /* @phpstan-ignore-line */
        $this->assertCount(1, $invoices);
        $this->assertEquals(self::$invoice->id(), $invoices[0]->id());

        $invoices = $notifier->getInvoices(self::$company, false, 300, false)->all(); /* @phpstan-ignore-line */
        $this->assertCount(0, $invoices);

        $invoices = $notifier->getInvoices(self::$company, false, 3, false)->all(); /* @phpstan-ignore-line */
        $this->assertCount(1, $invoices);
        $this->assertEquals(self::$sentInvoice->id(), $invoices[0]->id());

        $invoices = $notifier->getInvoices(self::$company, true, 0, false, strtotime('+1 month'))->all(); /* @phpstan-ignore-line */
        $this->assertCount(0, $invoices);

        // with delivery
        $invoices = $notifier->getInvoices(self::$company, true, 0, false)->all(); /* @phpstan-ignore-line */
        $this->assertCount(1, $invoices);

        $delivery = new InvoiceDelivery();
        $delivery->invoice_id = $invoices[0]->id;
        $delivery->saveOrFail();

        $invoices = $notifier->getInvoices(self::$company, true, 0, false)->all(); /* @phpstan-ignore-line */
        $this->assertCount(1, $invoices);

        $delivery->chase_schedule = [
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
        $delivery->saveOrFail();

        $invoices = $notifier->getInvoices(self::$company, true, 0, false)->all(); /* @phpstan-ignore-line */
        $this->assertCount(0, $invoices);

        $delivery->disabled = true;
        $delivery->saveOrFail();

        $invoices = $notifier->getInvoices(self::$company, true, 0, false)->all(); /* @phpstan-ignore-line */
        $this->assertCount(1, $invoices);
    }

    public function testGetPaymentPlans(): void
    {
        $notifier = $this->getNotifier();

        $invoices = $notifier->getPaymentPlans(self::$company, true, 3)->all(); /* @phpstan-ignore-line */
        $this->assertCount(1, $invoices);
        $this->assertEquals(self::$paymentPlanInvoice->id(), $invoices[0]->id());

        $invoices = $notifier->getPaymentPlans(self::$company, true, 0)->all(); /* @phpstan-ignore-line */
        $this->assertCount(1, $invoices);
        $this->assertEquals(self::$paymentPlanInvoice->id(), $invoices[0]->id());

        $invoices = $notifier->getPaymentPlans(self::$company, false, 3)->all(); /* @phpstan-ignore-line */
        $this->assertCount(0, $invoices);
    }

    public function testGetCreditNotes(): void
    {
        $notifier = $this->getNotifier();

        $creditNotes = $notifier->getCreditNotes(self::$company)->all();
        $this->assertCount(1, $creditNotes);
        $this->assertEquals(self::$creditNote->id(), $creditNotes[0]->id());
    }

    public function testGetEstimates(): void
    {
        $notifier = $this->getNotifier();

        $estimates = $notifier->getEstimates(self::$company, true, 3)->all(); /* @phpstan-ignore-line */
        $this->assertCount(1, $estimates);
        $this->assertEquals(self::$estimate->id(), $estimates[0]->id());

        $estimates = $notifier->getEstimates(self::$company, true, 0)->all(); /* @phpstan-ignore-line */
        $this->assertCount(1, $estimates);
        $this->assertEquals(self::$estimate->id(), $estimates[0]->id());

        $estimates = $notifier->getEstimates(self::$company, false, 3)->all(); /* @phpstan-ignore-line */
        $this->assertCount(0, $estimates);
    }

    public function testSendInvoices(): void
    {
        EventSpool::enable();

        $job = $this->getJob();

        // send out notifications
        $this->assertEquals(2, $job->sendInvoices(self::$company, true, 0, true));

        // verify
        $this->assertEquals(2, self::getService('test.email_spool')->size());
        self::getService('test.email_spool')->flush();

        // send notifications again
        // nothing should happen
        $this->assertEquals(0, $job->sendInvoices(self::$company, true, 0, true));

        // verify
        $this->assertEquals(0, self::getService('test.email_spool')->size());

        // try with reminders only on a newly issued invoice
        // only sent invoice should be sent
        self::$invoice->sent = false;
        self::$invoice->last_sent = strtotime('-3 days');
        self::$invoice->saveOrFail();
        // change email to prevent debouncing
        self::$customer->email = 'test3@example.com';
        self::$customer->saveOrFail();

        $this->assertEquals(1, $job->sendInvoices(self::$company, false, 3, true));

        $this->assertEquals(1, self::getService('test.email_spool')->size());
        self::getService('test.email_spool')->flush();

        // backdating issue date should send invoice
        self::$invoice->date = strtotime('-3 days');
        self::$invoice->saveOrFail();

        /** @var Connection $connection */
        $connection = self::getService('test.database');
        $connection->update('Invoices', ['updated_at' => CarbonImmutable::now()->subMinutes(6)], ['id' => self::$invoice->id()]);
        $this->assertEquals(1, $job->sendInvoices(self::$company, true, 3, true));

        $this->assertEquals(1, self::getService('test.email_spool')->size());
        self::getService('test.email_spool')->flush();

        // try with reminders only on an older invoice
        self::$invoice->last_sent = strtotime('-3 days');
        self::$invoice->saveOrFail();
        // change email to prevent debouncing
        self::$customer->email = 'test4@example.com';
        self::$customer->saveOrFail();

        $connection->update('Invoices', ['updated_at' => CarbonImmutable::now()->subMinutes(6)], ['id' => self::$invoice->id()]);
        $this->assertEquals(1, $job->sendInvoices(self::$company, false, 3, true));

        $this->assertEquals(1, self::getService('test.email_spool')->size());
        self::getService('test.email_spool')->flush();

        // backdating issue date with a cutoff date should NOT send invoice
        self::$invoice->date = strtotime('-3 days');
        self::$invoice->saveOrFail();

        $this->assertEquals(0, $job->sendInvoices(self::$company, true, 3, true, time()));

        $this->assertEquals(0, self::getService('test.email_spool')->size());
    }

    public function testSendPaymentPlans(): void
    {
        EventSpool::enable();

        $job = $this->getJob();

        // change email to prevent debouncing
        self::$customer->email = 'test@example.com';
        self::$customer->saveOrFail();

        // send out notifications
        $this->assertEquals(1, $job->sendPaymentPlans(self::$company, true, 0));

        // verify
        $this->assertEquals(1, self::getService('test.email_spool')->size());
        self::getService('test.email_spool')->flush();

        // send notifications again
        // nothing should happen
        $this->assertEquals(0, $job->sendPaymentPlans(self::$company, true, 3));

        // verify
        $this->assertEquals(0, self::getService('test.email_spool')->size());

        // try with reminders only on a newly issued payment plan
        // nothing should happen
        self::$paymentPlanInvoice->sent = false;
        self::$paymentPlanInvoice->last_sent = strtotime('-3 days');
        self::$paymentPlanInvoice->saveOrFail();
        // change email to prevent debouncing
        self::$customer->email = 'test3@example.com';
        self::$customer->saveOrFail();

        $this->assertEquals(0, $job->sendPaymentPlans(self::$company, false, 3));

        // backdating issue date should send payment plan
        self::$paymentPlanInvoice->date = strtotime('-3 days');
        self::$paymentPlanInvoice->saveOrFail();

        /** @var Connection $connection */
        $connection = self::getService('test.database');
        $connection->update('Invoices', ['updated_at' => CarbonImmutable::now()->subMinutes(6)], ['id' => self::$paymentPlanInvoice->id()]);
        $this->assertEquals(1, $job->sendPaymentPlans(self::$company, true, 3));

        $this->assertEquals(1, self::getService('test.email_spool')->size());
        self::getService('test.email_spool')->flush();

        // try with reminders only on an older payment plan
        self::$paymentPlanInvoice->last_sent = strtotime('-3 days');
        self::$paymentPlanInvoice->saveOrFail();
        // change email to prevent debouncing
        self::$customer->email = 'test4@example.com';
        self::$customer->saveOrFail();

        $connection->update('Invoices', ['updated_at' => CarbonImmutable::now()->subMinutes(6)], ['id' => self::$paymentPlanInvoice->id()]);
        $this->assertEquals(1, $job->sendPaymentPlans(self::$company, false, 3));

        $this->assertEquals(1, self::getService('test.email_spool')->size());
        self::getService('test.email_spool')->flush();
    }

    public function testSendCreditNotes(): void
    {
        EventSpool::enable();

        $job = $this->getJob();

        // send out notifications
        $this->assertEquals(1, $job->sendCreditNotes(self::$company));

        // verify
        $this->assertEquals(1, self::getService('test.email_spool')->size());
        self::getService('test.email_spool')->flush();

        // send notifications again
        // nothing should happen
        $this->assertEquals(0, $job->sendCreditNotes(self::$company));

        // verify
        $this->assertEquals(0, self::getService('test.email_spool')->size());
    }

    public function testSendEstimates(): void
    {
        EventSpool::enable();

        $job = $this->getJob();

        // change email to prevent debouncing
        self::$customer->email = 'test@example.com';
        self::$customer->saveOrFail();

        // send out notifications
        $this->assertEquals(1, $job->sendEstimates(self::$company, true, 0));

        // verify
        $this->assertEquals(1, self::getService('test.email_spool')->size());
        self::getService('test.email_spool')->flush();

        // send notifications again
        // nothing should happen
        $this->assertEquals(0, $job->sendEstimates(self::$company, true, 3));

        // verify
        $this->assertEquals(0, self::getService('test.email_spool')->size());

        // try with reminders only on a newly issued estimate
        // nothing should happen
        self::$estimate->sent = false;
        self::$estimate->last_sent = strtotime('-3 days');
        self::$estimate->saveOrFail();
        // change email to prevent debouncing
        self::$customer->email = 'test3@example.com';
        self::$customer->saveOrFail();

        $this->assertEquals(0, $job->sendEstimates(self::$company, false, 3));

        // backdating issue date should send estimate
        self::$estimate->date = strtotime('-3 days');
        self::$estimate->saveOrFail();

        /** @var Connection $connection */
        $connection = self::getService('test.database');
        $connection->update('Estimates', ['updated_at' => CarbonImmutable::now()->subMinutes(6)], ['id' => self::$estimate->id()]);
        $this->assertEquals(1, $job->sendEstimates(self::$company, true, 3));

        $this->assertEquals(1, self::getService('test.email_spool')->size());
        self::getService('test.email_spool')->flush();

        // try with reminders only on an older estimate
        self::$estimate->last_sent = strtotime('-3 days');
        self::$estimate->saveOrFail();
        // change email to prevent debouncing
        self::$customer->email = 'test4@example.com';
        self::$customer->saveOrFail();

        $connection->update('Estimates', ['updated_at' => CarbonImmutable::now()->subMinutes(6)], ['id' => self::$estimate->id()]);
        $this->assertEquals(1, $job->sendEstimates(self::$company, false, 3));

        $this->assertEquals(1, self::getService('test.email_spool')->size());
        self::getService('test.email_spool')->flush();
    }
}
