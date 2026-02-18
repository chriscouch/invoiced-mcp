<?php

namespace App\Tests\Chasing\CustomerChasing;

use App\AccountsReceivable\Models\Invoice;
use App\Chasing\EmailVariables\ChasingStatementEmailVariables;
use App\Chasing\Models\ChasingCadenceStep;
use App\Chasing\ValueObjects\ChasingEvent;
use App\Core\I18n\ValueObjects\Money;
use App\Sending\Email\Interfaces\EmailVariablesInterface;
use App\Sending\Email\Libs\EmailVariablesCollection;
use App\Sending\Email\Models\EmailTemplate;
use App\Statements\Enums\StatementType;
use App\Tests\AppTestCase;

class ChasingStatementEmailVariablesTest extends AppTestCase
{
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        self::hasCompany();
        self::hasCustomer();
        self::hasInvoice();
    }

    /**
     * @param Invoice[] $invoices
     */
    private function getVariables(array $invoices): EmailVariablesInterface
    {
        $balance = Money::fromDecimal('usd', self::$invoice->balance);
        $pastDueBalance = new Money('usd', 0);
        $step = new ChasingCadenceStep();
        $event = new ChasingEvent(self::$customer, $balance, $pastDueBalance, $invoices, $step);

        $collection = new EmailVariablesCollection($event->getCustomer(), $balance->currency);
        $collection->addVariables(new ChasingStatementEmailVariables($event));

        return $collection;
    }

    /**
     * @dataProvider getInvoices
     *
     * @param Invoice[] $input
     * @param string[]  $expected
     */
    public function testGenerateData(array $input, array $expected): void
    {
        $variables = $this->getVariables($input)->generate(new EmailTemplate());
        $this->assertEquals($expected['invoice_numbers'], $variables['invoice_numbers']);
        $this->assertEquals($expected['invoice_dates'], $variables['invoice_dates']);
        $this->assertEquals($expected['invoice_due_dates'], $variables['invoice_due_dates']);
    }

    public function testGenerate(): void
    {
        $variables = $this->getVariables([self::$invoice]);

        $url = self::$customer->statement_url.'?type='.StatementType::OpenItem->value;

        $expected = [
            'company_name' => 'TEST',
            'company_username' => self::$company->username,
            'company_address' => "Company\nAddress\nAustin, TX 78701",
            'company_email' => 'test@example.com',
            'customer_name' => 'Sherlock',
            'customer_contact_name' => 'Sherlock',
            'customer_number' => 'CUST-00001',
            'customer_address' => "Test\nAddress\nAustin, TX 78701",
            'account_balance' => '$100.00',
            'past_due_account_balance' => '$0.00',
            'invoice_numbers' => 'INV-00001',
            'invoice_dates' => date('M j, Y'),
            'invoice_due_dates' => '',
            'customer_portal_button' => '<center style="width: 100%; min-width: 532px;" class=""><table class="button radius" align="center" style="border-spacing: 0; border-collapse: collapse; padding: 0; vertical-align: top; text-align: left; width: auto; margin: 0 0 16px 0; Margin: 0 0 16px 0;"><tbody class=""><tr style="padding: 0; vertical-align: top; text-align: left;" class=""><td style="word-wrap: break-word; -webkit-hyphens: auto; -moz-hyphens: auto; hyphens: auto; border-collapse: collapse !important; vertical-align: top; color: #ffffff; font-family: Helvetica, Arial, sans-serif; font-weight: normal; padding: 0; margin: 0; Margin: 0; text-align: left; font-size: 16px; line-height: 1.3;" class=""><table style="border-spacing: 0; border-collapse: collapse; padding: 0; vertical-align: top; text-align: left; width: 100%;" class=""><tbody class=""><tr style="padding: 0; vertical-align: top; text-align: left;" class=""><td style="word-wrap: break-word; -webkit-hyphens: auto; -moz-hyphens: auto; hyphens: auto; border-collapse: collapse !important; vertical-align: top; font-family: Helvetica, Arial, sans-serif; font-weight: normal; padding: 0; margin: 0; Margin: 0; font-size: 16px; line-height: 1.3; text-align: left; color: #ffffff; background: #348eda; border-radius: 3px; border: none;" class=""><a href="'.$url.'" style="margin: 0; Margin: 0; text-align: left; line-height: 1.3; font-family: Helvetica, Arial, sans-serif; font-size: 16px; font-weight: bold; color: #ffffff; text-decoration: none; display: inline-block; padding: 8px 16px 8px 16px; border: 0 solid #348eda; border-radius: 3px;" class="">Pay Now<plainTextOnly>: '.$url.'</plainTextOnly></a></td></tr></tbody></table></td></tr></tbody></table></center>',
            'customer' => [
                'metadata' => [],
                'id' => self::$customer->id,
            ],
        ];

        $emailTemplate = new EmailTemplate();
        $emailTemplate->type = EmailTemplate::TYPE_CHASING;
        $this->assertEquals($expected, $variables->generate($emailTemplate));
    }

    public function getInvoices(): array
    {
        $invoice1 = new Invoice();
        $invoice1->number = 'INV-0001';
        $invoice1->date = (int) mktime(0, 0, 0, 8, 1, 2018);
        $invoice1->due_date = (int) mktime(0, 0, 0, 9, 1, 2018);
        $invoice2 = new Invoice();
        $invoice2->number = 'INV-0002';
        $invoice2->date = (int) mktime(0, 0, 0, 8, 2, 2018);
        $invoice2->due_date = (int) mktime(0, 0, 0, 9, 2, 2018);
        $invoice3 = new Invoice();
        $invoice3->number = 'INV-0003';
        $invoice3->date = (int) mktime(0, 0, 0, 8, 3, 2018);
        $invoice3->due_date = (int) mktime(0, 0, 0, 9, 3, 2018);
        $invoice4 = new Invoice();
        $invoice4->date = (int) mktime(0, 0, 0, 8, 4, 2018);
        $invoice4->due_date = null;

        return [
            [[], [
                'invoice_numbers' => '',
                'invoice_dates' => '',
                'invoice_due_dates' => '',
            ]],
            [[$invoice1], [
                'invoice_numbers' => 'INV-0001',
                'invoice_dates' => 'Aug 1, 2018',
                'invoice_due_dates' => 'Sep 1, 2018',
            ]],
            [[$invoice1, $invoice2], [
                'invoice_numbers' => 'INV-0001 and INV-0002',
                'invoice_dates' => 'Aug 1, 2018 and Aug 2, 2018',
                'invoice_due_dates' => 'Sep 1, 2018 and Sep 2, 2018',
            ]],
            [[$invoice1, $invoice2, $invoice3], [
                'invoice_numbers' => 'INV-0001, INV-0002, and INV-0003',
                'invoice_dates' => 'Aug 1, 2018, Aug 2, 2018, and Aug 3, 2018',
                'invoice_due_dates' => 'Sep 1, 2018, Sep 2, 2018, and Sep 3, 2018',
            ]],
            [[$invoice1, $invoice2, $invoice3, $invoice4], [
                'invoice_numbers' => 'INV-0001, INV-0002, and INV-0003',
                'invoice_dates' => 'Aug 1, 2018, Aug 2, 2018, Aug 3, 2018, and Aug 4, 2018',
                'invoice_due_dates' => 'Sep 1, 2018, Sep 2, 2018, and Sep 3, 2018',
            ]],
        ];
    }
}
