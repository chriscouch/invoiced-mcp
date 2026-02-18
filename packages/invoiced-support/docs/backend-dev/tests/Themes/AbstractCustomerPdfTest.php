<?php

namespace App\Tests\Themes;

use App\Tests\AppTestCase;
use App\Themes\Pdf\AbstractCustomerPdf;
use App\Themes\ValueObjects\PdfTheme;

abstract class AbstractCustomerPdfTest extends AppTestCase
{
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        self::hasCompany();
        self::hasCustomer();
    }

    abstract protected function getPdfBuilder(): AbstractCustomerPdf;

    abstract protected function getExpectedFilename(): string;

    abstract protected function getExpectedBodyHtmlTemplate(): string;

    abstract protected function getExpectedBodyCss(): string;

    abstract protected function getExpectedHtmlParameters(): array;

    public function testGetFilename(): void
    {
        $pdf = $this->getPdfBuilder();
        $this->assertEquals($this->getExpectedFilename(), $pdf->getFilename('en_US'));
    }

    public function testStandardBodyHtmlTemplate(): void
    {
        $pdf = $this->getPdfBuilder();

        $this->assertEquals($this->getExpectedBodyHtmlTemplate(), $pdf->getPdfTheme()->getBodyHtml());
    }

    public function testStandardBodyCss(): void
    {
        $pdf = $this->getPdfBuilder();

        $this->assertEquals($this->getExpectedBodyCss(), $pdf->getPdfTheme()->getBodyCss());
    }

    /**
     * @deprecated
     * should be replace for separate tests for
     * twig and mustache, with mocked expected data
     * instead of calculated one
     */
    public function testGetHtmlParameters(): void
    {
        $pdf = $this->getPdfBuilder();

        // The HTML parameters are tested as being HTML'ified. This
        // requires the Mustache engine is used. In the future there
        // could be tests for HTML'ified and non-HTML'ified parameters.
        $pdfTheme = new PdfTheme('mustache', '', '');
        $pdf->setPdfTheme($pdfTheme);

        $this->assertEquals(
            $this->getExpectedHtmlParameters(),
            $pdf->getHtmlParameters()
        );
    }

    public function testBuild(): void
    {
        $pdf = $this->getPdfBuilder();

        $str = $pdf->build('en_US');
        $this->assertTrue(is_string($str));
        $this->assertGreaterThan(0, strlen($str));
    }
}
