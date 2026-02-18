<?php

namespace App\Tests\Automations\Actions;

use App\Automations\Actions\PostToSlackAction;
use App\Automations\Models\AutomationWorkflow;
use App\Automations\ValueObjects\AutomationContext;
use App\CashApplication\Models\Payment;
use App\ActivityLog\Libs\EventSpool;
use App\ActivityLog\Models\Event;
use App\Integrations\Slack\SlackClient;
use App\Tests\AppTestCase;
use Mockery;
use Symfony\Component\Translation\Translator;

class PostToSlackActionTest extends AppTestCase
{
    private static Payment $payment2;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        self::hasCompany();
        self::hasCustomer();
        self::hasInvoice();
        self::hasPayment();
        self::$payment2 = self::getTestDataFactory()->createPayment(self::$customer);
    }

    /**
     * @dataProvider provideSettings
     */
    public function testPerform(callable $object, callable $expected, string $input): void
    {
        $context = new AutomationContext($object(), new AutomationWorkflow());
        $client = Mockery::mock(SlackClient::class);
        $translator = new Translator('en');
        $action = new PostToSlackAction($client, $translator, self::getService('test.event_storage'), self::getService('test.slack_emitter'), self::getService('test.twig_renderer_factory'));

        $client->shouldReceive('postMessage')->withArgs(function (array $input) use ($expected) {
            $this->assertEquals([
                'channel' => 'test',
                'text' => $expected(),
            ], $input);

            return true;
        })->once();

        $action->perform((object) [
            'channel' => 'test',
            'message' => $input,
        ], $context);
    }

    public function provideSettings(): array
    {
        return [
            'customer' => [
                fn () => self::$customer,
                fn () => 'test '.self::$customer->number.'   test',
                'test {{ customer.number }} {{ invoice.number }} {{ random }} test',
            ],
            'invoice' => [
                fn () => self::$invoice,
                fn () => 'test '.self::$customer->number.' '.self::$invoice->number.'  test',
                'test {{ customer.number }} {{ invoice.number }} {{ random }} test',
            ],
            'payment no customer' => [
                fn () => self::$payment,
                fn () => 'test  '.self::$payment->amount.'  test',
                'test {{ customer.number }} {{ payment.amount }} {{ random }} test',
            ],
            'payment' => [
                fn () => self::$payment2,
                fn () => 'test '.self::$customer->number.' '.self::$payment->amount.'  test',
                'test {{ customer.number }} {{ payment.amount }} {{ random }} test',
            ],
        ];
    }

    public function testEventPerform(): void
    {
        EventSpool::enable();
        self::hasInvoice();
        self::getService('test.event_spool')->flush();

        $context = new AutomationContext(self::$invoice, new AutomationWorkflow(), Event::query()->one());
        $client = Mockery::mock(SlackClient::class);
        $translator = new Translator('en');
        $action = new PostToSlackAction($client, $translator, self::getService('test.event_storage'), self::getService('test.slack_emitter'), self::getService('test.twig_renderer_factory'));

        $client->shouldReceive('postMessage')->withArgs(function (array $input) {
            $this->assertEquals([
                'channel' => 'test',
                'attachments' => json_encode([
                    (object) [
                        'title' => 'Invoice created',
                        'text' => 'Invoice is created',
                        'color' => '',
                    ],
                ]),
            ], $input);

            return true;
        })->once();

        $action->perform((object) [
            'channel' => 'test',
            'message' => json_encode([
                'attachments' => [
                    [
                        'title' => 'Invoice created',
                        'text' => 'Invoice is created',
                        'color' => '{{event.color}}',
                    ],
                ],
            ]),
        ], $context);
    }

    public function testRandomJson(): void
    {
        $context = new AutomationContext(self::$invoice, new AutomationWorkflow());
        $client = Mockery::mock(SlackClient::class);
        $translator = new Translator('en');
        $action = new PostToSlackAction($client, $translator, self::getService('test.event_storage'), self::getService('test.slack_emitter'), self::getService('test.twig_renderer_factory'));

        $client->shouldReceive('postMessage')->withArgs(function (array $input) {
            $this->assertEquals([
                'channel' => 'test',
                'text' => json_encode([
                    'random' => [
                        'title' => 'Invoice created',
                        'text' => 'Invoice is created',
                        'color' => null,
                    ],
                ]),
            ], $input);

            return true;
        })->once();

        $action->perform((object) [
            'channel' => 'test',
            'message' => json_encode([
                'random' => [
                    'title' => 'Invoice created',
                    'text' => 'Invoice is created',
                    'color' => null,
                ],
            ]),
        ], $context);
    }
}
