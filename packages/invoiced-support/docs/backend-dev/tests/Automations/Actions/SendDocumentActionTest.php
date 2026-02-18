<?php

namespace App\Tests\Automations\Actions;

use App\AccountsReceivable\Models\Contact;
use App\AccountsReceivable\Models\Customer;
use App\Automations\Actions\SendDocumentAction;
use App\Automations\Enums\AutomationResult;
use App\Automations\Models\AutomationWorkflow;
use App\Automations\ValueObjects\AutomationContext;
use App\Automations\ValueObjects\AutomationOutcome;
use App\Chasing\Models\Task;
use App\Sending\Email\EmailFactory\DocumentEmailFactory;
use App\Sending\Email\Interfaces\SendableDocumentInterface;
use App\Sending\Email\Libs\EmailSender;
use App\Sending\Email\Models\EmailTemplate;
use App\Statements\Libs\AbstractStatement;
use App\Statements\Libs\BalanceForwardStatement;
use App\Statements\Libs\OpenItemStatement;
use App\Tests\AppTestCase;
use Carbon\CarbonImmutable;
use Mockery;
use Mockery\MockInterface;

class SendDocumentActionTest extends AppTestCase
{
    private static MockInterface $email;
    private static EmailSender $send;
    private static SendDocumentAction $action;
    private static EmailTemplate $template;
    private static object $settings;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        self::hasCompany();
        self::$email = Mockery::mock(DocumentEmailFactory::class)->makePartial();
        self::$send = Mockery::mock(EmailSender::class);
        self::$send->shouldReceive('send');
        self::$action = new SendDocumentAction(self::$email, self::$send, self::getService('test.statement_builder'));

        self::hasCustomer();
        self::$template = new EmailTemplate();
        self::$template->name = 'test';
        self::$template->type = 'invoice';
        self::$template->subject = 'blah subj';
        self::$template->body = 'blah';
        self::$template->saveOrFail();

        self::$settings = (object) [
            'template' => self::$template->id,
        ];
    }

    public function testFail(): void
    {
        $factory = new DocumentEmailFactory('test.com', self::getService('app.translator'));
        $action = new SendDocumentAction($factory, self::getService('test.email_sender'), self::getService('test.statement_builder'));
        $context = new AutomationContext(new Task(), new AutomationWorkflow());
        $this->assertEquals(new AutomationOutcome(AutomationResult::Failed, 'Invalid document'), $action->perform((object) [], $context));

        $customer = new Customer();
        $customer->name = 'Sherlock2';
        $customer->saveOrFail();
        $context = new AutomationContext($customer, new AutomationWorkflow());
        $this->assertEquals(new AutomationOutcome(AutomationResult::Failed, 'No email recipients given. At least one recipient must be provided.'), $action->perform(self::$settings, $context));
    }

    public function testPerformInvoice(): void
    {
        self::hasInvoice();
        self::$email->shouldReceive('make')->withArgs(function (SendableDocumentInterface $document, EmailTemplate $emailTemplate, array $to) {
            $this->assertEquals(self::$invoice, $document);
            $this->assertEquals([
                [
                    'name' => 'Sherlock',
                    'email' => 'sherlock@example.com',
                ],
            ], $to);
            $this->assertEquals(self::$template->id, $emailTemplate->id);

            return true;
        })->once();

        $context = new AutomationContext(self::$invoice, new AutomationWorkflow());
        $this->assertEquals(new AutomationOutcome(AutomationResult::Succeeded), self::$action->perform(self::$settings, $context));
    }

    public function testPerformEstimate(): void
    {
        self::hasEstimate();
        self::$email->shouldReceive('make')->withArgs(function (SendableDocumentInterface $document, EmailTemplate $emailTemplate, array $to) {
            $this->assertEquals(self::$estimate, $document);
            $this->assertEquals([
                [
                    'name' => 'Sherlock',
                    'email' => 'sherlock@example.com',
                ],
            ], $to);
            $this->assertEquals(self::$template->id, $emailTemplate->id);

            return true;
        })->once();
        $context = new AutomationContext(self::$estimate, new AutomationWorkflow());
        $this->assertEquals(new AutomationOutcome(AutomationResult::Succeeded), self::$action->perform(self::$settings, $context));
    }

    public function testPerformCustomer(): void
    {
        $contact = new Contact();
        $contact->customer = self::$customer;
        $contact->email = 'test@test3.com';
        $contact->name = 'Another Role';
        $contact->saveOrFail();
        self::$email->shouldReceive('make')->withArgs(function (OpenItemStatement $document, EmailTemplate $emailTemplate, array $to) {
            $this->assertEquals(self::getService('test.statement_builder')->openItem(self::$customer), $document);

            $this->assertEquals([
                [
                    'name' => 'Another Role',
                    'email' => 'test@test3.com',
                ],
                [
                    'name' => 'Sherlock',
                    'email' => 'sherlock@example.com',
                ],
            ], $to);
            $this->assertEquals(self::$template->id, $emailTemplate->id);
            $this->assertEquals(null, $document->getSendObjectType());
            $this->assertBetween((int) $document->getEndDate(), time() - 3, time() + 3);
            $this->assertEquals(null, $document->getStartDate());

            return true;
        })->once();
        $context = new AutomationContext(self::$customer, new AutomationWorkflow());

        $this->assertEquals(new AutomationOutcome(AutomationResult::Succeeded), self::$action->perform(self::$settings, $context));
    }

    /**
     * @dataProvider provideCustomerSettings
     */
    public function testCustomerSettings(object $settings, array $expected): void
    {
        $settings->template = self::$template->id;
        self::$email->shouldReceive('make')->withArgs(function (AbstractStatement $document, EmailTemplate $emailTemplate, array $to) use ($expected) {
            $this->assertInstanceOf($expected['class'], $document);
            $this->assertEquals(self::$template->id, $emailTemplate->id);
            $this->assertEquals(null, $document->getSendObjectType());
            $this->assertBetween((int) $document->getEndDate(), $expected['end_date'] - 100, $expected['end_date'] + 100);
            $this->assertBetween((int) $document->getStartDate(), $expected['start_date'] - 100, $expected['start_date'] + 100);
            $this->assertStringContainsString($expected['past_due'], $document->getSendClientUrl());

            return true;
        })->once();
        $context = new AutomationContext(self::$customer, new AutomationWorkflow());

        $this->assertEquals(new AutomationOutcome(AutomationResult::Succeeded), self::$action->perform($settings, $context));
    }

    public function provideCustomerSettings(): array
    {
        return [
            [
                (object) [
                ],
                [
                    'class' => OpenItemStatement::class,
                    'type' => null,
                    'end_date' => time(),
                    'start_date' => 0,
                    'past_due' => '',
                ],
            ],
            [
                (object) [
                    'openItemMode' => 'past_due',
                ],
                [
                    'class' => OpenItemStatement::class,
                    'type' => null,
                    'end_date' => time(),
                    'start_date' => 0,
                    'past_due' => 'past_due',
                ],
            ],
            [
                (object) [
                    'openItemMode' => 'open',
                    'type' => 'open_item',
                ],
                [
                    'class' => OpenItemStatement::class,
                    'type' => null,
                    'end_date' => time(),
                    'start_date' => 0,
                    'past_due' => '',
                ],
            ],
            [
                (object) [
                    'type' => 'balance_forward',
                ],
                [
                    'class' => BalanceForwardStatement::class,
                    'type' => null,
                    'end_date' => time(),
                    'start_date' => CarbonImmutable::now()->startOfMonth()->unix(),
                    'past_due' => '',
                ],
            ],
            [
                (object) [
                    'type' => 'balance_forward',
                    'period' => 'this_month',
                ],
                [
                    'class' => BalanceForwardStatement::class,
                    'type' => null,
                    'end_date' => CarbonImmutable::now()->endOfDay()->unix(),
                    'start_date' => CarbonImmutable::now()->startOfMonth()->unix(),
                    'past_due' => '',
                ],
            ],
            [
                (object) [
                    'type' => 'balance_forward',
                    'period' => 'last_quarter',
                ],
                [
                    'class' => BalanceForwardStatement::class,
                    'type' => null,
                    'end_date' => CarbonImmutable::now()->subQuarter()->endOfQuarter()->unix(),
                    'start_date' => CarbonImmutable::now()->subQuarter()->startOfQuarter()->unix(),
                    'past_due' => '',
                ],
            ],
        ];
    }
}
