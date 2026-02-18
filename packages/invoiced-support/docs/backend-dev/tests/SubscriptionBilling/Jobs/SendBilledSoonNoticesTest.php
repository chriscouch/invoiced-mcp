<?php

namespace App\Tests\SubscriptionBilling\Jobs;

use App\EntryPoint\CronJob\SendBilledSoonNotices;
use App\Sending\Email\Models\EmailTemplate;
use App\Sending\Email\Models\EmailTemplateOption;
use App\Tests\AppTestCase;
use App\Core\Orm\Iterator;

class SendBilledSoonNoticesTest extends AppTestCase
{
    public static function setUpBeforeClass(): void
    {
        self::hasCompany();

        // enable subscription confirmation email
        $emailTemplate = new EmailTemplate();
        $emailTemplate->id = EmailTemplate::SUBSCRIPTION_BILLED_SOON;
        $emailTemplate->subject = 'subject';
        $emailTemplate->body = 'test';
        $options = $emailTemplate->options;
        $options[EmailTemplateOption::DAYS_BEFORE_BILLING] = 2;
        $emailTemplate->options = $options;
        $emailTemplate->saveOrFail();
    }

    public function testGetWithNotifications(): void
    {
        $job = new SendBilledSoonNotices(self::getService('test.tenant'), self::getService('test.email_spool'), self::getService('test.lock_factory'));
        $options = $job->getTasks();
        $this->assertInstanceOf(Iterator::class, $options);

        $ids = [];
        foreach ($options as $option) {
            $this->assertInstanceOf(EmailTemplateOption::class, $option);
            $ids[] = $option->tenant_id;
        }

        $this->assertTrue(in_array(self::$company->id(), $ids));
    }
}
