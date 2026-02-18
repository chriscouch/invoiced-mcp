<?php

namespace App\Tests\AccountsReceivable\Pdf;

use App\AccountsReceivable\Models\CreditNote;
use App\AccountsReceivable\Pdf\CreditNotePdf;
use App\Core\Files\Models\Attachment;
use App\Core\Files\Models\File;
use App\Tests\Themes\AbstractCustomerPdfTest;
use App\Themes\Pdf\AbstractCustomerPdf;

class CreditNotePdfTest extends AbstractCustomerPdfTest
{
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        self::hasInvoice();
        self::hasCreditNote();
    }

    protected function getObject(): CreditNote
    {
        return self::$creditNote;
    }

    protected function getPdfBuilder(): AbstractCustomerPdf
    {
        return new CreditNotePdf($this->getObject());
    }

    protected function getExpectedFilename(): string
    {
        return 'Credit Note CN-00001.pdf';
    }

    protected function getExpectedBodyHtmlTemplate(): string
    {
        return (string) file_get_contents(self::$kernel->getProjectDir().'/templates/pdf/classic/credit_note.twig');
    }

    protected function getExpectedBodyCss(): string
    {
        return (string) file_get_contents(self::$kernel->getProjectDir().'/templates/pdf/classic/credit_note.css');
    }

    protected function getExpectedHtmlParameters(): array
    {
        $theme = self::$company->theme();
        $object = $this->getObject();

        return [
            'theme' => $theme->getThemeVariables()->generate($theme),
            'company' => self::$company->getThemeVariables()->generate($theme, ['showCountry' => false]),
            'customer' => self::$customer->getThemeVariables()->generate($theme),
            'credit_note' => $object->getThemeVariables()->generate($theme),
        ];
    }

    /**
     * @doesNotPerformAssertions
     */
    public function testHtmlMustacheParseFail(): void
    {
        // not used for credit notes
    }

    /**
     * @doesNotPerformAssertions
     */
    public function testHtmlTwigParseFail(): void
    {
        // not used for credit notes
    }

    public function testBuildPdfAttachmentOverride(): void
    {
        /** @var CreditNotePdf $pdf */
        $pdf = $this->getPdfBuilder();

        $file = new File();
        $file->name = 'Invoice.pdf';
        $file->size = 1024;
        $file->type = 'application/pdf';
        if ($url = getenv('TEST_ATTACHMENT_ENDPOINT')) {
            $file->url = $url.'/custom_pdf_test';
        } else {
            $file->url = 'http://localhost/custom_pdf_test';
        }
        $file->saveOrFail();

        $document = $pdf->getDocument();
        $attachment = new Attachment();
        $attachment->parent_type = $document->object;
        $attachment->parent_id = (int) $document->id();
        $attachment->location = Attachment::LOCATION_PDF;
        $attachment->file_id = (int) $file->id();
        $attachment->saveOrFail();

        $this->assertEquals('this is used by the test suite. do not delete this file.', $pdf->build('en_US'));
    }
}
