<?php

namespace App\Tests\AccountsReceivable;

use App\Chasing\Models\PromiseToPay;
use App\Core\Cron\ValueObjects\Run;
use App\Core\Statsd\StatsdClient;
use App\EntryPoint\CronJob\MarkPromiseToPayBroken;
use App\ActivityLog\Enums\EventType;
use App\ActivityLog\Libs\EventSpool;
use App\ActivityLog\Models\Event;
use App\PaymentProcessing\Models\PaymentMethod;
use App\Tests\AppTestCase;

class MarkPromiseToPayBrokenCronJobTest extends AppTestCase
{
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        self::hasCompany();
        self::hasCustomer();
    }

    public function testEvent(): void
    {
        // to be updated promise
        self::hasInvoice();
        $promiseToPay1 = $this->makePromise(time() - 100);

        // future promise
        self::hasInvoice();
        $this->makePromise(time() + 100);

        // paid invoice promise
        self::hasInvoice();
        $this->makePromise(time() - 100);
        self::hasTransaction();

        EventSpool::enable();
        $job = new MarkPromiseToPayBroken(self::getService('test.tenant'));
        $job->setStatsd(new StatsdClient());
        $job->execute(new Run());
        self::getService('test.event_spool')->flush();

        self::getService('test.tenant')->set(self::$company);
        $events = Event::where('type_id', EventType::PromiseToPayBroken->toInteger())->execute();
        $this->assertCount(1, $events);
        $this->assertEquals($events[0]->object_id, $promiseToPay1->id);
    }

    private function makePromise(int $time): PromiseToPay
    {
        self::hasInvoice();
        $promiseToPay = new PromiseToPay();

        $promiseToPay->create([
            'customer' => self::$customer,
            'invoice' => self::$invoice,
            'method' => PaymentMethod::CHECK,
            'date' => $time,
            'currency' => 'usd',
            'amount' => 100,
        ]);

        return $promiseToPay;
    }
}
