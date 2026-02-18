<?php

namespace App\Tests\Sending\Email\EmailFactory;

use App\Companies\Models\Company;
use App\Core\Files\Models\Attachment;
use App\Core\Files\Models\File;
use App\Core\Pdf\Exception\PdfException;
use App\Sending\Email\EmailFactory\DocumentEmailFactory;
use App\Sending\Email\Exceptions\SendEmailException;
use App\Sending\Email\Interfaces\EmailVariablesInterface;
use App\Sending\Email\Interfaces\SendableDocumentInterface;
use App\Sending\Email\Models\EmailTemplate;
use App\Sending\Email\Models\EmailTemplateOption;
use App\Sending\Email\Models\EmailThread;
use App\Sending\Email\ValueObjects\DocumentEmail;
use App\Sending\Email\ValueObjects\NamedAddress;
use App\Themes\Interfaces\PdfBuilderInterface;
use Mockery;
use Psr\Log\NullLogger;

class DocumentEmailFactoryTest extends AbstractEmailFactoryTestBase
{
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        self::hasCompany();
        self::$company->name = 'Test Company';
        self::$company->email = 'test@example.com';
        self::$company->saveOrFail();
        self::$company->accounts_receivable_settings->bcc = 'bcc@example.com';
        self::$company->accounts_receivable_settings->saveOrFail();

        self::hasInbox();
        self::hasCustomer();
        self::hasInvoice();
    }

    public function getFactory(): DocumentEmailFactory
    {
        $factory = new DocumentEmailFactory('test.invoicedmail.com', self::getService('translator'));
        $factory->setLogger(new NullLogger());

        return $factory;
    }

    public function testMakeNoRecipients(): void
    {
        $this->expectException(SendEmailException::class);
        $this->expectExceptionMessage('No email recipients given. At least one recipient must be provided.');

        $emailTemplate = EmailTemplate::make(self::$company->id, EmailTemplate::NEW_INVOICE);

        $this->getFactory()->make(self::$invoice, $emailTemplate, []);
    }

    public function testFrom(): void
    {
        $message = $this->getMessage();
        $this->assertEquals(new NamedAddress('test@example.com', 'Test Company'), $message->getFrom());
    }

    public function testBCCs(): void
    {
        $factory = $this->getFactory();

        $bccs = $factory->generateBccs(self::$company, [], null);
        $expected = [
            new NamedAddress('bcc@example.com', 'Test Company'),
        ];
        $this->assertEquals($expected, $bccs);

        $to = [new NamedAddress('test2@example.com', 'Test')];

        $bccs = $factory->generateBccs(self::$company, $to, 'test@example.com, TEST@EXAMPLE.COM,test@example.com,test2@example.com,test3@example.com,test4@example.com,test5@example.com,test6@example.com,test7@example.com');

        $expected = [
            new NamedAddress('test@example.com', 'Test Company'),
            new NamedAddress('test3@example.com', 'Test Company'),
            new NamedAddress('test4@example.com', 'Test Company'),
            new NamedAddress('test5@example.com', 'Test Company'),
            new NamedAddress('test6@example.com', 'Test Company'),
        ];

        $this->assertEquals($expected, $bccs);

        $bccs = $factory->generateBccs(self::$company, [], 'test@example.com, test2@example.com, emailtosalesforce@3396xvce1xrj5tjrf19s9lacjcgw45of3k3uz0ah6f4wdcbtd9.41-gqnqeak.na35.le.salesforce.com');
        $expected = [
            new NamedAddress('test@example.com', 'Test Company'),
            new NamedAddress('test2@example.com', 'Test Company'),
            new NamedAddress('emailtosalesforce@3396xvce1xrj5tjrf19s9lacjcgw45of3k3uz0ah6f4wdcbtd9.41-gqnqeak.na35.le.salesforce.com', 'Test Company'),
        ];
        $this->assertEquals($expected, $bccs);
    }

    public function testSubject(): void
    {
        $message = $this->getMessage();
        $this->assertEquals('Invoice from Test Company: INV-00001', $message->getSubject());
    }

    public function testSubjectCustom(): void
    {
        $message = $this->getMessage(null, [], 'Custom subject from {{company_name}}');
        $this->assertEquals('Custom subject from Test Company', $message->getSubject());
    }

    public function testEmailThreadName(): void
    {
        $message = $this->getMessage();
        $this->assertEquals('INV-00001', $message->getEmailThread()->name); /* @phpstan-ignore-line */
    }

    public function testMustacheSubject(): void
    {
        DocumentEmailFactory::$emailVariables = ['test' => 'Hello, world!'];
        $message = $this->getMessage(null, [], '{{test}}{{{test}}}{TEST}');
        $this->assertEquals('Hello, world!Hello, world!{TEST}', $message->getSubject());

        // Currently we allow HTML in message bodies since
        // email clients should provide XSS protection. However,
        // we do not allow HTML to be injected from email variables.
        DocumentEmailFactory::$emailVariables = ['test' => 'Hello, world!<script>Should not be escaped</script>'];
        $message = $this->getMessage(null, [], '{{test}}{{{test}}}{TEST}<script>Should also not be escaped</script>');
        $this->assertEquals('Hello, world!<script>Should not be escaped</script>Hello, world!Should not be escaped{TEST}Should also not be escaped', $message->getSubject());
    }

    public function testMustacheSubjectInvalidSyntax(): void
    {
        $this->expectException(SendEmailException::class);

        // NOTE: inevitably users will assume they need to fill
        // in the variables and do something like below
        $subject = '{{#Attendance}}{{ what is this }}';
        $this->getMessage(null, [], $subject);
    }

    public function testTwigSubject(): void
    {
        DocumentEmailFactory::$emailVariables = ['test' => 'Hello, world!'];
        $message = $this->getMessage(null, [], '{{test}}{TEST}{% if test %}pass{% else %}fail{% endif %}', 'twig');
        $this->assertEquals('Hello, world!{TEST}pass', $message->getSubject());

        // Currently we allow HTML in message bodies since
        // email clients should provide XSS protection. However,
        // we do not allow HTML to be injected from email variables.
        DocumentEmailFactory::$emailVariables = ['test' => 'Hello, world!<script>Should not be escaped</script>'];
        $message = $this->getMessage(null, [], '{{test}}{{test|raw}}{TEST}<script>Should also not be escaped</script>', 'twig');
        $this->assertEquals('Hello, world!<script>Should not be escaped</script>Hello, world!Should not be escaped{TEST}Should also not be escaped', $message->getSubject());
    }

    public function testTwigSubjectInvalidSyntax(): void
    {
        $this->expectException(SendEmailException::class);

        // NOTE: inevitably users will assume they need to fill
        // in the variables and do something like below
        $subject = '{{#Attendance}}{{ what is this }}';
        $this->getMessage(null, [], $subject, 'twig');
    }

    public function testTemplateVars(): void
    {
        $expected = [
            'highlightColor' => '#303030',
            'logo' => null,
            'companyName' => 'Test Company',
            'companyNameWithAddress' => 'Test Company, Company, Address, Austin, TX 78701, United States',
            'testMode' => false,
            'showPoweredBy' => false,
            'body' => 'test',
        ];

        $factory = $this->getFactory();
        $templateVars = $factory->getTemplateVars(self::$company, 'test');
        $this->assertEquals($expected, $templateVars);
    }

    public function testBody(): void
    {
        $message = $this->getMessage('Test');

        $this->assertStringContainsString('Test', $message->getBody());
    }

    public function testMustacheBody(): void
    {
        DocumentEmailFactory::$emailVariables = ['test' => 'Hello, world!'];
        $message = $this->getMessage('{{test}}{{{test}}}{TEST}');
        $this->assertStringStartsWith('Hello, world!Hello, world!{TEST}', $message->getBody());

        // Currently we allow HTML in message bodies since
        // email clients should provide XSS protection. However,
        // we do not allow HTML to be injected from email variables.
        DocumentEmailFactory::$emailVariables = ['test' => 'Hello, world!<script>Should be escaped</script>'];
        $message = $this->getMessage('{{test}}{{{test}}}{TEST}<script>Should not be escaped</script>');
        $this->assertStringStartsWith('Hello, world!&lt;script&gt;Should be escaped&lt;/script&gt;Hello, world!<script>Should be escaped</script>{TEST}<script>Should not be escaped</script>', $message->getBody());
    }

    public function testMustacheBodyInvalidSyntax(): void
    {
        $this->expectException(SendEmailException::class);

        // NOTE: inevitably users will assume they need to fill
        // in the variables and do something like below
        $body = 'Hi {{Attendance}},

We have received your payment for invoice # {{invoice_0005}}, thank you. Attached is a receipt for your payment.

Received On: {{Oct 3,2017}}
Amount: {{3900 BDT}}
Payment Method: {{payment_method}}{{#Hand cash}} -';

        $this->getMessage($body);
    }

    public function testTwigBody(): void
    {
        DocumentEmailFactory::$emailVariables = ['test' => 'Hello, world!'];
        $message = $this->getMessage('{{test}}{TEST}{% if test %}pass{% else %}fail{% endif %}', [], null, 'twig');
        $this->assertStringStartsWith('Hello, world!{TEST}pass', $message->getBody());

        // Currently we allow HTML in message bodies since
        // email clients should provide XSS protection. However,
        // we do not allow HTML to be injected from email variables.
        DocumentEmailFactory::$emailVariables = ['test' => 'Hello, world!<script>Should be escaped</script>'];
        $message = $this->getMessage('{{test}}{{test|raw}}{TEST}<script>Should not be escaped</script>', [], null, 'twig');
        $this->assertStringStartsWith('Hello, world!&lt;script&gt;Should be escaped&lt;/script&gt;Hello, world!<script>Should be escaped</script>{TEST}<script>Should not be escaped</script>', $message->getBody());
    }

    public function testTwigBodyInvalidSyntax(): void
    {
        $this->expectException(SendEmailException::class);

        // NOTE: inevitably users will assume they need to fill
        // in the variables and do something like below
        $body = 'Hi {{Attendance}},

We have received your payment for invoice # {{invoice_0005}}, thank you. Attached is a receipt for your payment.

Received On: {{Oct 3,2017}}
Amount: {{3900 BDT}}
Payment Method: {{payment_method}}{{#Hand cash}} -';

        $this->getMessage($body, [], null, 'twig');
    }

    public function testPlainText(): void
    {
        $message = $this->getMessage('Test<script></script>&nbsp;&nbsp;');

        $expected = 'Test

View Invoice: '.self::$invoice->url;
        $this->assertEquals($expected, $message->getPlainText());
    }

    public function testHtml(): void
    {
        $message = $this->getMessage("Test<script></script>&nbsp;&nbsp;\n\n");

        $expected = 'Test<script></script>&nbsp;&nbsp;<br />
<br />
<center style="width: 100%; min-width: 532px;" class=""><table class="button radius" align="center" style="border-spacing: 0; border-collapse: collapse; padding: 0; vertical-align: top; text-align: left; width: auto; margin: 0 0 16px 0; Margin: 0 0 16px 0;"><tbody class=""><tr style="padding: 0; vertical-align: top; text-align: left;" class=""><td style="word-wrap: break-word; -webkit-hyphens: auto; -moz-hyphens: auto; hyphens: auto; border-collapse: collapse !important; vertical-align: top; color: #ffffff; font-family: Helvetica, Arial, sans-serif; font-weight: normal; padding: 0; margin: 0; Margin: 0; text-align: left; font-size: 16px; line-height: 1.3;" class=""><table style="border-spacing: 0; border-collapse: collapse; padding: 0; vertical-align: top; text-align: left; width: 100%;" class=""><tbody class=""><tr style="padding: 0; vertical-align: top; text-align: left;" class=""><td style="word-wrap: break-word; -webkit-hyphens: auto; -moz-hyphens: auto; hyphens: auto; border-collapse: collapse !important; vertical-align: top; font-family: Helvetica, Arial, sans-serif; font-weight: normal; padding: 0; margin: 0; Margin: 0; font-size: 16px; line-height: 1.3; text-align: left; color: #ffffff; background: #348eda; border-radius: 3px; border: none;" class=""><a href="'.self::$invoice->url.'" style="margin: 0; Margin: 0; text-align: left; line-height: 1.3; font-family: Helvetica, Arial, sans-serif; font-size: 16px; font-weight: bold; color: #ffffff; text-decoration: none; display: inline-block; padding: 8px 16px 8px 16px; border: 0 solid #348eda; border-radius: 3px;" class="">View Invoice</a></td></tr></tbody></table></td></tr></tbody></table></center><span itemscope itemtype="http://schema.org/EmailMessage"><meta itemprop="description" content="Please review and pay your invoice"/><span itemprop="action" itemscope itemtype="http://schema.org/ViewAction"><meta itemprop="url" content="'.self::$invoice->url.'"/><meta itemprop="name" content="View Invoice"/></span><span itemprop="publisher" itemscope itemtype="http://schema.org/Organization"><meta itemprop="name" content="Invoiced"/><meta itemprop="url" content="https://invoiced.com"/></span></span>';

        $html = $message->getHtml();
        $this->assertStringContainsString($expected, (string) $html);

        // Currently we allow HTML in message bodies since
        // email clients should provide XSS protection. However,
        // we do not allow HTML to be injected from email variables.
        $this->assertStringContainsString('<script></script>', (string) $html);
    }

    public function testHtmlWithTracking(): void
    {
        $message = $this->getMessage('Hello!<plainTextOnly>: http://test1640119515347500196.invoiced.localhost:1234/estimates/test_client_id?currency=usd&date=1234&what=//_.-jf%ls</plainTextOnly>', [['email' => 'test@example.com']]);

        $html = (string) $message->getHtml(true);
        $this->assertStringContainsString('Hello!', $html);
        $this->assertStringNotContainsString('<plainTextOnly>', $html);
        $this->assertStringNotContainsString('</plainTextOnly>', $html);
        $pixel = $message->getTrackingPixel('test@example.com');
        $this->assertStringContainsString($pixel->__toString(), $html);
    }

    public function testGetTrackingPixel(): void
    {
        $message = $this->getMessage();

        $pixel = $message->getTrackingPixel('test@example.com');
        $this->assertInstanceOf('App\Sending\Email\ValueObjects\TrackingPixel', $pixel);

        $pixel2 = $message->getTrackingPixel('test2@example.com');
        $this->assertNotEquals($pixel->getId(), $pixel2->getId());

        $pixel3 = $message->getTrackingPixel('test@example.com');
        $this->assertEquals($pixel, $pixel3);
    }

    public function testAttachmentsPdf(): void
    {
        $emailTemplate = new EmailTemplate();
        $emailTemplate->id = 'new_invoice_email';
        $emailTemplate = Mockery::mock($emailTemplate);
        $emailTemplate->shouldReceive('getOption')
            ->withArgs([EmailTemplateOption::ATTACH_PDF])
            ->andReturn(true);
        $emailTemplate->shouldReceive('getOption')
            ->withArgs([EmailTemplateOption::ATTACH_SECONDARY_FILES])
            ->andReturn(false);

        $pdf = Mockery::mock(PdfBuilderInterface::class);
        $pdf->shouldReceive('getFilename')
            ->andReturn('filename.pdf');
        $pdf->shouldReceive('build')
            ->andReturn('pdf');

        $object = $this->getSendableDocument($pdf);

        $message = $this->getFactory()->make($object, $emailTemplate, self::$invoice->getDefaultEmailContacts());

        $attachments = $message->getAttachments();
        $this->assertCount(1, $attachments);
        $this->assertEquals('filename.pdf', $attachments[0]->getFilename());
        $this->assertEquals('application/pdf', $attachments[0]->getType());
        $this->assertEquals('pdf', $attachments[0]->getContent());
    }

    public function testAttachmentsSecondary(): void
    {
        $emailTemplate = new EmailTemplate();
        $emailTemplate->id = 'new_invoice_email';
        $emailTemplate = Mockery::mock($emailTemplate);
        $emailTemplate->shouldReceive('getOption')
            ->withArgs([EmailTemplateOption::ATTACH_PDF])
            ->andReturn(false);
        $emailTemplate->shouldReceive('getOption')
            ->withArgs([EmailTemplateOption::ATTACH_SECONDARY_FILES])
            ->andReturn(true);
        $emailTemplate->shouldReceive('getOption')
            ->withArgs([EmailTemplateOption::BUTTON_TEXT])
            ->andReturn('view');

        $file = new File();
        $file->name = 'file.xlsx';
        $file->type = 'application/vnd.ms-excel';
        $file->size = 100;
        if ($url = getenv('TEST_ATTACHMENT_ENDPOINT')) {
            $file->url = $url.'/custom_pdf_test';
        } else {
            $file->url = 'http://localhost/custom_pdf_test';
        }
        $file->saveOrFail();
        $file->setContent('file contents');

        $attachment = new Attachment();
        $attachment->setParent(self::$invoice);
        $attachment->setFile($file);
        $attachment->saveOrFail();

        $message = $this->getFactory()->make(self::$invoice, $emailTemplate, self::$invoice->getDefaultEmailContacts());

        $attachments = $message->getAttachments();
        $this->assertCount(1, $attachments);
        $this->assertEquals('file.xlsx', $attachments[0]->getFilename());
        $this->assertEquals('application/vnd.ms-excel', $attachments[0]->getType());
        $this->assertEquals('this is used by the test suite. do not delete this file.', $attachments[0]->getContent());
    }

    public function testAttachmentsFail(): void
    {
        $this->expectException(SendEmailException::class);
        $this->expectExceptionMessage('Unable to build message attachments.');

        $emailTemplate = new EmailTemplate();
        $emailTemplate->id = 'new_invoice_email';
        $emailTemplate = Mockery::mock($emailTemplate);
        $emailTemplate->shouldReceive('getOption')
            ->withArgs([EmailTemplateOption::ATTACH_PDF])
            ->andReturn(true);
        $emailTemplate->shouldReceive('getOption')
            ->withArgs([EmailTemplateOption::ATTACH_SECONDARY_FILES])
            ->andReturn(false);

        $pdf = Mockery::mock(PdfBuilderInterface::class);
        $pdf->shouldReceive('getFilename')
            ->andReturn('filename.pdf');
        $pdf->shouldReceive('build')
            ->andThrow(new PdfException('fail'));

        $object = $this->getSendableDocument($pdf);

        $message = $this->getFactory()->make($object, $emailTemplate, self::$invoice->getDefaultEmailContacts());
        $attachments = $message->getAttachments();
        // force the content to generate
        $attachments[0]->getContent();
    }

    public function testHeaders(): void
    {
        $message = $this->getMessage();
        /** @var EmailThread $thread */
        $thread = $message->getEmailThread();

        $expected = [
            'Reply-To' => 'Test Company <'.self::$company->accounts_receivable_settings->reply_to_inbox?->external_id.'@test.invoicedmail.com>',
            'X-Invoiced' => 'true',
            'X-Invoiced-Account' => self::$company->identifier,
            'X-Invoiced-Url' => self::$invoice->url,
            'X-Auto-Response-Suppress' => 'All',
            'Message-ID' => '<'.self::$company->username.'/'.$message->getId().'@invoiced.com>',
            'In-Reply-To' => '<'.self::$company->username.'/threads/'.$thread->id().'@invoiced.com>',
            'References' => '<'.self::$company->username.'/threads/'.$thread->id().'@invoiced.com>',
        ];

        $this->assertEquals($expected, $message->getHeaders());
    }

    public function testHeadersCompanyReply(): void
    {
        self::$company->accounts_receivable_settings->reply_to_inbox = null;
        self::$company->accounts_receivable_settings->saveOrFail();
        $message = $this->getMessage();
        /** @var EmailThread $thread */
        $thread = $message->getEmailThread();

        $expected = [
            'Reply-To' => 'Test Company <test@example.com>',
            'X-Invoiced' => 'true',
            'X-Invoiced-Account' => self::$company->identifier,
            'X-Invoiced-Url' => self::$invoice->url,
            'X-Auto-Response-Suppress' => 'All',
            'Message-ID' => '<'.self::$company->username.'/'.$message->getId().'@invoiced.com>',
            'In-Reply-To' => '<'.self::$company->username.'/threads/'.$thread->id().'@invoiced.com>',
            'References' => '<'.self::$company->username.'/threads/'.$thread->id().'@invoiced.com>',
        ];

        $this->assertEquals($expected, $message->getHeaders());
    }

    private function getMessage(?string $body = null, array $to = [], ?string $subject = null, string $engine = 'mustache'): DocumentEmail
    {
        $emailTemplate = EmailTemplate::make(self::$company->id, EmailTemplate::NEW_INVOICE);
        $emailTemplate->template_engine = $engine;
        $to = $to ?: self::$invoice->getDefaultEmailContacts();

        return $this->getFactory()->make(self::$invoice, $emailTemplate, $to, [], null, $subject, $body);
    }

    private function getSendableDocument(PdfBuilderInterface $pdf, ?Company $company = null): SendableDocumentInterface
    {
        $company = $company ?? self::$company;
        $object = Mockery::mock(SendableDocumentInterface::class)->makePartial();
        $object->shouldReceive('getSendCompany')
            ->andReturn($company);
        $object->shouldReceive('getSendCustomer')
            ->andReturn(self::$customer);
        $object->shouldReceive('getSendObjectType')
            ->andReturn(null);
        $variables = Mockery::mock(EmailVariablesInterface::class);
        $variables->shouldReceive('generate')
            ->andReturn([]);
        $variables->shouldReceive('getCurrency')
            ->andReturn('usd');
        $object->shouldReceive('getEmailVariables')
            ->andReturn($variables);
        $object->shouldReceive('getPdfBuilder')
            ->andReturn($pdf);
        $object->shouldReceive('schemaOrgActions')
            ->andReturn(null);
        $object->shouldReceive('getSendClientUrl')
            ->andReturn(null);
        $object->shouldReceive('getThreadName')
            ->andReturn('test');

        return $object;
    }
}
