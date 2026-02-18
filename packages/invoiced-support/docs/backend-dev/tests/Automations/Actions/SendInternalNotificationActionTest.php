<?php

namespace App\Tests\Automations\Actions;

use App\Automations\Actions\SendInternalNotificationAction;
use App\Automations\Enums\AutomationResult;
use App\Automations\Models\AutomationWorkflow;
use App\Automations\ValueObjects\AutomationContext;
use App\Automations\ValueObjects\AutomationOutcome;
use App\Companies\Models\Member;
use App\Core\Queue\Queue;
use App\EntryPoint\QueueJob\NotificationEventJob;
use App\Tests\AppTestCase;
use Mockery;
use Symfony\Component\Translation\Translator;

class SendInternalNotificationActionTest extends AppTestCase
{
    private static Mockery\MockInterface $queue;
    private static SendInternalNotificationAction $action;
    private static AutomationContext $context;
    private static Member $member1;
    private static Member $member2;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        self::hasCompany();
        self::hasCustomer();
        self::$customer->metadata = (object) [
            'email' => 'metadata@email.com',
            '_not_email' => 'random',
        ];
        self::$customer->saveOrFail();
        self::hasInvoice();
        self::$queue = Mockery::mock(Queue::class);
        self::$action = new SendInternalNotificationAction(self::$queue, new Translator('en'), self::getService('test.twig_renderer_factory'));
        self::$context = new AutomationContext(self::$invoice, new AutomationWorkflow());

        self::$member1 = self::hasMember('1');
        self::$member2 = self::hasMember('2');
    }

    public function testFail(): void
    {
        $this->assertEquals(new AutomationOutcome(AutomationResult::Failed, 'Invalid message'), self::$action->perform((object) [
            'message' => ' {{random}} ',
            'members' => [],
        ], self::$context));
    }

    /**
     * @dataProvider performDataProvider
     */
    public function testPerform(callable $settings, callable $result): void
    {
        $context = new AutomationContext(self::$invoice, new AutomationWorkflow());
        self::$queue->shouldReceive('enqueue')->withArgs(function (string $job, array $input) use ($result) {
            $expected = $result();
            $this->assertEquals(NotificationEventJob::class, $job);
            $this->assertEquals($expected['message'], $input['message']);
            $this->assertEquals($expected['contextId'], $input['contextId']);

            return true;
        })->once();
        $this->assertEquals(new AutomationOutcome(AutomationResult::Succeeded), self::$action->perform($settings(), $context));
    }

    public function performDataProvider(): array
    {
        return [
            [
                fn () => (object) [
                    'members' => [self::$member1->id],
                    'message' => 'body {{body}}',
                ],
                fn () => [
                    'message' => 'body',
                    'contextId' => [self::$member1->id],
                ],
            ],
            [
                fn () => (object) [
                    'members' => [self::$member1->id, self::$member2->id],
                    'message' => 'body {{body}} {{customer.email}} {{invoice.currency}}',
                ],
                fn () => [
                    'message' => 'body  sherlock@example.com usd',
                    'contextId' => [self::$member1->id, self::$member2->id],
                ],
            ],
        ];
    }
}
