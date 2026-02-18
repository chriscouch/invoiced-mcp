<?php

namespace App\Tests\Chasing\CustomerChasing\Actions;

use App\AccountsReceivable\Libs\CustomerHierarchy;
use App\Chasing\CustomerChasing\Actions\Email;
use App\Chasing\CustomerChasing\ChasingStatement;
use App\Chasing\CustomerChasing\ChasingStatementStrategy;
use App\Chasing\Models\ChasingCadence;
use App\Chasing\Models\ChasingCadenceStep;
use App\Chasing\ValueObjects\ChasingEvent;
use App\Core\I18n\ValueObjects\Money;
use App\Sending\Email\EmailFactory\DocumentEmailFactory;
use App\Sending\Email\Libs\EmailSender;
use App\Sending\Email\Models\EmailTemplate;
use App\Statements\Libs\OpenItemStatement;
use App\Statements\Libs\OpenItemStatementData;
use App\Statements\Libs\StatementBuilder;
use App\Statements\StatementLines\OpenItemStatementLineFactory;
use App\Tests\AppTestCase;
use Mockery;

class EmailTest extends AppTestCase
{
    private static ChasingCadence $cadence;
    private static ChasingCadenceStep $step;
    private static EmailTemplate $emailTemplate;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        self::hasCompany();
        self::hasCustomer();
        self::hasInvoice();

        self::$emailTemplate = new EmailTemplate();
        self::$emailTemplate->type = EmailTemplate::TYPE_CHASING;
        self::$emailTemplate->name = 'Test';
        self::$emailTemplate->subject = 'Test';
        self::$emailTemplate->body = 'Your account is past due';
        self::$emailTemplate->saveOrFail();

        self::$cadence = new ChasingCadence();
        self::$cadence->name = 'Test';
        self::$cadence->time_of_day = 7;
        self::$cadence->steps = [
            [
                'name' => 'First Step',
                'schedule' => 'age:0',
                'action' => ChasingCadenceStep::ACTION_EMAIL,
                'email_template_id' => self::$emailTemplate->id,
            ],
        ];
        self::$cadence->saveOrFail();

        self::$step = self::$cadence->getSteps()[0];
    }

    public function getFactory(): DocumentEmailFactory
    {
        return new DocumentEmailFactory('test.invoicedmail.com', self::getService('translator'));
    }

    public function testExecute(): void
    {
        $sender = Mockery::mock(EmailSender::class);
        $sender->shouldReceive('send')
            ->andReturn(true);
        $action = new Email($this->getFactory(), $sender, self::getService('test.statement_builder'));

        $balance = new Money('usd', 100);
        $pastDueBalance = new Money('usd', 0);
        $invoices = [self::$invoice];
        $event = new ChasingEvent(self::$customer, $balance, $pastDueBalance, $invoices, self::$step);

        $actionResult = $action->execute($event);
        $this->assertTrue($actionResult->isSuccessful());
        $this->assertNull($actionResult->getMessage());
    }

    public function testThreshold(): void
    {
        $sender = Mockery::mock(EmailSender::class);
        $sender->shouldReceive('send')
            ->andReturn(true);
        $factory = Mockery::mock(DocumentEmailFactory::class);
        $builder = Mockery::mock(StatementBuilder::class);

        $action = new Email($factory, $sender, $builder);

        $balance = new Money('usd', 100);
        $pastDueBalance = new Money('usd', 0);
        $invoices = [self::$invoice];
        $event = new ChasingEvent(self::$customer, $balance, $pastDueBalance, $invoices, self::$step);

        // list of variables available for chasing strategy only
        $expectedVariables = [
            'customer',
            'company_name',
            'company_username',
            'company_address',
            'company_email',
            'customer_name',
            'customer_contact_name',
            'customer_number',
            'customer_address',
            'account_balance',
            'past_due_account_balance',
            'invoice_numbers',
            'invoice_dates',
            'invoice_due_dates',
            'customer_portal_button',
        ];
        sort($expectedVariables);

        $factory->shouldReceive('make')
            ->withArgs(function (ChasingStatementStrategy $arg) use ($expectedVariables) {
                $this->assertInstanceOf(ChasingStatement::class, $arg->getStrategy());

                $variables = $arg->getEmailVariables()->generate(self::$emailTemplate);
                $keys = array_keys($variables);
                sort($keys);
                $this->assertEquals($expectedVariables, $keys);

                return true;
            })->once();
        $actionResult = $action->execute($event);
        $this->assertTrue($actionResult->isSuccessful());
        $this->assertNull($actionResult->getMessage());
        $builder->shouldNotHaveBeenCalled();

        $invoices = array_fill(0, Email::MAX_INVOICES_THRESHOLD + 1, self::$invoice);
        $event = new ChasingEvent(self::$customer, $balance, $pastDueBalance, $invoices, self::$step);

        $hierarchy = Mockery::mock(CustomerHierarchy::class);
        $hierarchy->shouldReceive('getSubCustomerIds')->andReturn([]);
        $lineFactory = Mockery::mock(OpenItemStatementLineFactory::class);
        $lineFactory->shouldReceive('makeFromList')->andReturn([]);
        $openItemStatement = new OpenItemStatement(
            new OpenItemStatementData(
                $lineFactory,
                $hierarchy
            ), self::$customer);
        $builder->shouldReceive('openItem')->andReturn($openItemStatement)->once();
        $factory->shouldReceive('make')
            ->withArgs(function (ChasingStatementStrategy $arg) use ($expectedVariables) {
                $this->assertInstanceOf(OpenItemStatement::class, $arg->getStrategy());

                $variables = $arg->getEmailVariables()->generate(self::$emailTemplate);
                $keys = array_keys($variables);
                sort($keys);
                $this->assertEquals($expectedVariables, $keys);

                return true;
            })->once();
        $actionResult = $action->execute($event);
        $this->assertTrue($actionResult->isSuccessful());
        $this->assertNull($actionResult->getMessage());
        $builder->shouldNotHaveBeenCalled();
    }
}
