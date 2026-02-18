<?php

namespace App\Tests\Automations\Actions;

use App\Automations\Actions\WebhookAction;
use App\Automations\Enums\AutomationResult;
use App\Automations\Models\AutomationWorkflow;
use App\Automations\ValueObjects\AutomationContext;
use App\Core\Utils\Enums\ObjectType;
use App\ActivityLog\Models\Event;
use App\Tests\AppTestCase;
use Mockery;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class WebhookActionTest extends AppTestCase
{
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        self::hasCompany();
        self::hasCustomer();
    }

    public function testPerform(): void
    {
        $client = Mockery::mock(HttpClientInterface::class);
        $action = new WebhookAction($client);
        $workflow = new AutomationWorkflow();
        $workflow->id = 123456;
        $event = new Event();
        $event->id = 54321;
        $settings = (object) [
            'url' => 'test.com',
        ];
        $context = new AutomationContext(self::$customer, $workflow);
        $client->shouldReceive('request')->withArgs(function (string $method, string $url, array $options) {
            $this->assertEquals('POST', $method);
            $this->assertEquals('test.com', $url);
            $this->assertEquals([
                'object' => self::$customer->getEventObject(),
                'context' => [
                    'object_type' => ObjectType::Customer->typeName(),
                    'object_id' => self::$customer->id,
                    'workflow' => 123456,
                    'event' => null,
                ],
            ], $options['json']);

            return true;
        })->once();
        $response = $action->perform($settings, $context);
        $this->assertEquals(AutomationResult::Succeeded, $response->result);

        $context = new AutomationContext(self::$customer, $workflow, $event);
        $client->shouldReceive('request')->withArgs(function (string $method, string $url, array $options) {
            $this->assertEquals('POST', $method);
            $this->assertEquals('test.com', $url);
            $this->assertEquals([
                'object' => self::$customer->getEventObject(),
                'context' => [
                    'object_type' => ObjectType::Customer->typeName(),
                    'object_id' => self::$customer->id,
                    'workflow' => 123456,
                    'event' => 54321,
                ],
            ], $options['json']);

            return true;
        })->once();
        $response = $action->perform($settings, $context);
        $this->assertEquals(AutomationResult::Succeeded, $response->result);
    }
}
