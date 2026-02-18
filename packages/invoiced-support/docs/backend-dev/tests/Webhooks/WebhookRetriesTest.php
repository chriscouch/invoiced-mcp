<?php

namespace App\Tests\Webhooks;

use App\Core\Cron\ValueObjects\Run;
use App\Core\Statsd\StatsdClient;
use App\EntryPoint\CronJob\WebhookRetries;
use App\Tests\AppTestCase;
use App\Webhooks\Models\WebhookAttempt;

class WebhookRetriesTest extends AppTestCase
{
    private static WebhookAttempt $attempt;

    public static function setUpBeforeClass(): void
    {
        self::hasCompany();
        self::hasCustomer();

        // create a webhook attempt
        self::$attempt = new WebhookAttempt();
        self::$attempt->event_id = -1;
        self::$attempt->url = 'https://example.com/webhook';
        self::$attempt->next_attempt = time() - 1;
        self::$attempt->save();

        // create one not scheduled
        $attempt2 = new WebhookAttempt();
        $attempt2->event_id = -1;
        $attempt2->url = 'https://example.com/webhook';
        $attempt2->next_attempt = null;
        $attempt2->save();

        // create one scheduled in the future
        $attempt3 = new WebhookAttempt();
        $attempt3->event_id = -1;
        $attempt3->url = 'https://example.com/webhook';
        $attempt3->next_attempt = strtotime('+1 hour');
        $attempt3->save();
    }

    private function getJob(): WebhookRetries
    {
        $job = new WebhookRetries(self::getService('test.tenant'), self::getService('test.queue'));
        $job->setStatsd(new StatsdClient());

        return $job;
    }

    public function testGetAttempts(): void
    {
        $job = $this->getJob();
        $attempts = $job->getAttempts();

        $ids = [];
        foreach ($attempts->all() as $attempt) {
            $this->assertInstanceOf(WebhookAttempt::class, $attempt);
            $ids[] = $attempt->id();
        }

        $this->assertEquals([self::$attempt->id()], $ids);
    }

    public function testRun(): void
    {
        $job = $this->getJob();

        // send out scheduled attempts
        $job->execute(new Run());
        $this->assertEquals(1, $job->getTaskCount());

        self::$attempt->next_attempt = null;
        $this->assertTrue(self::$attempt->save());

        // retry again
        // nothing should happen
        $job->execute(new Run());
        $this->assertEquals(0, $job->getTaskCount());
    }
}
