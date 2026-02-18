<?php

namespace App\Tests\ActivityLog\Messages;

use App\AccountsPayable\Models\Vendor;
use App\AccountsReceivable\Models\CreditNote;
use App\AccountsReceivable\Models\Customer;
use App\AccountsReceivable\Models\Estimate;
use App\AccountsReceivable\Models\Invoice;
use App\AccountsReceivable\Models\PaymentLink;
use App\CashApplication\Models\Payment;
use App\CashApplication\Models\Transaction;
use App\Companies\Models\Company;
use App\ActivityLog\Libs\Messages\BaseMessage;
use App\ActivityLog\ValueObjects\AttributedString;
use App\Network\Models\NetworkDocument;
use App\Sending\Email\Models\Email;
use App\Sending\Mail\Models\Letter;
use App\Sending\Sms\Models\TextMessage;
use App\SubscriptionBilling\Models\Plan;
use App\SubscriptionBilling\Models\Subscription;
use App\Tests\AppTestCase;

abstract class MessageTestCaseBase extends AppTestCase
{
    const MESSAGE_CLASS = '';

    protected static array $moneyFormat;
    private static Company $dummyCompany;
    protected static Email $email;
    protected static TextMessage $textMessage;
    protected static Letter $letter;
    protected static NetworkDocument $networkDocument;
    protected static PaymentLink $paymentLink;

    public static function setUpBeforeClass(): void
    {
        self::$dummyCompany = new Company(['id' => -1]);
        self::$dummyCompany->currency = 'usd';
        self::$moneyFormat = self::$dummyCompany->moneyFormat();

        self::$customer = new Customer(['id' => -2]);
        self::$customer->name = 'Sherlock';

        self::$invoice = new Invoice(['id' => -3]);
        self::$invoice->name = 'Invoice';
        self::$invoice->number = 'INV-0001';

        self::$estimate = new Estimate(['id' => -4]);
        self::$estimate->name = 'Estimate';
        self::$estimate->number = 'EST-0001';

        self::$creditNote = new CreditNote(['id' => -10]);
        self::$creditNote->name = 'Credit Note';
        self::$creditNote->number = 'CRE-0001';

        self::$payment = new Payment(['id' => -12]);

        self::$transaction = new Transaction(['id' => -5]);

        self::$plan = new Plan(['id' => -10]);
        self::$plan->tenant_id = (int) self::$dummyCompany->id();
        self::$plan->id = 'test_plan';
        self::$plan->name = 'Starter';

        self::$subscription = new Subscription(['id' => -6]);

        self::$email = new Email(['id' => -7]);
        self::$email->email = 'test@example.com';

        self::$textMessage = new TextMessage(['id' => -10]);

        self::$letter = new Letter(['id' => -11]);

        self::$vendor = new Vendor(['id' => -14]);
        self::$vendor->name = 'Test Vendor';

        self::$networkDocument = new NetworkDocument(['id' => -15]);

        self::$paymentLink = new PaymentLink(['id' => -16]);
    }

    public function testConstruct(): void
    {
        $message = $this->getMessage('does_not_matter', ['test' => 'object'], ['test' => 'associations'], ['test' => 'previous']);
        $this->assertEquals('does_not_matter', $message->getEventType());
        $this->assertEquals(['test' => 'object'], $message->getObject());
        $this->assertEquals(['test' => 'associations'], $message->getAssociations());
        $this->assertEquals(['test' => 'previous'], $message->getPrevious());
    }

    public function testNotFound(): void
    {
        $expected = [
            new AttributedString('not_found'),
        ];

        $message = $this->getMessage('not_found');
        $this->assertEquals($expected, $message->generate());
    }

    abstract public function testToString(): void;

    protected function getMessage(string $type, array $object = [], array $associations = [], array $previous = []): BaseMessage
    {
        $class = static::MESSAGE_CLASS;

        return new $class(self::$dummyCompany, $type, $object, $associations, $previous); /* @phpstan-ignore-line */
    }
}
