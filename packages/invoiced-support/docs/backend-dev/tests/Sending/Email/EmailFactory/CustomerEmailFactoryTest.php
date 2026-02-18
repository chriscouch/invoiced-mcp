<?php

namespace App\Tests\Sending\Email\EmailFactory;

use App\Companies\Models\Company;
use App\Sending\Email\EmailFactory\CustomerEmailFactory;
use App\Sending\Email\ValueObjects\Email;
use App\Sending\Email\ValueObjects\NamedAddress;

class CustomerEmailFactoryTest extends AbstractEmailFactoryTestBase
{
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        self::hasCompany();
        self::$company->name = 'Test Company';
        self::$company->email = 'test@example.com';
        self::$company->logo = 'LOGO';
        self::$company->save();

        self::hasCustomer();
        self::hasInvoice();
    }

    public function testGetCompany(): void
    {
        $message = $this->getMessage();
        $this->assertInstanceOf(Company::class, $message->getCompany());
    }

    public function testFrom(): void
    {
        $message = $this->getMessage();
        $this->assertEquals(new NamedAddress('test@example.com', 'Test Company'), $message->getFrom());
    }

    public function testTemplateVars(): void
    {
        $factory = $this->getFactory();

        $expected = [
            'highlightColor' => '#303030',
            'logo' => 'https://logos.invoiced.com/LOGO',
            'companyName' => 'Test Company',
            'companyNameWithAddress' => 'Test Company, Company, Address, Austin, TX 78701, United States',
            'showPoweredBy' => false,
            'testMode' => false,
            'test' => true,
        ];

        $this->assertEquals($expected, $factory->getTemplateVars(self::$company, ['test' => true]));
    }

    public function getFactory(): CustomerEmailFactory
    {
        return new CustomerEmailFactory('test.invoicedmail.com');
    }

    public function testPlainText(): void
    {
        $message = $this->getMessage(['text' => 'test']);
        $this->assertEquals('test', $message->getPlainText());

        $message = $this->getMessage(['text' => null]);
        $this->assertEquals('Please open this email in a client that supports HTML messages.', $message->getPlainText());
    }

    public function testHtml(): void
    {
        $message = $this->getMessage(['body' => 'test']);
        $html = $message->getHtml();
        $this->assertStringContainsString('test', (string) $html);
    }

    public function testAttachments(): void
    {
        $message = $this->getMessage();
        $this->assertEquals([], $message->getAttachments());
    }

    public function testHeaders(): void
    {
        $message = $this->getMessage();

        $expected = [
            'Reply-To' => 'Test Company <'.self::$company->accounts_receivable_settings->reply_to_inbox?->external_id.'@test.invoicedmail.com>',
            'X-Invoiced' => 'true',
            'X-Invoiced-Account' => self::$company->identifier,
            'X-Auto-Response-Suppress' => 'All',
            'Message-ID' => '<'.self::$company->username.'/'.$message->getId().'@invoiced.com>',
        ];

        $this->assertEquals($expected, $message->getHeaders());
    }

    public function testHeadersCompanyReply(): void
    {
        self::$company->accounts_receivable_settings->reply_to_inbox = null;
        self::$company->accounts_receivable_settings->saveOrFail();
        $message = $this->getMessage();

        $expected = [
            'Reply-To' => 'Test Company <test@example.com>',
            'X-Invoiced' => 'true',
            'X-Invoiced-Account' => self::$company->identifier,
            'X-Auto-Response-Suppress' => 'All',
            'Message-ID' => '<'.self::$company->username.'/'.$message->getId().'@invoiced.com>',
        ];

        $this->assertEquals($expected, $message->getHeaders());
    }

    private function getMessage(array $vars = []): Email
    {
        $vars = array_replace(['body' => 'test', 'text' => 'test'], $vars);
        $factory = $this->getFactory();
        $to = [['email' => 'test@example.com', 'name' => 'Test']];

        return $factory->make(self::$customer, 'custom-message', $vars, $to);
    }
}
