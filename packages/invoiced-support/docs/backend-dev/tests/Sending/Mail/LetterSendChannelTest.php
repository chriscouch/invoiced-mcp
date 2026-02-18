<?php

namespace App\Tests\Sending\Mail;

use App\Core\I18n\AddressFormatter;
use App\AccountsReceivable\Models\ReceivableDocument;
use App\Sending\Mail\Libs\LetterSendChannel;
use App\Sending\Mail\Libs\LetterSender;
use App\Sending\Models\ScheduledSend;
use App\Tests\AppTestCase;

class LetterSendChannelTest extends AppTestCase
{
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        self::hasCompany();
        self::hasCustomer();
        self::hasInvoice();
    }

    private function getChannel(): LetterSendChannel
    {
        return new LetterSendChannel(\Mockery::mock(LetterSender::class), self::getService('test.transaction_manager'));
    }

    public function testMissingContactInfo(): void
    {
        $channel = $this->getChannel();
        $send = new ScheduledSend();
        $send->invoice = self::$invoice;

        // unset customer address
        self::$customer->address1 = null;
        self::$customer->address2 = null;

        // should mark send as failed for missing contact info
        $channel->send($send);
        $this->assertTrue($send->failed);
        $this->assertEquals('Address information is missing.', $send->failure_detail);
    }

    public function testBuildToCustomerAddress(): void
    {
        $channel = $this->getChannel();
        $send = new ScheduledSend();
        $send->invoice = self::$invoice;

        // unset customer address
        self::$customer->address1 = '201 1st Street';
        self::$customer->city = 'Austin';
        self::$customer->state = 'TX';
        self::$customer->postal_code = '78652';
        self::$customer->country = 'US';
        self::$customer->save();

        // should build address from customer address
        /** @var ReceivableDocument $document */
        $document = $send->getDocument();
        $address = $channel->buildTo($send->getParameters(), $document, new AddressFormatter());
        $this->assertEquals('201 1st Street', $address->getAddressLine1());
        $this->assertEquals('Austin', $address->getLocality());
        $this->assertEquals('TX', $address->getAdministrativeArea());
        $this->assertEquals('78652', $address->getPostalCode());
        $this->assertEquals('US', $address->getCountryCode());
    }

    public function testBuildToScheduledSendAddress(): void
    {
        $channel = $this->getChannel();
        $send = new ScheduledSend();
        $send->invoice = self::$invoice;
        $send->parameters = [
            'to' => [
                'address1' => '202 2nd Street',
                'city' => 'Dallas',
                'state' => 'TX',
                'postal_code' => '78653',
                'country' => 'US',
            ],
        ];

        self::$customer->address1 = null;
        self::$customer->city = null;
        self::$customer->state = null;
        self::$customer->postal_code = null;

        // should build address from ScheduledSend 'to' property
        /** @var ReceivableDocument $document */
        $document = $send->getDocument();
        $address = $channel->buildTo($send->getParameters(), $document, new AddressFormatter());
        $this->assertEquals('202 2nd Street', $address->getAddressLine1());
        $this->assertEquals('Dallas', $address->getLocality());
        $this->assertEquals('TX', $address->getAdministrativeArea());
        $this->assertEquals('78653', $address->getPostalCode());
        $this->assertEquals('US', $address->getCountryCode());
    }
}
