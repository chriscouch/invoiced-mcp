<?php

namespace App\Tests\AccountsReceivable\EmailVariables;

use App\AccountsReceivable\EmailVariables\EstimateEmailVariables;
use App\AccountsReceivable\Models\Estimate;
use App\Sending\Email\Libs\DocumentEmailTemplateFactory;
use App\Tests\AppTestCase;

class EstimateEmailVariablesTest extends AppTestCase
{
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        self::hasCompany();
        self::hasCustomer();
    }

    protected function getDocument(): Estimate
    {
        $estimate = new Estimate();
        $estimate->tenant_id = (int) self::$company->id();
        $estimate->setCustomer(self::$customer);
        $estimate->number = 'EST-00001';
        $estimate->date = (int) mktime(0, 0, 0, 6, 12, 2014);
        $estimate->payment_terms = 'NET 30';
        $estimate->total = 101.80;
        $estimate->currency = 'eur';
        $estimate->notes = 'test';
        $estimate->client_id = 'test_client_id';
        $estimate->discounts = [['amount' => 5.1]];

        return $estimate;
    }

    public function testGenerate(): void
    {
        $estimate = $this->getDocument();
        $generator = new EstimateEmailVariables($estimate);

        $url = 'http://invoiced.localhost:1234/estimates/'.self::$company->identifier.'/test_client_id';
        $expected = [
            'company_name' => 'TEST',
            'company_username' => self::$company->username,
            'company_address' => "Company\nAddress\nAustin, TX 78701",
            'company_email' => 'test@example.com',
            'customer_name' => 'Sherlock',
            'customer_contact_name' => 'Sherlock',
            'customer_number' => 'CUST-00001',
            'customer_address' => "Test\nAddress\nAustin, TX 78701",
            'estimate_number' => 'EST-00001',
            'estimate_date' => 'Jun 12, 2014',
            'payment_terms' => 'NET 30',
            'expiration_date' => null,
            'purchase_order' => null,
            'total' => '€101.80',
            'discounts' => '€5.10',
            'notes' => 'test',
            'view_estimate_button' => '<center style="width: 100%; min-width: 532px;" class=""><table class="button radius" align="center" style="border-spacing: 0; border-collapse: collapse; padding: 0; vertical-align: top; text-align: left; width: auto; margin: 0 0 16px 0; Margin: 0 0 16px 0;"><tbody class=""><tr style="padding: 0; vertical-align: top; text-align: left;" class=""><td style="word-wrap: break-word; -webkit-hyphens: auto; -moz-hyphens: auto; hyphens: auto; border-collapse: collapse !important; vertical-align: top; color: #ffffff; font-family: Helvetica, Arial, sans-serif; font-weight: normal; padding: 0; margin: 0; Margin: 0; text-align: left; font-size: 16px; line-height: 1.3;" class=""><table style="border-spacing: 0; border-collapse: collapse; padding: 0; vertical-align: top; text-align: left; width: 100%;" class=""><tbody class=""><tr style="padding: 0; vertical-align: top; text-align: left;" class=""><td style="word-wrap: break-word; -webkit-hyphens: auto; -moz-hyphens: auto; hyphens: auto; border-collapse: collapse !important; vertical-align: top; font-family: Helvetica, Arial, sans-serif; font-weight: normal; padding: 0; margin: 0; Margin: 0; font-size: 16px; line-height: 1.3; text-align: left; color: #ffffff; background: #348eda; border-radius: 3px; border: none;" class=""><a href="'.$url.'" style="margin: 0; Margin: 0; text-align: left; line-height: 1.3; font-family: Helvetica, Arial, sans-serif; font-size: 16px; font-weight: bold; color: #ffffff; text-decoration: none; display: inline-block; padding: 8px 16px 8px 16px; border: 0 solid #348eda; border-radius: 3px;" class="">View Estimate<plainTextOnly>: '.$url.'</plainTextOnly></a></td></tr></tbody></table></td></tr></tbody></table></center>',
            'url' => $url,
            'estimate' => [
                'metadata' => [],
            ],
            'customer' => [
                'metadata' => [],
                'id' => self::$customer->id,
            ],
        ];

        $emailTemplate = (new DocumentEmailTemplateFactory())->get($estimate);
        $variables = $generator->generate($emailTemplate);
        $this->assertEquals($expected, $variables);
    }

    public function testAvailableVariables(): void
    {
        $estimate = $this->getDocument();
        $generator = new EstimateEmailVariables($estimate);
        $template = (new DocumentEmailTemplateFactory())->get($estimate);

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
