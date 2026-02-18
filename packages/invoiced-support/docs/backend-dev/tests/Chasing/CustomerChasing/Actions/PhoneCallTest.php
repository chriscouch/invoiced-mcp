<?php

namespace App\Tests\Chasing\CustomerChasing\Actions;

use App\AccountsReceivable\Models\Invoice;
use App\Chasing\CustomerChasing\Actions\PhoneCall;
use App\Chasing\Models\ChasingCadence;
use App\Chasing\Models\ChasingCadenceStep;
use App\Chasing\Models\Task;
use App\Chasing\ValueObjects\ChasingEvent;
use App\Core\I18n\ValueObjects\Money;
use App\Tests\AppTestCase;

class PhoneCallTest extends AppTestCase
{
    private static ChasingCadence $cadence;
    private static ChasingCadenceStep $step;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        self::hasCompany();
        self::hasCustomer();

        self::$cadence = new ChasingCadence();
        self::$cadence->name = 'Test';
        self::$cadence->time_of_day = 7;
        self::$cadence->steps = [
            [
                'name' => 'First Step',
                'schedule' => 'age:0',
                'action' => ChasingCadenceStep::ACTION_MAIL,
            ],
        ];
        self::$cadence->saveOrFail();

        self::$step = self::$cadence->getSteps()[0];
    }

    public function testExecute(): void
    {
        $action = new PhoneCall();

        $balance = new Money('usd', 100);
        $pastDueBalance = new Money('usd', 0);
        $invoices = [new Invoice()];
        $event = new ChasingEvent(self::$customer, $balance, $pastDueBalance, $invoices, self::$step);

        $actionResult = $action->execute($event);
        $this->assertTrue($actionResult->isSuccessful());
        $this->assertNull($actionResult->getMessage());

        $collectionTask = Task::where('customer_id', self::$customer->id())
            ->where('action', 'phone')
            ->where('complete', false)
            ->oneOrNull();
        $this->assertInstanceOf(Task::class, $collectionTask);

        // running again should not duplicate the task
        $actionResult = $action->execute($event);
        $this->assertTrue($actionResult->isSuccessful());
        $this->assertNull($actionResult->getMessage());

        $n = Task::where('customer_id', self::$customer->id())
            ->where('action', 'phone')
            ->where('complete', false)
            ->count();
        $this->assertEquals(1, $n);
    }

    public function testExecuteAccountOwner(): void
    {
        self::$customer->owner = self::getService('test.user_context')->get();
        self::$customer->saveOrFail();
        Task::where('customer_id', self::$customer->id())->delete();

        $action = new PhoneCall();

        $balance = new Money('usd', 100);
        $pastDueBalance = new Money('usd', 0);
        $invoices = [new Invoice()];
        $event = new ChasingEvent(self::$customer, $balance, $pastDueBalance, $invoices, self::$step);

        $actionResult = $action->execute($event);
        $this->assertTrue($actionResult->isSuccessful());
        $this->assertNull($actionResult->getMessage());

        $collectionTask = Task::where('customer_id', self::$customer->id())
            ->where('action', 'phone')
            ->where('complete', false)
            ->where('user_id', self::getService('test.user_context')->get())
            ->oneOrNull();
        $this->assertInstanceOf(Task::class, $collectionTask);

        // running again should not duplicate the task
        $actionResult = $action->execute($event);
        $this->assertTrue($actionResult->isSuccessful());
        $this->assertNull($actionResult->getMessage());

        $n = Task::where('customer_id', self::$customer->id())
            ->where('action', 'phone')
            ->where('complete', false)
            ->where('user_id', self::getService('test.user_context')->get())
            ->count();
        $this->assertEquals(1, $n);
    }
}
