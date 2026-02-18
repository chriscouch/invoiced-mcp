<?php

namespace App\Tests\Automations\Actions;

use App\Automations\Actions\CopyPropertyValueAction;
use App\Automations\Enums\AutomationResult;
use App\Automations\Models\AutomationWorkflow;
use App\Automations\ValueObjects\AutomationContext;
use App\Tests\AppTestCase;

class CopyPropertyValueActionTest extends AppTestCase
{
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        self::hasCompany();
        self::hasCustomer();
    }

    public function testPerform(): void
    {
        self::hasInvoice();
        $action = new CopyPropertyValueAction();
        $context = new AutomationContext(self::$invoice, new AutomationWorkflow());
        $settings = (object) [
            'name' => 'number',
            'value' => 'number',
            'object_type' => 'customer',
        ];

        $response = $action->perform($settings, $context);

        $this->assertEquals(AutomationResult::Succeeded, $response->result);
        $this->assertEquals(self::$invoice->number, self::$customer->refresh()->number);
    }
}
