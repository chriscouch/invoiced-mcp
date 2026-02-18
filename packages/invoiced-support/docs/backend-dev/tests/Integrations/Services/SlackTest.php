<?php

namespace App\Tests\Integrations\Services;

use App\Automations\Enums\AutomationActionType;
use App\Automations\Enums\AutomationTriggerType;
use App\Automations\Models\AutomationWorkflow;
use App\Automations\Models\AutomationWorkflowStep;
use App\Automations\Models\AutomationWorkflowTrigger;
use App\ActivityLog\Enums\EventType;
use App\Integrations\Enums\IntegrationType;
use App\Integrations\Services\Slack;
use App\Integrations\Slack\SlackAccount;
use App\Notifications\Models\Notification;
use App\Tests\AppTestCase;

class SlackTest extends AppTestCase
{
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        self::hasCompany();
    }

    public function testIsAccountingIntegration(): void
    {
        $integration = $this->getIntegration();
        $this->assertFalse($integration->isAccountingIntegration());
    }

    public function testIsConnected(): void
    {
        $integration = $this->getIntegration();
        $this->assertFalse($integration->isConnected());

        self::hasSlackAccount();
        $integration = $this->getIntegration();
        $this->assertTrue($integration->isConnected());
    }

    /**
     * @depends testIsConnected
     */
    public function testGetConnectedName(): void
    {
        $integration = $this->getIntegration();
        $this->assertEquals('Test Slack Account', $integration->getConnectedName());
    }

    /**
     * @depends testIsConnected
     */
    public function testGetExtra(): void
    {
        $integration = $this->getIntegration();
        $expected = [
            'webhook_channel' => '#general',
            'webhook_config_url' => 'http://example.com/settings',
        ];
        $this->assertEquals((object) $expected, $integration->getExtra());
    }

    /**
     * @depends testIsConnected
     */
    public function testDisconnect(): void
    {
        $integration = $this->getIntegration();
        $integration->disconnect();

        $this->assertFalse($integration->isConnected());
        $account = SlackAccount::queryWithTenant(self::$company)->oneOrNull();
        $this->assertNull($account);
    }

    private function getIntegration(): Slack
    {
        return self::getService('test.integration_factory')->get(IntegrationType::Slack, self::$company);
    }

    public function testDisconnectWithMigration(): void
    {
        $account = new SlackAccount();
        $account->team_id = 'client_id';
        $account->name = 'Test';
        $account->access_token = 'shhh';
        $account->webhook_url = 'http://example.com';
        $account->webhook_config_url = 'http://example.com/config';
        $account->webhook_channel = '#test';
        $this->assertTrue($account->save());

        $notification = new Notification();
        $notification->event = 'customer.created';
        $notification->enabled = true;
        $notification->medium = Notification::EMITTER_SLACK;
        $notification->saveOrFail();

        $notification = new Notification();
        $notification->event = 'estimate.created';
        $notification->enabled = false;
        $notification->medium = Notification::EMITTER_SLACK;
        $notification->saveOrFail();

        $integration = $this->getIntegration();
        $integration->disconnect();

        $this->assertEquals(0, Notification::query()->count());
        $workflows = AutomationWorkflow::all();
        $this->assertCount(2, $workflows);
        $this->assertTrue($workflows[0]->enabled);
        $this->assertTrue(str_starts_with($workflows[0]->name, 'Slack Notification for Customer created (migrated)'));
        $this->assertFalse($workflows[1]->enabled);
        $this->assertTrue(str_starts_with($workflows[1]->name, 'Slack Notification for Estimate created (migrated)'));
        $steps = AutomationWorkflowStep::all();
        $this->assertCount(2, $steps);
        $this->assertEquals(AutomationActionType::PostToSlack, $steps[0]->action_type);
        $this->assertEquals((object) [
            'channel' => '#test',
            'message' => json_encode([
                'attachments' => [
                    [
                        'title' => '<{{event.href}}|Customer created>',
                        'text' => '{{event.text}}',
                        'color' => '{{event.color}}',
                    ],
                ],
            ]),
        ], $steps[0]->settings);
        $this->assertEquals(AutomationActionType::PostToSlack, $steps[1]->action_type);
        $this->assertEquals((object) [
            'channel' => '#test',
            'message' => json_encode([
                'attachments' => [
                    [
                        'title' => '<{{event.href}}|Estimate created>',
                        'text' => '{{event.text}}',
                        'color' => '{{event.color}}',
                    ],
                ],
            ]),
        ], $steps[1]->settings);
        $triggers = AutomationWorkflowTrigger::all();
        $this->assertCount(2, $triggers);
        $this->assertEquals(AutomationTriggerType::Event, $triggers[0]->trigger_type);
        $this->assertEquals(EventType::CustomerCreated->toInteger(), $triggers[0]->event_type);
        $this->assertEquals(AutomationTriggerType::Event, $triggers[1]->trigger_type);
        $this->assertEquals(EventType::EstimateCreated->toInteger(), $triggers[1]->event_type);
    }
}
