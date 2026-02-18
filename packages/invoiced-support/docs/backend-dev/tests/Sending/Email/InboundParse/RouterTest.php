<?php

namespace App\Tests\Sending\Email\InboundParse;

use App\Core\Mailer\Mailer;
use App\Sending\Email\Exceptions\InboundParseException;
use App\Sending\Email\InboundParse\Handlers\ImportInvoicePdfHandler;
use App\Sending\Email\InboundParse\Handlers\InboxEmailHandler;
use App\Sending\Email\InboundParse\Router;
use App\Sending\Email\Models\Inbox;
use App\Tests\AppTestCase;

class RouterTest extends AppTestCase
{
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        self::hasCompany();
        self::hasInbox();
    }

    private function getRouter(): Router
    {
        return self::getService('test.router');
    }

    public function testRouteInvalidAddress(): void
    {
        $this->expectException(InboundParseException::class);

        $this->getRouter()->route('blah@example.com');
    }

    public function testRouteInboxEmail(): void
    {
        /** @var InboxEmailHandler $handler */
        $handler = $this->getRouter()->route(self::$inbox->external_id.'@test.invoicedmail.com');

        $this->assertInstanceOf(InboxEmailHandler::class, $handler);
        $this->assertInstanceOf(Inbox::class, $handler->getInbox());
        $this->assertEquals(self::$inbox->id(), $handler->getInbox()->id());
    }

    public function testRouteCommentReplyNoSuchCompany(): void
    {
        $this->expectException(InboundParseException::class);

        $this->getRouter()->route('reply+acmecorp.invoice.1938699@test.invoicedmail.com');
    }

    public function testRouteInvoiceImport(): void
    {
        $handler = $this->getRouter()->route('invimport+'.self::$company->username.'@test.invoicedmail.com');

        $this->assertInstanceOf(ImportInvoicePdfHandler::class, $handler);
    }

    public function testRouteInvoiceImportRfc822(): void
    {
        $handler = $this->getRouter()->route('My Customer <invimport+'.self::$company->username.'@test.invoicedmail.com>');

        $this->assertInstanceOf(ImportInvoicePdfHandler::class, $handler);
    }

    public function testRouteInvoiceImportNoSuchCompany(): void
    {
        $this->expectException(InboundParseException::class);

        $this->getRouter()->route('reply+acmecorp@test.invoicedmail.com');
    }

    public function testNotifyAboutException(): void
    {
        $mailer = \Mockery::mock(Mailer::class);
        $mailer->shouldReceive('send')->once();
        $e = new InboundParseException('Unrecognized address');
        $this->getRouter()->notifyAboutException($mailer, 'reply+acmecorp.invoice.1938699@test.invoicedmail.com', 'jared@invoiced.com', 'INV-0001', $e);
    }

    public function testGetAddressRfc822(): void
    {
        $this->assertEquals('jared@invoiced.com', Router::getAddressRfc822('jared@invoiced.com'));
        $this->assertEquals('jared@invoiced.com', Router::getAddressRfc822('Jared King <jared@invoiced.com>'));
    }
}
