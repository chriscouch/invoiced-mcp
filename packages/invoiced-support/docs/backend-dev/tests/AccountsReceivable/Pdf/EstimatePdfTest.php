<?php

namespace App\Tests\AccountsReceivable\Pdf;

use App\AccountsReceivable\Models\Estimate;
use App\AccountsReceivable\Pdf\EstimatePdf;
use App\Core\Files\Models\Attachment;
use App\Core\Files\Models\File;
use App\Tests\Themes\AbstractCustomerPdfTest;
use App\Themes\Pdf\AbstractCustomerPdf;

class EstimatePdfTest extends AbstractCustomerPdfTest
{
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        self::hasInvoice();
        self::hasEstimate();
    }

    protected function getObject(): Estimate
    {
        return self::$estimate;
    }

    protected function getPdfBuilder(): AbstractCustomerPdf
    {
        return new EstimatePdf($this->getObject());
    }

    protected function getExpectedFilename(): string
    {
        return 'Estimate EST-00001.pdf';
    }

    protected function getExpectedBodyHtmlTemplate(): string
    {
        return (string) file_get_contents(self::$kernel->getProjectDir().'/templates/pdf/classic/estimate.twig');
    }

    protected function getExpectedBodyCss(): string
    {
        return (string) file_get_contents(self::$kernel->getProjectDir().'/templates/pdf/classic/estimate.css');
    }

    protected function getExpectedHtmlParameters(): array
    {
        $theme = self::$company->theme();
        $object = $this->getObject();

        return [
            'theme' => $theme->getThemeVariables()->generate($theme),
            'company' => self::$company->getThemeVariables()->generate($theme, ['showCountry' => false]),
            'customer' => self::$customer->getThemeVariables()->generate($theme),
            'estimate' => $object->getThemeVariables()->generate($theme),
        ];
    }

    public function testBuildPdfAttachmentOverride(): void
    {
        /** @var EstimatePdf $pdf */
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
