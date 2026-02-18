<?php

namespace App\Tests\AccountsReceivable\Operations;

use App\AccountsReceivable\Enums\InvoiceStatus;
use App\AccountsReceivable\Models\CreditNote;
use App\AccountsReceivable\Models\Invoice;
use App\AccountsReceivable\Models\Item;
use App\AccountsReceivable\Operations\SetBadDebt;
use App\CashApplication\Enums\PaymentItemType;
use App\CashApplication\Models\Payment;
use App\CashApplication\Models\Transaction;
use App\Core\I18n\ValueObjects\Money;
use App\Core\Orm\Event\ModelUpdating;
use App\Core\Orm\Exception\ModelException;
use App\SalesTax\Models\TaxRate;
use App\SalesTax\Models\TaxRule;
use App\Sending\Email\Libs\EmailTriggers;
use App\Sending\Email\Models\EmailTemplate;
use App\Sending\Email\Models\EmailTemplateOption;
use App\Tests\AppTestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

class SetBadDebtTest extends AppTestCase
{
    private static SetBadDebt $operation;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        self::$operation = self::getService('test.set_bad_debt');
        self::hasCompany();
        self::hasCustomer();
        self::hasInvoice();
    }

    public function testSet(): void
    {
        $requestStack = new RequestStack();
        $requestStack->push(new Request());

        $item = Item::where('id', Item::BAD_DEBT)
            ->oneOrNull();
        $this->assertNull($item);

        $operation = self::$operation;

        self::$invoice->draft = true;
        try {
            $operation->set(self::$invoice);
            $this->assertFalse(true, 'Error not thrown');
        } catch (ModelException $e) {
            $this->assertEquals("You can't write off draft invoices", $e->getMessage());
        }
        self::$invoice->draft = false;
        self::$invoice->voided = true;
        try {
            $operation->set(self::$invoice);
            $this->assertFalse(true, 'Error not thrown');
        } catch (ModelException $e) {
            $this->assertEquals("You can't write off voided invoices", $e->getMessage());
        }
        self::$invoice->balance = 0;
        self::$invoice->voided = false;
        try {
            $operation->set(self::$invoice);
            $this->assertFalse(true, 'Error not thrown');
        } catch (ModelException $e) {
            $this->assertEquals("You can't write off invoices with zero balance", $e->getMessage());
        }

        self::hasInvoice();
        $this->assertInstanceOf(Invoice::class, $operation->set(self::$invoice));

        self::$invoice->refresh();
        $this->assertInstanceOf(Invoice::class, self::$invoice);
        $this->assertTrue(self::$invoice->closed);
        $this->assertFalse(self::$invoice->paid);
        $this->assertNull(self::$invoice->date_paid);
        $this->assertGreaterThan(time() - 5, self::$invoice->date_bad_debt);
        $this->assertEquals(InvoiceStatus::BadDebt->value, self::$invoice->status);

        /** @var Transaction[] $transactions */
        $transactions = Transaction::where('invoice', self::$invoice)->execute();
        $this->assertCount(1, $transactions);
        $transaction = $transactions[0];
        $this->assertNotNull($transaction->credit_note);
        $this->assertEquals(-100, $transaction->amount);

        $this->assertEquals(1, Item::where('id', Item::BAD_DEBT)->count());

        self::hasInvoice();
        $operation->set(self::$invoice);
        $this->assertEquals(1, Item::where('id', Item::BAD_DEBT)->count());
        $this->assertTrue(self::$invoice->closed);
        $this->assertFalse(self::$invoice->paid);
        $this->assertNull(self::$invoice->date_paid);
        self::$invoice->refresh();
        $this->assertEquals(100, self::$invoice->amount_written_off);

        try {
            $operation->set(self::$invoice);
            $this->assertFalse(true, 'Error not thrown');
        } catch (ModelException $e) {
            $this->assertEquals('The invoice has already been written off', $e->getMessage());
        }

        self::hasInvoice();
        $creditNote = new CreditNote();
        $creditNote->setCustomer(self::$customer);
        $creditNote->items = [
            [
                'name' => 'Test Item',
                'description' => 'test',
                'quantity' => 1,
                'unit_cost' => 50,
            ],
        ];
        $creditNote->saveOrFail();

        $payment = new Payment();
        $payment->currency = $creditNote->currency;
        $payment->setCustomer(self::$customer);
        $payment->applied_to = [
            [
                'type' => PaymentItemType::CreditNote->value,
                'credit_note' => $creditNote,
                'document_type' => 'invoice',
                'invoice' => self::$invoice,
                'amount' => 50,
            ],
        ];
        $payment->saveOrFail();
        $operation->set(self::$invoice);
        $this->assertEquals(50, self::$invoice->amount_written_off);
    }

    public function testEmailSending(): void
    {
        $triggers = EmailTriggers::make(self::$company);
        $this->enable(EmailTemplate::PAID_INVOICE, EmailTemplateOption::SEND_ONCE_PAID);

        $invoice = \Mockery::mock(Invoice::class)->makePartial();
        $invoice->shouldReceive('setUpdatedEventType')->once();
        $event = new ModelUpdating($invoice);
        Invoice::paidEvent($event);

        $invoice->mute();
        $invoice->shouldNotReceive('setUpdatedEventType');
        $invoice->shouldNotReceive('paid');
        Invoice::paidEvent($event);
    }

    /**
     * INVD-2661: Tests that tax rules are ignored
     * when creating the credit note.
     */
    public function testIgnoreTaxRules(): void
    {
        $taxRate = new TaxRate();
        $taxRate->name = 'test-tax';
        $taxRate->is_percent = true;
        $taxRate->value = 10;
        $taxRate->saveOrFail();

        $taxRule = new TaxRule();
        $taxRule->tax_rate = $taxRate->id;
        $taxRule->saveOrFail();

        $invoice = new Invoice();
        $invoice->setCustomer(self::$customer);
        $invoice->items = [
            [
                'name' => 'Test Item',
                'description' => 'test',
                'quantity' => 1,
                'unit_cost' => 100,
            ],
        ];
        $invoice->saveOrFail();
        $this->assertTrue($invoice->getTotal()->equals(Money::fromDecimal($invoice->currency, 110)));

        // perform bad debt operation
        self::$operation->set($invoice);

        // find credit note
        $transaction = Transaction::where('invoice', $invoice->id())
            ->oneOrNull();
        if (!($transaction instanceof Transaction)) {
            throw new \Exception('Bad debt transaction not found');
        }
        $creditNote = $transaction->creditNote();
        if (!($creditNote instanceof CreditNote)) {
            throw new \Exception('Bad debt credit note not found');
        }

        // test credit note total
        $this->assertTrue($creditNote->getTotal()->equals(Money::fromDecimal($invoice->currency, 110)));
    }

    private function enable(string $templateId, string $option, bool $enabled = true): void
    {
        $template = new EmailTemplate();
        $template->id = $templateId;
        $template->subject = 'blah subj';
        $template->body = 'blah';
        $template->options = [$option => $enabled];
        $template->saveOrFail();
    }
}
