<?php

namespace App\Tests\Automations\Actions;

use App\Automations\Actions\ClearPropertyValueAction;
use App\Automations\Enums\AutomationResult;
use App\Automations\Models\AutomationWorkflow;
use App\Automations\ValueObjects\AutomationContext;
use App\Tests\AppTestCase;

class ClearPropertyValueActionTest extends AppTestCase
{
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        self::hasCompany();
        self::hasCustomer();
        self::$customer->email = 'email@email.com';
        self::$customer->saveOrFail();
    }

    public function testPerform(): void
    {
        $this->assertEquals('email@email.com', self::$customer->refresh()->email);

        $action = new ClearPropertyValueAction();
        $context = new AutomationContext(self::$customer, new AutomationWorkflow());
        $settings = (object) [
            'name' => 'email',
            'object_type' => 'customer',
        ];

        $response = $action->perform($settings, $context);

        $this->assertEquals(AutomationResult::Succeeded, $response->result);
        $this->assertNull(self::$customer->refresh()->email);
    }
}
