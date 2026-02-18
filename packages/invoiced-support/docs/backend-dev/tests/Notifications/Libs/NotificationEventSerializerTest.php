<?php

namespace App\Tests\Notifications\Libs;

use App\Chasing\Models\PromiseToPay;
use App\Chasing\Models\Task;
use App\Companies\Models\Company;
use App\Companies\Models\Member;
use App\Network\Enums\DocumentStatus;
use App\Notifications\Enums\NotificationEventType;
use App\Notifications\Libs\NotificationEventSerializer;
use App\Notifications\Models\NotificationEvent;
use App\PaymentPlans\Models\PaymentPlan;
use App\PaymentPlans\Models\PaymentPlanInstallment;
use App\PaymentProcessing\Gateways\TestGateway;
use App\PaymentProcessing\Models\Charge;
use App\Tests\AppTestCase;

class NotificationEventSerializerTest extends AppTestCase
{
    private static Company $company2;

    public static function setUpBeforeClass(): void
    {
        $testData = self::getTestDataFactory();
        self::$company2 = $testData->createCompany();
        parent::setUpBeforeClass();
        self::hasCompany();
        self::hasCustomer();
        self::hasInbox();
        self::hasEmailThread();
        self::hasInboxEmail();
        self::hasInvoice();
        self::hasEstimate();
        self::hasInvoice();
        self::hasPayment();
        self::hasCard();
        self::hasPlan();
        self::hasSubscription();
    }

    public static function tearDownAfterClass(): void
    {
        parent::tearDownAfterClass();
        if (isset(self::$company2)) {
            self::$company2->delete();
        }
    }

    private function getSerializer(): NotificationEventSerializer
    {
        return new NotificationEventSerializer(self::getService('test.database'));
    }

    public function testSerializeThreadAssigned(): void
    {
        /** @var Member $member */
        $member = Member::query()->oneOrNull();
        $member->notifications = true;
        $member->saveOrFail();

        $event = new NotificationEvent();
        $event->setType(NotificationEventType::ThreadAssigned);
        $event->object_id = self::$thread->id;
        $event->saveOrFail();

        $serializer = $this->getSerializer();
        $serializer->add($event);
        $this->assertEquals([
            'id' => self::$thread->id,
            'name' => 'test',
            'customer_id' => null,
            'inbox_id' => self::$inbox->id,
        ], $serializer->serialize()[0]['metadata']);
    }

    public function testSerializeEmailReceived(): void
    {
        $event = new NotificationEvent();
        $event->setType(NotificationEventType::EmailReceived);
        $event->object_id = self::$inboxEmail->id;
        $event->saveOrFail();
        $serializer = $this->getSerializer();
        $serializer->add($event);
        $this->assertEquals([
            'id' => self::$inboxEmail->id,
            'subject' => '',
            'thread_id' => self::$inboxEmail->thread_id,
            'customer_id' => null,
            'inbox_id' => self::$inbox->id,
            'related_to_type' => null,
            'related_to_id' => null,
        ], $serializer->serialize()[0]['metadata']);
    }

    public function testSerializeTaskAssigned(): void
    {
        $task = new Task();
        $task->name = 'Send shut off notice';
        $task->action = 'mail';
        $task->due_date = time();
        $task->customer_id = self::$customer->id;
        $task->saveOrFail();
        $event = new NotificationEvent();
        $event->setType(NotificationEventType::TaskAssigned);
        $event->object_id = $task->id;
        $event->saveOrFail();

        $serializer = $this->getSerializer();
        $serializer->add($event);
        $this->assertEquals([
            'id' => $task->id,
            'name' => 'Send shut off notice',
            'customer_id' => self::$customer->id,
            'bill_id' => null,
            'vendor_credit_id' => null,
            'action' => 'mail',
        ], $serializer->serialize()[0]['metadata']);
    }

    public function testSerializeInvoiceViewed(): void
    {
        $event = new NotificationEvent();
        $event->setType(NotificationEventType::InvoiceViewed);
        $event->object_id = self::$invoice->id;
        $event->saveOrFail();

        $serializer = $this->getSerializer();
        $serializer->add($event);
        $this->assertEquals([
            'id' => self::$invoice->id,
            'number' => self::$invoice->number,
        ], $serializer->serialize()[0]['metadata']);
    }

    public function testSerializeEstimateViewed(): void
    {
        $event = new NotificationEvent();
        $event->setType(NotificationEventType::EstimateViewed);
        $event->object_id = self::$estimate->id;
        $event->saveOrFail();
        $serializer = $this->getSerializer();
        $serializer->add($event);
        $this->assertEquals([
            'id' => self::$estimate->id,
            'number' => self::$estimate->number,
        ], $serializer->serialize()[0]['metadata']);

        $event = new NotificationEvent();
        $event->setType(NotificationEventType::EstimateApproved);
        $event->object_id = self::$estimate->id;
        $event->saveOrFail();
        $serializer = $this->getSerializer();
        $serializer->add($event);
        $this->assertEquals([
            'id' => self::$estimate->id,
            'number' => self::$estimate->number,
        ], $serializer->serialize()[0]['metadata']);
    }

    public function testSerializeCustomerPortalPaymentCompleted(): void
    {
        self::$payment->customer = self::$customer->id;
        self::$payment->saveOrFail();
        $event = new NotificationEvent();
        $event->setType(NotificationEventType::PaymentDone);
        $event->object_id = self::$payment->id;
        $event->saveOrFail();
        $serializer = $this->getSerializer();
        $serializer->add($event);
        $this->assertEquals([
            'id' => self::$payment->id,
            'customer_id' => self::$customer->id,
            'customer_name' => 'Sherlock',
            'amount' => 200,
            'currency' => 'usd',
        ], $serializer->serialize()[0]['metadata']);
    }

    public function testSerializePromiseToPay(): void
    {
        $promiseToPay = new PromiseToPay();
        $promiseToPay->invoice = self::$invoice;
        $promiseToPay->customer = self::$customer;
        $promiseToPay->currency = self::$invoice->currency;
        $promiseToPay->amount = self::$invoice->balance;
        $promiseToPay->saveOrFail();
        $event = new NotificationEvent();
        $event->setType(NotificationEventType::PromiseCreated);
        $event->object_id = $promiseToPay->id;
        $event->saveOrFail();
        $serializer = $this->getSerializer();
        $serializer->add($event);
        $this->assertEquals([
            'id' => $promiseToPay->id,
            'customer_id' => self::$customer->id,
            'customer_name' => 'Sherlock',
            'amount' => 100,
            'currency' => 'usd',
            'number' => self::$invoice->number,
            'invoice_id' => self::$invoice->id,
        ], $serializer->serialize()[0]['metadata']);
    }

    public function testSerializePaymentPlanApproved(): void
    {
        $installment1 = new PaymentPlanInstallment();
        $installment1->date = (int) mktime(0, 0, 0, 3, 12, 2019);
        $installment1->amount = 50;
        $installment2 = new PaymentPlanInstallment();
        $installment2->date = (int) mktime(0, 0, 0, 4, 12, 2019);
        $installment2->amount = 50;
        $paymentPlan = new PaymentPlan();
        $paymentPlan->installments = [
            $installment1,
            $installment2,
        ];
        self::$invoice->attachPaymentPlan($paymentPlan, false, true);
        $event = new NotificationEvent();
        $event->setType(NotificationEventType::PaymentPlanApproved);
        $event->object_id = $paymentPlan->id;
        $event->saveOrFail();
        $serializer = $this->getSerializer();
        $serializer->add($event);
        $this->assertEquals([
            'id' => $paymentPlan->id,
            'number' => self::$invoice->number,
            'invoice_id' => self::$invoice->id,
        ], $serializer->serialize()[0]['metadata']);
    }

    public function testSerializeAutoPayFailed(): void
    {
        $charge = new Charge();
        $charge->customer = self::$customer;
        $charge->currency = 'usd';
        $charge->amount = self::$invoice->balance;
        $charge->status = Charge::PENDING;
        $charge->gateway = TestGateway::ID;
        $charge->gateway_id = 'ch_test' . microtime(true);
        $charge->setPaymentSource(self::$card);
        $charge->saveOrFail();
        $event = new NotificationEvent();
        $event->setType(NotificationEventType::AutoPayFailed);
        $event->object_id = $charge->id;
        $event->saveOrFail();
        $serializer = $this->getSerializer();
        $serializer->add($event);
        $this->assertEquals([
            'id' => $charge->id,
            'customer_id' => self::$customer->id,
            'customer_name' => 'Sherlock',
            'amount' => 100,
            'currency' => 'usd',
            'failure_message' => null,
            'payment_id' => null,
        ], $serializer->serialize()[0]['metadata']);
    }

    public function testSerializeAutoPaySucceeded(): void
    {
        $charge = new Charge();
        $charge->customer = self::$customer;
        $charge->currency = 'usd';
        $charge->amount = self::$invoice->balance;
        $charge->status = Charge::SUCCEEDED;
        $charge->gateway = TestGateway::ID;
        $charge->gateway_id = 'ch_test' . microtime(true);
        $charge->setPaymentSource(self::$card);
        $charge->saveOrFail();
        $event = new NotificationEvent();
        $event->setType(NotificationEventType::AutoPaySucceeded);
        $event->object_id = $charge->id;
        $event->saveOrFail();
        $serializer = $this->getSerializer();
        $serializer->add($event);
        $this->assertEquals([
            'id' => $charge->id,
            'customer_id' => self::$customer->id,
            'customer_name' => 'Sherlock',
            'amount' => 100,
            'currency' => 'usd',
            'failure_message' => null,
            'payment_id' => null,
        ], $serializer->serialize()[0]['metadata']);
    }

    public function testSerializeSignUpPageCompleted(): void
    {
        $event = new NotificationEvent();
        $event->setType(NotificationEventType::SignUpPageCompleted);
        $event->object_id = self::$customer->id;
        $event->saveOrFail();
        $serializer = $this->getSerializer();
        $serializer->add($event);
        $this->assertEquals([
            'id' => self::$customer->id,
            'customer_id' => self::$customer->id,
            'customer_name' => 'Sherlock',
            'sign_up_page_name' => null,
        ], $serializer->serialize()[0]['metadata']);
    }

    public function testSerializeSubscriptionCanceled(): void
    {
        $event = new NotificationEvent();
        $event->setType(NotificationEventType::SubscriptionCanceled);
        $event->object_id = self::$subscription->id;
        $event->saveOrFail();
        $serializer = $this->getSerializer();
        $serializer->add($event);
        $this->assertEquals([
            'id' => self::$subscription->id,
            'customer_id' => self::$customer->id,
            'customer_name' => 'Sherlock',
            'plan' => 'Starter',
        ], $serializer->serialize()[0]['metadata']);
    }

    public function testSerializeSubscriptionExpired(): void
    {
        $event = new NotificationEvent();
        $event->setType(NotificationEventType::SubscriptionExpired);
        $event->object_id = self::$subscription->id;
        $event->saveOrFail();
        $serializer = $this->getSerializer();
        $serializer->add($event);
        $this->assertEquals([
            'id' => self::$subscription->id,
            'customer_id' => self::$customer->id,
            'customer_name' => 'Sherlock',
            'plan' => 'Starter',
        ], $serializer->serialize()[0]['metadata']);
    }

    public function testSerializeLockboxReceived(): void
    {
        $event = new NotificationEvent();
        $event->setType(NotificationEventType::LockboxCheckReceived);
        $event->object_id = self::$payment->id;
        $event->saveOrFail();
        $serializer = $this->getSerializer();
        $serializer->add($event);
        $this->assertEquals([
            'id' => self::$payment->id,
            'customer_id' => self::$customer->id,
            'customer_name' => 'Sherlock',
            'amount' => 200.0,
            'currency' => 'usd',
        ], $serializer->serialize()[0]['metadata']);
    }

    public function testSerializeNetworkInvitationDeclined(): void
    {
        $invitation = self::getTestDataFactory()->createNetworkInvitation(self::$company, self::$company2);
        $event = new NotificationEvent();
        $event->setType(NotificationEventType::NetworkInvitationDeclined);
        $event->object_id = $invitation->id;
        $event->saveOrFail();
        $serializer = $this->getSerializer();
        $serializer->add($event);
        $this->assertEquals([
            'id' => $invitation->id,
            'email' => null,
            'name' => 'TEST',
        ], $serializer->serialize()[0]['metadata']);
    }

    public function testSerializeNetworkInvitationAccepted(): void
    {
        $connection = self::getTestDataFactory()->connectCompanies(self::$company, self::$company2);
        $event = new NotificationEvent();
        $event->setType(NotificationEventType::NetworkInvitationAccepted);
        $event->object_id = $connection->id;
        $event->saveOrFail();
        $serializer = $this->getSerializer();
        $serializer->add($event);
        $this->assertEquals([
            'id' => $connection->id,
            'customer_id' => self::$company2->id,
            'customer_name' => 'TEST',
            'vendor_id' => self::$company->id,
            'vendor_name' => 'TEST',
        ], $serializer->serialize()[0]['metadata']);
    }

    public function testSerializeNetworkDocumentReceived(): void
    {
        $networkDocument = self::getTestDataFactory()->createNetworkDocument(self::$company, self::$company2);
        $event = new NotificationEvent();
        $event->setType(NotificationEventType::NetworkDocumentReceived);
        $event->object_id = $networkDocument->id;
        $event->saveOrFail();
        $serializer = $this->getSerializer();
        $serializer->add($event);
        $this->assertEquals([
            'id' => $networkDocument->id,
            'from_name' => 'TEST',
            'document_type' => 'Invoice',
            'reference' => $networkDocument->reference,
        ], $serializer->serialize()[0]['metadata']);
    }

    public function testSerializeNetworkDocumentStatusChanged(): void
    {
        $networkDocument = self::getTestDataFactory()->createNetworkDocument(self::$company, self::$company2);
        $transition = self::getTestDataFactory()->createNetworkDocumentStatusTransition(self::$company, $networkDocument, DocumentStatus::Approved);
        $event = new NotificationEvent();
        $event->setType(NotificationEventType::NetworkDocumentStatusChange);
        $event->object_id = $transition->id;
        $event->saveOrFail();
        $serializer = $this->getSerializer();
        $serializer->add($event);
        $this->assertEquals([
            'id' => $transition->id,
            'document_id' => $networkDocument->id,
            'from_name' => 'TEST',
            'document_type' => 'Invoice',
            'reference' => $networkDocument->reference,
            'document_status' => 'Approved',
            'description' => null,
        ], $serializer->serialize()[0]['metadata']);
    }
}
