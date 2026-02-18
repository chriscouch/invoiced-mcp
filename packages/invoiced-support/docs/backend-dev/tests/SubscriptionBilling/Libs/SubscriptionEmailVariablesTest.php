<?php

namespace App\Tests\SubscriptionBilling\Libs;

use App\SubscriptionBilling\Models\Plan;
use App\Core\Utils\ValueObjects\Interval;
use App\Sending\Email\Models\EmailTemplate;
use App\SubscriptionBilling\EmailVariables\SubscriptionEmailVariables;
use App\SubscriptionBilling\Models\Subscription;
use App\Tests\AppTestCase;

class SubscriptionEmailVariablesTest extends AppTestCase
{
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        self::hasCompany();
        self::hasCustomer();
    }

    protected function getSubscription(): Subscription
    {
        $plan = new Plan();
        $plan->tenant_id = (int) self::$company->id();
        $plan->currency = 'usd';
        $plan->interval_count = 1;
        $plan->interval = Interval::MONTH;
        $plan->amount = 207.90;
        $plan->name = 'Test';

        $subscription = new Subscription();
        $subscription->tenant_id = (int) self::$company->id();
        $subscription->setCustomer(self::$customer);
        $subscription->client_id = 'test_client_id';
        $subscription->start_date = time();
        $subscription->recurring_total = 207.9;
        $subscription->setPlan($plan);

        return $subscription;
    }

    public function testGenerate(): void
    {
        $subscription = $this->getSubscription();
        $generator = new SubscriptionEmailVariables($subscription);

        $url = 'http://'.self::$company->username.'.invoiced.localhost:1234/subscriptions/test_client_id';
        $expected = [
            'company_name' => 'TEST',
            'company_username' => self::$company->username,
            'company_address' => "Company\nAddress\nAustin, TX 78701",
            'company_email' => 'test@example.com',
            'customer_name' => 'Sherlock',
            'customer_contact_name' => 'Sherlock',
            'customer_number' => 'CUST-00001',
            'customer_address' => "Test\nAddress\nAustin, TX 78701",
            'name' => 'Test',
            'recurring_total' => '$207.90 monthly',
            'frequency' => 'monthly',
            'start_date' => date('M j, Y'),
            'manage_subscription_button' => '<center style="width: 100%; min-width: 532px;" class=""><table class="button radius" align="center" style="border-spacing: 0; border-collapse: collapse; padding: 0; vertical-align: top; text-align: left; width: auto; margin: 0 0 16px 0; Margin: 0 0 16px 0;"><tbody class=""><tr style="padding: 0; vertical-align: top; text-align: left;" class=""><td style="word-wrap: break-word; -webkit-hyphens: auto; -moz-hyphens: auto; hyphens: auto; border-collapse: collapse !important; vertical-align: top; color: #ffffff; font-family: Helvetica, Arial, sans-serif; font-weight: normal; padding: 0; margin: 0; Margin: 0; text-align: left; font-size: 16px; line-height: 1.3;" class=""><table style="border-spacing: 0; border-collapse: collapse; padding: 0; vertical-align: top; text-align: left; width: 100%;" class=""><tbody class=""><tr style="padding: 0; vertical-align: top; text-align: left;" class=""><td style="word-wrap: break-word; -webkit-hyphens: auto; -moz-hyphens: auto; hyphens: auto; border-collapse: collapse !important; vertical-align: top; font-family: Helvetica, Arial, sans-serif; font-weight: normal; padding: 0; margin: 0; Margin: 0; font-size: 16px; line-height: 1.3; text-align: left; color: #ffffff; background: #348eda; border-radius: 3px; border: none;" class=""><a href="'.$url.'" style="margin: 0; Margin: 0; text-align: left; line-height: 1.3; font-family: Helvetica, Arial, sans-serif; font-size: 16px; font-weight: bold; color: #ffffff; text-decoration: none; display: inline-block; padding: 8px 16px 8px 16px; border: 0 solid #348eda; border-radius: 3px;" class="">Manage Subscription<plainTextOnly>: '.$url.'</plainTextOnly></a></td></tr></tbody></table></td></tr></tbody></table></center>',
            'url' => $url,
            'customer' => [
                'metadata' => [],
                'id' => self::$customer->id,
            ],
        ];

        $emailTemplate = EmailTemplate::make(self::$company->id, EmailTemplate::SUBSCRIPTION_CONFIRMATION);
        $variables = $generator->generate($emailTemplate);
        $this->assertEquals($expected, $variables);
    }

    public function testGenerateRenewsSoon(): void
    {
        $subscription = $this->getSubscription();
        $subscription->renews_next = strtotime('+1 week') + 30;
        $generator = new SubscriptionEmailVariables($subscription);

        $url = 'http://'.self::$company->username.'.invoiced.localhost:1234/subscriptions/test_client_id';
        $expected = [
            'company_name' => 'TEST',
            'company_username' => self::$company->username,
            'company_address' => "Company\nAddress\nAustin, TX 78701",
            'company_email' => 'test@example.com',
            'customer_name' => 'Sherlock',
            'customer_contact_name' => 'Sherlock',
            'customer_number' => 'CUST-00001',
            'customer_address' => "Test\nAddress\nAustin, TX 78701",
            'name' => 'Test',
            'recurring_total' => '$207.90 monthly',
            'frequency' => 'monthly',
            'start_date' => date('M j, Y'),
            'manage_subscription_button' => '<center style="width: 100%; min-width: 532px;" class=""><table class="button radius" align="center" style="border-spacing: 0; border-collapse: collapse; padding: 0; vertical-align: top; text-align: left; width: auto; margin: 0 0 16px 0; Margin: 0 0 16px 0;"><tbody class=""><tr style="padding: 0; vertical-align: top; text-align: left;" class=""><td style="word-wrap: break-word; -webkit-hyphens: auto; -moz-hyphens: auto; hyphens: auto; border-collapse: collapse !important; vertical-align: top; color: #ffffff; font-family: Helvetica, Arial, sans-serif; font-weight: normal; padding: 0; margin: 0; Margin: 0; text-align: left; font-size: 16px; line-height: 1.3;" class=""><table style="border-spacing: 0; border-collapse: collapse; padding: 0; vertical-align: top; text-align: left; width: 100%;" class=""><tbody class=""><tr style="padding: 0; vertical-align: top; text-align: left;" class=""><td style="word-wrap: break-word; -webkit-hyphens: auto; -moz-hyphens: auto; hyphens: auto; border-collapse: collapse !important; vertical-align: top; font-family: Helvetica, Arial, sans-serif; font-weight: normal; padding: 0; margin: 0; Margin: 0; font-size: 16px; line-height: 1.3; text-align: left; color: #ffffff; background: #348eda; border-radius: 3px; border: none;" class=""><a href="'.$url.'" style="margin: 0; Margin: 0; text-align: left; line-height: 1.3; font-family: Helvetica, Arial, sans-serif; font-size: 16px; font-weight: bold; color: #ffffff; text-decoration: none; display: inline-block; padding: 8px 16px 8px 16px; border: 0 solid #348eda; border-radius: 3px;" class="">Manage Subscription<plainTextOnly>: '.$url.'</plainTextOnly></a></td></tr></tbody></table></td></tr></tbody></table></center>',
            'url' => $url,
            'time_until_renewal' => '7 days',
            'customer' => [
                'metadata' => [],
                'id' => self::$customer->id,
            ],
        ];

        $t = EmailTemplate::SUBSCRIPTION_BILLED_SOON;
        $template = EmailTemplate::make(self::$company->id, $t);
        $variables = $generator->generate($template);
        $this->assertEquals($expected, $variables);
    }

    public function testAvailableVariables(): void
    {
        $subscription = $this->getSubscription();
        $generator = new SubscriptionEmailVariables($subscription);

        // these templates should all have the same variables
        $templates = [
            EmailTemplate::SUBSCRIPTION_CONFIRMATION,
            EmailTemplate::SUBSCRIPTION_CANCELED,
            EmailTemplate::SUBSCRIPTION_BILLED_SOON,
        ];
        foreach ($templates as $t) {
            $template = EmailTemplate::make(self::$company->id, $t);

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
}
