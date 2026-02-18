<?php

namespace App\Tests\Chasing\CustomerChasing\Actions;

use App\AccountsReceivable\Models\Invoice;
use App\Chasing\CustomerChasing\Actions\SMS;
use App\Chasing\Models\ChasingCadence;
use App\Chasing\Models\ChasingCadenceStep;
use App\Chasing\ValueObjects\ChasingEvent;
use App\Core\I18n\ValueObjects\Money;
use App\Sending\Sms\Libs\TextMessageSender;
use App\Sending\Sms\Models\SmsTemplate;
use App\Tests\AppTestCase;
use Mockery;

class SMSTest extends AppTestCase
{
    private static ChasingCadence $cadence;
    private static ChasingCadenceStep $step;
    private static SmsTemplate $smsTemplate;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        self::hasCompany();
        self::hasCustomer();

        self::$smsTemplate = new SmsTemplate();
        self::$smsTemplate->name = 'Test';
        self::$smsTemplate->message = 'Your account is past due';
        self::$smsTemplate->saveOrFail();

        self::$cadence = new ChasingCadence();
        self::$cadence->name = 'Test';
        self::$cadence->time_of_day = 7;
        self::$cadence->steps = [
            [
                'name' => 'First Step',
                'schedule' => 'age:0',
                'action' => ChasingCadenceStep::ACTION_SMS,
                'sms_template_id' => self::$smsTemplate->id(),
            ],
        ];
        self::$cadence->saveOrFail();

        self::$step = self::$cadence->getSteps()[0];
    }

    private function getAction(?TextMessageSender $sender = null): SMS
    {
        $sender ??= Mockery::mock(TextMessageSender::class);

        return new SMS($sender);
    }

    public function testGetVariables(): void
    {
        $balance = new Money('usd', 100);
        $pastDueBalance = new Money('usd', 0);
        $invoices = [new Invoice()];
        $step = new ChasingCadenceStep();
        $event = new ChasingEvent(self::$customer, $balance, $pastDueBalance, $invoices, $step);

        $action = $this->getAction();
        $variables = $action->getVariables($event);

        $this->assertEquals('Sherlock', $variables['customer_name']);
        $this->assertEquals('CUST-00001', $variables['customer_number']);
        $this->assertTrue(isset($variables['url']));
    }

    public function testExecuteMustache(): void
    {
        $sender = Mockery::mock(TextMessageSender::class);
        $sender->shouldReceive('send');
        $action = $this->getAction($sender);

        $balance = new Money('usd', 100);
        $pastDueBalance = new Money('usd', 0);
        $invoices = [new Invoice()];
        $event = new ChasingEvent(self::$customer, $balance, $pastDueBalance, $invoices, self::$step);

        $actionResult = $action->execute($event);
        $this->assertTrue($actionResult->isSuccessful());
        $this->assertNull($actionResult->getMessage());
    }

    public function testExecuteTwig(): void
    {
        self::$smsTemplate->template_engine = 'twig';
        self::$smsTemplate->saveOrFail();

        $sender = Mockery::mock(TextMessageSender::class);
        $sender->shouldReceive('send');
        $action = $this->getAction($sender);

        $balance = new Money('usd', 100);
        $pastDueBalance = new Money('usd', 0);
        $invoices = [new Invoice()];
        $event = new ChasingEvent(self::$customer, $balance, $pastDueBalance, $invoices, self::$step);

        $actionResult = $action->execute($event);
        $this->assertTrue($actionResult->isSuccessful());
        $this->assertNull($actionResult->getMessage());
    }
}
