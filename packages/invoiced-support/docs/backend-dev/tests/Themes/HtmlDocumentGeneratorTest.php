<?php

namespace App\Tests\Themes;

use App\Companies\Models\Company;
use App\Core\Pdf\Exception\PdfException;
use App\Tests\AppTestCase;
use App\Themes\Libs\HtmlDocumentGenerator;
use App\Themes\Pdf\AbstractCustomerPdf;
use App\Themes\ValueObjects\PdfTheme;
use Mockery;

class HtmlDocumentGeneratorTest extends AppTestCase
{
    public function getGenerator(string $engine): HtmlDocumentGenerator
    {
        $pdfTheme = new PdfTheme($engine, '', '');
        $company = new Company();

        $customerPdf = Mockery::mock(AbstractCustomerPdf::class);
        $customerPdf->shouldReceive('setLocale');
        $customerPdf->shouldReceive('getPdfTheme')
            ->andReturn($pdfTheme);
        $customerPdf->shouldReceive('getHtmlParameters')
            ->andReturn(['testVariable' => 'test']);
        $customerPdf->shouldReceive('getCompany')
            ->andReturn($company);
        $customerPdf->shouldReceive('getDocumentCurrency')
            ->andReturn('usd');
        $customerPdf->shouldReceive('getMoneyFormat')
            ->andReturn([]);
        $customerPdf->shouldReceive('getFilename')
            ->andReturn('Invoice.pdf');
        $customerPdf->shouldReceive('getTranslator')
            ->andReturn(self::getService('translator'));

        return new HtmlDocumentGenerator($customerPdf);
    }

    public function testHtmlMustache(): void
    {
        $generator = $this->getGenerator('mustache');
        $html = $generator->build('<div id="{{testVariable}}"></div>', 'body { background: red; }', true, 'en_US');

        $this->assertGreaterThan(0, strlen($html));
        $this->assertStringStartsWith('<!DOCTYPE html>', $html);
        $this->assertDoesNotMatchRegularExpression('/E_NOTICE|E_WARNING|Notice|Warning/', $html);
        $this->assertStringContainsString('<div id="test"></div>', $html);
        $this->assertStringContainsString('body { background: red; }', $html);
    }

    public function testHtmlMustacheParseFail(): void
    {
        $this->expectException(PdfException::class);

        $generator = $this->getGenerator('mustache');
        $generator->build('{{#Attendance}}{{ what is this }}', null, true, 'en_US');
    }

    public function testHtmlTwig(): void
    {
        $generator = $this->getGenerator('twig');
        $html = $generator->build('<div id="{{testVariable}}"></div>', 'body { background: red; }', true, 'en_US');

        $this->assertGreaterThan(0, strlen($html));
        $this->assertStringStartsWith('<!DOCTYPE html>', $html);
        $this->assertDoesNotMatchRegularExpression('/E_NOTICE|E_WARNING|Notice|Warning/', $html);
        $this->assertStringContainsString('<div id="test"></div>', $html);
        $this->assertStringContainsString('body { background: red; }', $html);
    }

    public function testHtmlTwigParseFail(): void
    {
        $this->expectException(PdfException::class);

        $generator = $this->getGenerator('twig');
        $generator->build('{% if Attendance %}{{ what is this }}', null, true, 'en_US');
    }
}
