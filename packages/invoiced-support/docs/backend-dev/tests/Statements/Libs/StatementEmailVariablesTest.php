<?php

namespace App\Tests\Statements\Libs;

use App\Statements\Libs\BalanceForwardStatement;
use App\Statements\EmailVariables\StatementEmailVariables;
use App\Sending\Email\Libs\DocumentEmailTemplateFactory;
use App\Tests\AppTestCase;

class StatementEmailVariablesTest extends AppTestCase
{
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        self::hasCompany();
        self::hasCustomer();
        self::hasInvoice();
        self::hasCredit();
    }

    public function getStatement(): BalanceForwardStatement
    {
        return self::getService('test.statement_builder')->balanceForward(self::$customer);
    }

    public function testGenerate(): void
    {
        $statement = $this->getStatement();
        $generator = new StatementEmailVariables($statement);

        $url = self::$customer->statement_url.'?currency=usd&type=balance_forward&start='.$statement->start.'&end='.$statement->end;

        $expected = [
            'company_name' => 'TEST',
            'company_username' => self::$company->username,
            'company_address' => "Company\nAddress\nAustin, TX 78701",
            'company_email' => 'test@example.com',
            'customer_name' => 'Sherlock',
            'customer_contact_name' => 'Sherlock',
            'customer_number' => 'CUST-00001',
            'customer_address' => "Test\nAddress\nAustin, TX 78701",
            'statement_url' => $url,
            'statement_start_date' => date('M 1, Y'),
            'statement_end_date' => date('M j, Y'),
            'statement_balance' => '$100.00',
            'statement_credit_balance' => '$100.00',
            'view_statement_button' => '<center style="width: 100%; min-width: 532px;" class=""><table class="button radius" align="center" style="border-spacing: 0; border-collapse: collapse; padding: 0; vertical-align: top; text-align: left; width: auto; margin: 0 0 16px 0; Margin: 0 0 16px 0;"><tbody class=""><tr style="padding: 0; vertical-align: top; text-align: left;" class=""><td style="word-wrap: break-word; -webkit-hyphens: auto; -moz-hyphens: auto; hyphens: auto; border-collapse: collapse !important; vertical-align: top; color: #ffffff; font-family: Helvetica, Arial, sans-serif; font-weight: normal; padding: 0; margin: 0; Margin: 0; text-align: left; font-size: 16px; line-height: 1.3;" class=""><table style="border-spacing: 0; border-collapse: collapse; padding: 0; vertical-align: top; text-align: left; width: 100%;" class=""><tbody class=""><tr style="padding: 0; vertical-align: top; text-align: left;" class=""><td style="word-wrap: break-word; -webkit-hyphens: auto; -moz-hyphens: auto; hyphens: auto; border-collapse: collapse !important; vertical-align: top; font-family: Helvetica, Arial, sans-serif; font-weight: normal; padding: 0; margin: 0; Margin: 0; font-size: 16px; line-height: 1.3; text-align: left; color: #ffffff; background: #348eda; border-radius: 3px; border: none;" class=""><a href="'.$url.'" style="margin: 0; Margin: 0; text-align: left; line-height: 1.3; font-family: Helvetica, Arial, sans-serif; font-size: 16px; font-weight: bold; color: #ffffff; text-decoration: none; display: inline-block; padding: 8px 16px 8px 16px; border: 0 solid #348eda; border-radius: 3px;" class="">View Statement<plainTextOnly>: '.$url.'</plainTextOnly></a></td></tr></tbody></table></td></tr></tbody></table></center>',
            'customer' => [
                'metadata' => [],
                'id' => self::$customer->id,
            ],
        ];

        $emailTemplate = (new DocumentEmailTemplateFactory())->get($statement);
        $variables = $generator->generate($emailTemplate);
        $this->assertEquals($expected, $variables);
    }

    public function testAvailableVariables(): void
    {
        $statement = $this->getStatement();
        $generator = new StatementEmailVariables($statement);
        $template = (new DocumentEmailTemplateFactory())->get($statement);

        $variables = array_keys($generator->generate($template));

        // verify the variables match the email template
        // (minus the mustaches)
        $expected = $template->getAvailableVariables(false);

        $missingFromVariables = array_diff($expected, $variables);
        $this->assertEquals([], $missingFromVariables, 'These variables were missing on the model side for '.$template->id);

        $missingFromTemplate = array_diff($variables, $expected);
        $this->assertEquals([], $missingFromTemplate, 'These variables were missing on the template side for '.$template->id);
    }
}
