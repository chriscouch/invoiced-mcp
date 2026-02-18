<?php

namespace App\Tests\Automations\Actions;

use App\AccountsReceivable\Models\CreditNote;
use App\Automations\Actions\SendEmailAction;
use App\Automations\Enums\AutomationResult;
use App\Automations\Models\AutomationWorkflow;
use App\Automations\ValueObjects\AutomationContext;
use App\Automations\ValueObjects\AutomationOutcome;
use App\Sending\Email\EmailFactory\GenericEmailFactory;
use App\Sending\Email\Libs\EmailSender;
use App\Sending\Email\ValueObjects\Email;
use App\Tests\AppTestCase;
use Carbon\CarbonImmutable;
use DateTimeInterface;
use Mockery;
use Symfony\Component\Translation\Translator;

class SendEmailActionTest extends AppTestCase
{
    private static Mockery\MockInterface $send;
    private static SendEmailAction $action;
    private static AutomationContext $context;

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
        $creditNote = new CreditNote();
        $creditNote->setCustomer(self::$customer);
        $creditNote->items = [
            [
                'name' => 'Test Item',
                'description' => 'test',
                'quantity' => 1,
                'unit_cost' => 20,
            ],
        ];
        $creditNote->saveOrFail();
        self::hasCredit();
        self::$send = Mockery::mock(EmailSender::class);
        $factory = new GenericEmailFactory('test.com', self::getService('test.twig_renderer_factory'));
        self::$action = new SendEmailAction(self::getService('test.tenant'), new Translator('en'), $factory, self::$send);
        self::$context = new AutomationContext(self::$invoice, new AutomationWorkflow());
    }

    public function testFail(): void
    {
        $this->assertEquals(new AutomationOutcome(AutomationResult::Failed, 'Missing required parameters'), self::$action->perform((object) [
            'to' => [],
        ], self::$context));
        $this->assertEquals(new AutomationOutcome(AutomationResult::Failed, 'Invalid email address'), self::$action->perform((object) [
            'to' => [],
            'subject' => '',
            'body' => '',
        ], self::$context));
    }

    /**
     * @dataProvider performDataProvider
     */
    public function testPerform(object $settings, array $expected): void
    {
        $context = new AutomationContext(self::$invoice, new AutomationWorkflow());
        self::$send->shouldReceive('send')->withArgs(function (Email $email) use ($expected) {
            $this->assertEquals($expected['to'], array_map(fn ($item) => $item->getAddress(), $email->getTo()));
            $this->assertEquals($expected['cc'], array_map(fn ($item) => $item->getAddress(), $email->getCc()));
            $this->assertEquals($expected['bcc'], array_map(fn ($item) => $item->getAddress(), $email->getBcc()));
            $this->assertEquals($expected['body'], $email->getPlainText());
            $this->assertEquals($expected['subject'], $email->getSubject());

            return true;
        })->once();
        $this->assertEquals(new AutomationOutcome(AutomationResult::Succeeded), self::$action->perform($settings, $context));
    }

    /**
     * @depends testPerform
     */
    public function testDateVoided(): void
    {
        self::$invoice->void();

        $context = new AutomationContext(self::$invoice, new AutomationWorkflow());
        self::$send->shouldReceive('send')->withArgs(function (Email $email) {
            $this->assertEquals('body  '.CarbonImmutable::createFromTimestamp(self::$invoice->date_voided ?? 0)->format(DateTimeInterface::ATOM), $email->getPlainText());

            return true;
        })->once();
        $this->assertEquals(new AutomationOutcome(AutomationResult::Succeeded), self::$action->perform((object) [
            'to' => ['test1@test.com'],
            'subject' => 'subject {{subject}}',
            'body' => 'body {{body}} {{invoice.date_voided}}',
        ], $context));
    }

    public function performDataProvider(): array
    {
        return [
            [
                (object) [
                    'to' => ['test1@test.com'],
                    'subject' => 'subject {{subject}}',
                    'body' => 'body {{body}}',
                ],
                [
                    'to' => ['test1@test.com'],
                    'cc' => [],
                    'bcc' => [],
                    'body' => 'body',
                    'subject' => 'subject',
                ],
            ],
            [
                (object) [
                    'to' => ['test1@test.com', 'random', '{{customer.email}}', '{{customer.nothing}}'],
                    'cc' => ['test2@test.com', '{{customer.metadata.email}}'],
                    'bcc' => ['tes41@test.com', '{{customer.metadata._not_email}}'],
                    'subject' => 'subject {{subject}} {{customer.name}} {{customer|balance|money}} {{customer|credit_balance|money}} {{customer|credit_balance("eur")|money}} {{customer|credit_balance("usd")|money}}',
                    'body' => 'body {{body}} {{customer.email}} {{invoice.currency}} {{invoice.amount_paid}}',
                ],
                [
                    'to' => ['test1@test.com', 'sherlock@example.com'],
                    'cc' => ['test2@test.com', 'metadata@email.com'],
                    'bcc' => ['tes41@test.com'],
                    'body' => 'body  sherlock@example.com usd 0',
                    'subject' => 'subject  Sherlock $100.00 $80.00 $0.00 $80.00',
                ],
            ],
        ];
    }
}
