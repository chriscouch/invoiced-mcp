<?php

namespace App\Tests\Automations\Actions;

use App\AccountsReceivable\Models\Customer;
use App\Automations\Actions\DeleteObjectAction;
use App\Automations\Enums\AutomationResult;
use App\Automations\Models\AutomationWorkflow;
use App\Automations\ValueObjects\AutomationContext;
use App\Tests\AppTestCase;

class DeleteActionTest extends AppTestCase
{
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        self::hasCompany();
        self::hasCustomer();
    }

    public function testPerformFail(): void
    {
        self::hasInvoice();
        $action = new DeleteObjectAction();

        $settings = (object) [];
        $context = new AutomationContext(self::$customer, new AutomationWorkflow());
        $response = $action->perform($settings, $context);

        $this->assertEquals(AutomationResult::Failed, $response->result);
        $this->assertEquals('Could not delete customer', $response->errorMessage);
    }

    public function testVoid(): void
    {
        self::hasInvoice();
        $action = new DeleteObjectAction();
        $context = new AutomationContext(self::$invoice, new AutomationWorkflow());
        $settings = (object) [];

        $response = $action->perform($settings, $context);

        $this->assertEquals(AutomationResult::Succeeded, $response->result);
        $this->assertTrue(self::$invoice->refresh()->voided);
    }

    public function testDelete(): void
    {
        self::hasCustomer();
        $action = new DeleteObjectAction();
        $context = new AutomationContext(self::$customer, new AutomationWorkflow());
        $settings = (object) [];

        $response = $action->perform($settings, $context);

        $this->assertEquals(AutomationResult::Succeeded, $response->result);
        $this->assertNull(Customer::find(self::$customer->id));
    }
}
