<?php

namespace App\Tests\Core\Billing\Webhook;

use App\Companies\Models\Company;
use App\Core\Billing\Models\BillingProfile;
use App\Core\Billing\Webhook\InvoicedBillingWebhook;
use App\Core\Mailer\Mailer;
use App\Core\Utils\RandomString;
use App\Tests\AppTestCase;
use Invoiced\Client;
use Invoiced\Collection;
use Mockery;

class InvoicedBillingWebhookTest extends AppTestCase
{
    private static array $companies = [];
    private static string $invoicedCustomerId;

    public static function tearDownAfterClass(): void
    {
        parent::tearDownAfterClass();
        foreach (self::$companies as $company) {
            $company->delete();
        }
    }

    private function getWebhook(?Client $client = null, ?Mailer $mailer = null): InvoicedBillingWebhook
    {
        $client = $client ?? Mockery::mock(Client::class);
        $mailer ??= self::getService('test.mailer');
        $webhook = new InvoicedBillingWebhook($mailer, $client, self::getService('test.tenant'));
        $webhook->setLogger(self::$logger);

        return $webhook;
    }

    public function testGetHandleMethod(): void
    {
        $webhook = $this->getWebhook();
        $this->assertEquals('handleSubscriptionUpdated', $webhook->getHandleMethod((object) ['type' => 'subscription.updated']));
    }

    public function testHandleInvalidEvent(): void
    {
        $webhook = $this->getWebhook();
        $this->assertEquals(InvoicedBillingWebhook::ERROR_INVALID_EVENT, $webhook->handle([]));
    }

    public function testHandleCustomerNotFound(): void
    {
        $webhook = $this->getWebhook();

        $event = [
            'id' => 1,
            'type' => 'subscription.deleted',
            'data' => [
                'object' => [
                    'customer' => [
                        'id' => 987564321,
                    ],
                ],
            ],
        ];

        $this->assertEquals(InvoicedBillingWebhook::ERROR_CUSTOMER_NOT_FOUND, $webhook->handle($event));
    }

    public function testHandleNotSupported(): void
    {
        $webhook = $this->getWebhook();

        $company = new Company();
        $company->name = 'testHandleNotSupported';
        $company->username = 'testHandleNotSupported';
        $company->saveOrFail();
        self::$companies[] = $company;

        $billingProfile = BillingProfile::getOrCreate($company);
        $billingProfile->billing_system = 'invoiced';
        $billingProfile->invoiced_customer = RandomString::generate();
        $billingProfile->saveOrFail();

        $event = [
            'id' => 1,
            'data' => [
                'object' => [
                    'customer' => [
                        'id' => $billingProfile->invoiced_customer,
                    ],
                ],
            ],
            'type' => 'not.found',
        ];

        $this->assertEquals(InvoicedBillingWebhook::ERROR_EVENT_NOT_SUPPORTED, $webhook->handle($event));
    }

    public function testHandle(): void
    {
        $webhook = $this->getWebhook();

        $company = new Company();
        $company->name = 'testHandle';
        $company->username = 'testHandle';
        $company->saveOrFail();
        self::$companies[] = $company;

        $billingProfile = BillingProfile::getOrCreate($company);
        $billingProfile->billing_system = 'invoiced';
        $billingProfile->invoiced_customer = RandomString::generate();
        $billingProfile->saveOrFail();
        self::$invoicedCustomerId = (string) $billingProfile->invoiced_customer;

        $event = [
            'id' => 1,
            'data' => [
                'object' => [
                    'customer' => [
                        'id' => $billingProfile->invoiced_customer,
                    ],
                ],
            ],
            'type' => 'test',
        ];

        $this->assertEquals(InvoicedBillingWebhook::SUCCESS, $webhook->handle($event));
    }

    public function testHandleException(): void
    {
        $webhook = $this->getWebhook();

        $event = [
            'id' => 'evt_test',
            'type' => 'test.error',
            'data' => [
                'object' => [
                    'customer' => [
                        'id' => self::$invoicedCustomerId,
                    ],
                ],
            ],
        ];

        $this->assertEquals(InvoicedBillingWebhook::ERROR_GENERIC, $webhook->handle($event));
    }

    public function testHandleCustomerUpdated(): void
    {
        $webhook = $this->getWebhook();

        $billingProfile = new BillingProfile();

        $event = new \stdClass();
        $event->name = 'New Name';

        $webhook->handleCustomerUpdated($event, $billingProfile);

        $this->assertEquals('New Name', $billingProfile->name);
    }

    public function testHandleSubscriptionUpdated(): void
    {
        $webhook = $this->getWebhook();

        $billingProfile = new BillingProfile();

        $event = new \stdClass();
        $event->customer = new \stdClass();
        $event->customer->id = self::$invoicedCustomerId;
        $event->status = 'past_due';
        $event->period_end = 1234567892;
        $event->cancel_at_period_end = false;

        $webhook->handleSubscriptionUpdated($event, $billingProfile);

        $this->assertTrue($billingProfile->past_due);
    }

    public function testHandleSubscriptionPendingCancellation(): void
    {
        $webhook = $this->getWebhook();

        $company = new Company();
        $company->trial_ends = 1234567890;

        $billingProfile = new BillingProfile();
        $company->billing_profile = $billingProfile;

        $event = new \stdClass();
        $event->customer = new \stdClass();
        $event->customer->id = self::$invoicedCustomerId;
        $event->status = 'past_due';
        $event->cancel_at_period_end = true;

        $webhook->handleSubscriptionUpdated($event, $billingProfile);

        $this->assertTrue($billingProfile->past_due);
        $this->assertEquals(1234567890, $company->trial_ends);
    }

    public function testHandleSubscriptionDeleted(): void
    {
        $mailer = Mockery::mock(Mailer::class);
        $mailer->shouldReceive('sendToAdministrators')->once();

        $client = Mockery::mock(Client::class);
        $client->Subscription = Mockery::mock();
        $client->Subscription->shouldReceive('all')
            ->andReturn([[], new Collection('<https://api.invoiced.com/invoices?per_page=25&page=1>; rel="self", <https://api.invoiced.com/invoices?per_page=25&page=1>; rel="first", <https://api.invoiced.com/invoices?per_page=25&page=1>; rel="last"', 0)]);
        $webhook = $this->getWebhook($client, $mailer);

        $company = Mockery::mock(Company::class.'[save]');
        $company->shouldReceive('save')->once();

        $billingProfile = new BillingProfile();
        $company->billing_profile = $billingProfile;
        $webhook->setCompanies([$company]);

        $event = new \stdClass();
        $event->canceled_reason = 'nonpayment';

        $webhook->handleSubscriptionDeleted($event, $billingProfile);

        $this->assertTrue($company->canceled);
    }

    public function testHandleTaskCreated(): void
    {
        $task = Mockery::mock();
        $task->shouldReceive('save')->once();
        $client = Mockery::mock(Client::class);
        $client->Task = Mockery::mock();
        $client->Task->shouldReceive('retrieve')
            ->withArgs([1234])
            ->andReturn($task);
        $mailer = Mockery::mock(Mailer::class);
        $mailer->shouldReceive('sendToAdministrators')->once();
        $webhook = $this->getWebhook($client, $mailer);

        $company = Mockery::mock(Company::class.'[save]');
        $company->shouldReceive('save')->once();

        $billingProfile = new BillingProfile();
        $company->billing_profile = $billingProfile;
        $webhook->setCompanies([$company]);

        $event = new \stdClass();
        $event->id = 1234;
        $event->name = 'Shut off service';

        $webhook->handleTaskCreated($event, $billingProfile);

        $this->assertTrue($company->canceled);
    }
}
