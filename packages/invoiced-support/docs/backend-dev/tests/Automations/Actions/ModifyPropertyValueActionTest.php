<?php

namespace App\Tests\Automations\Actions;

use App\AccountsReceivable\Models\InvoiceDelivery;
use App\Automations\Actions\ClearPropertyValueAction;
use App\Automations\Actions\ModifyPropertyValueAction;
use App\Automations\Enums\AutomationResult;
use App\Automations\Models\AutomationWorkflow;
use App\Automations\ValueObjects\AutomationContext;
use App\Chasing\Models\InvoiceChasingCadence;
use App\Core\Utils\Enums\ObjectType;
use App\Tests\AppTestCase;

class ModifyPropertyValueActionTest extends AppTestCase
{
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        self::hasCompany();
        self::hasCustomer();
    }

    public function testPerform(): void
    {
        $action = new ModifyPropertyValueAction();
        $context = new AutomationContext(self::$customer, new AutomationWorkflow());
        $settings = (object) [
            'object_type' => 'customer',
            'name' => 'number',
            'value' => 'test',
        ];

        $response = $action->perform($settings, $context);

        $this->assertEquals(AutomationResult::Succeeded, $response->result);
        $this->assertEquals('test', self::$customer->refresh()->number);
    }

    public function testPerformChasing(): void
    {
        $options = [
            'hour' => 4,
            'email' => true,
            'sms' => false,
            'letter' => false,
        ];

        self::hasInvoice();
        $cadence1 = new InvoiceChasingCadence();
        $cadence1->name = 'Chasing Cadence';
        $cadence1->default = true;
        $cadence1->chase_schedule = [
            [
                'trigger' => InvoiceChasingCadence::ON_ISSUE,
                'options' => $options,
            ],
        ];
        $cadence1->saveOrFail();

        $action = new ModifyPropertyValueAction();
        $context = new AutomationContext(self::$invoice, new AutomationWorkflow());
        $settings = (object) [
            'object_type' => 'invoice',
            'name' => 'delivery',
            'value' => '{"cadence_id": '.$cadence1->id.'}',
        ];

        $response = $action->perform($settings, $context);

        $this->assertEquals(AutomationResult::Succeeded, $response->result);
        /** @var InvoiceDelivery[] $deliveries */
        $deliveries = InvoiceDelivery::where('invoice_id', self::$invoice->id)->execute();
        $this->assertCount(1, $deliveries);
        $this->assertEquals($options, $deliveries[0]->chase_schedule[0]['options']);
        $this->assertFalse($deliveries[0]->disabled);

        // test clear
        $action = new ClearPropertyValueAction();
        $settings = (object) [
            'object_type' => 'invoice',
            'name' => 'delivery',
        ];
        $response = $action->perform($settings, $context);
        $this->assertEquals(AutomationResult::Succeeded, $response->result);
        $this->assertTrue($deliveries[0]->refresh()->disabled);
    }

    /**
     * unit test for ModifyPropertyValueAction::validateSettings.
     */
    public function testValidateSettings(): void
    {
        $action = new ModifyPropertyValueAction();
        // no object_type
        $settings = (object) [];

        try {
            $action->validateSettings($settings, ObjectType::Attachment);
            $this->fail('Expected exception not thrown');
        } catch (\Exception $e) {
            $this->assertEquals('Missing target object', $e->getMessage());
        }

        $settings->object_type = 'customer';
        try {
            $action->validateSettings($settings, ObjectType::Attachment);
            $this->fail('Expected exception not thrown');
        } catch (\Exception $e) {
            $this->assertEquals('Missing `name` property', $e->getMessage());
        }

        $settings->name = 'test';
        try {
            $action->validateSettings($settings, ObjectType::Invoice);
            $this->fail('Expected exception not thrown');
        } catch (\Exception $e) {
            $this->assertEquals('Missing `value` property', $e->getMessage());
        }

        $settings->value = 'test';
        try {
            $action->validateSettings($settings, ObjectType::Invoice);
            $this->fail('Expected exception not thrown');
        } catch (\Exception $e) {
            $this->assertEquals('Field `test` is not writable', $e->getMessage());
        }

        $settings->name = 'company';
        try {
            $action->validateSettings($settings, ObjectType::Invoice);
            $this->fail('Expected exception not thrown');
        } catch (\Exception $e) {
            $this->assertEquals('Field `company` is not writable', $e->getMessage());
        }

        $settings->name = 'customer';
        try {
            $action->validateSettings($settings, ObjectType::Invoice);
        } catch (\Exception $e) {
            $this->assertEquals('Field `customer` is not writable', $e->getMessage());
        }
    }
}
