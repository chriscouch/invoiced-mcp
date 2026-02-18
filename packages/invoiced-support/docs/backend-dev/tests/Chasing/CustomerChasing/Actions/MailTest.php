<?php

namespace App\Tests\Chasing\CustomerChasing\Actions;

use App\AccountsReceivable\Models\Invoice;
use App\Chasing\CustomerChasing\Actions\Mail;
use App\Chasing\Models\ChasingCadence;
use App\Chasing\Models\ChasingCadenceStep;
use App\Chasing\ValueObjects\ChasingEvent;
use App\Core\I18n\ValueObjects\Money;
use App\Sending\Mail\Libs\LetterSender;
use App\Tests\AppTestCase;
use Mockery;

class MailTest extends AppTestCase
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
        $sender = Mockery::mock(LetterSender::class);
        $sender->shouldReceive('send');
        $action = new Mail($sender, self::getService('test.statement_builder'));

        $balance = new Money('usd', 100);
        $pastDueBalance = new Money('usd', 0);
        $invoices = [new Invoice()];
        $event = new ChasingEvent(self::$customer, $balance, $pastDueBalance, $invoices, self::$step);

        $actionResult = $action->execute($event);
        $this->assertTrue($actionResult->isSuccessful());
        $this->assertNull($actionResult->getMessage());
    }
}
