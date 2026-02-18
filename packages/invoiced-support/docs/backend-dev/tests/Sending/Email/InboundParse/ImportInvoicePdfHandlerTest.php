<?php

namespace App\Tests\Sending\Email\InboundParse;

use App\AccountsReceivable\Models\Invoice;
use App\Core\Authentication\Models\User;
use App\Core\Files\Libs\DocumentPdfUploader;
use App\Core\Files\Libs\NullFileCreator;
use App\Core\Files\Models\Attachment;
use App\Core\Files\Models\File;
use App\Core\Orm\ACLModelRequester;
use App\Core\Orm\Model;
use App\Core\Statsd\StatsdClient;
use App\Core\Utils\Enums\ObjectType;
use App\Sending\Email\InboundParse\Handlers\ImportInvoicePdfHandler;
use App\Tests\AppTestCase;
use Mockery;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;

class ImportInvoicePdfHandlerTest extends AppTestCase
{
    private static ?Model $requester;
    private static User $originalUser;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        self::hasCompany();
        self::hasCustomer();
        self::$invoice = new Invoice();
        self::$invoice->draft = true;
        self::$invoice->setCustomer(self::$customer);
        self::$invoice->items = [
            [
                'name' => 'Test Item',
                'description' => 'test',
                'quantity' => 1,
                'unit_cost' => 100,
            ],
        ];
        self::$invoice->saveOrFail();

        self::$requester = ACLModelRequester::get();
        self::$originalUser = self::getService('test.user_context')->get();
    }

    public static function tearDownAfterClass(): void
    {
        parent::tearDownAfterClass();
        self::getService('test.user_context')->set(self::$originalUser);
        ACLModelRequester::set(self::$requester);
    }

    private function getHandler(?DocumentPdfUploader $pdfUploader = null): ImportInvoicePdfHandler
    {
        $pdfUploader = $pdfUploader ?? Mockery::mock(DocumentPdfUploader::class);
        $handler = new ImportInvoicePdfHandler(self::getService('test.user_context'), self::getService('test.tenant'), $pdfUploader, self::$kernel->getProjectDir(), 'test.invoicedmail.com');
        $handler->setCompany(self::$company);
        $handler->setStatsd(new StatsdClient());

        return $handler;
    }

    public function testParseInvoiceNumber(): void
    {
        $handler = $this->getHandler();
        $this->assertEquals('INV-0001', $handler->parseInvoiceNumber('Invoice INV-0001.pdf'));
        $this->assertEquals('INV-0001', $handler->parseInvoiceNumber('invoice_INV-0001.pdf'));
        $this->assertEquals('INV-0001', $handler->parseInvoiceNumber('INV-0001.pdf'));
    }

    public function testProcessEmail(): void
    {
        $parameters = [
            'from' => 'jared@invoiced.com',
            'text' => 'Comment text...',
            'attachment-info' => json_encode([
                'file1' => [],
            ]),
        ];

        // make a backup of the source
        copy(__DIR__.'/test_invoice.orig.pdf', __DIR__.'/test_invoice.pdf');

        $uploadedFile = new UploadedFile(
            __DIR__.'/test_invoice.pdf',
            self::$invoice->number.'.pdf',
            'application/pdf',
            UPLOAD_ERR_OK,
            true
        );
        $files = ['file1' => $uploadedFile];

        $request = Request::create('/sendgrid/inbound', 'POST', $parameters, [], $files);

        $creator = Mockery::mock(NullFileCreator::class);
        $creator->shouldReceive('create')
            ->withAnyArgs()
            ->andReturnUsing(function($bucket, $fileName, $originalFile, $key, $awsParameters) {
                $file = new File([
                    'name' => $fileName,
                    'size' => 1000,
                    'type' => 'application/pdf',
                    'url' => 'https://example.com/' . $key,
                    'bucket_name' => $bucket,
                    'key' => $key
                ]);
                $file->saveOrFail();
                return $file;
            });

        $uploader = new DocumentPdfUploader($creator, 'test', 'test');
        $handler = $this->getHandler($uploader);
        $handler->processEmail($request);

        // should create an attachment
        $attachment = Attachment::where('parent_type', ObjectType::Invoice->typeName())
            ->where('parent_id', self::$invoice)
            ->oneOrNull();
        $this->assertInstanceOf(Attachment::class, $attachment);
        $this->assertEquals(Attachment::LOCATION_PDF, $attachment->location);
        $file = $attachment->file();
        $this->assertEquals('application/pdf', $file->type);

        // invoice should be issued
        $this->assertFalse(self::$invoice->refresh()->draft);
    }
}
