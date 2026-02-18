<?php

namespace App\Tests\Themes;

use App\Tests\AppTestCase;
use App\Themes\ValueObjects\PdfTheme;
use App\Themes\Models\PdfTemplate;

class PdfTemplateTest extends AppTestCase
{
    private static PdfTemplate $template;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        self::hasCompany();
    }

    public function testToPdfTheme(): void
    {
        $pdfTemplate = new PdfTemplate();
        $pdfTemplate->margin_bottom = '0';
        $pdfTemplate->margin_top = '0';
        $pdfTemplate->margin_left = '0';
        $pdfTemplate->margin_right = '0';
        $pdfTemplate->html = 'body_html';
        $pdfTemplate->css = 'body_css';
        $pdfTemplate->header_html = 'header_html';
        $pdfTemplate->header_css = 'header_css';
        $pdfTemplate->footer_html = 'footer_html';
        $pdfTemplate->footer_css = 'footer_css';

        $pdfTheme = $pdfTemplate->toPdfTheme();
        $this->assertEquals([
            'margin-top' => '0',
            'margin-bottom' => '0',
            'margin-left' => '0',
            'margin-right' => '0',
        ], $pdfTheme->getPdfOptions());
        $this->assertEquals(PdfTheme::TEMPLATE_ENGINE_TIWG, $pdfTheme->getTemplateEngine());
        $this->assertEquals('body_html', $pdfTheme->getBodyHtml());
        $this->assertEquals('body_css', $pdfTheme->getBodyCss());
        $this->assertEquals('header_html', $pdfTheme->getHeaderHtml());
        $this->assertEquals('header_css', $pdfTheme->getHeaderCss());
        $this->assertEquals('footer_html', $pdfTheme->getFooterHtml());
        $this->assertEquals('footer_css', $pdfTheme->getFooterCss());

        // test with smart shrinking disabled
        $pdfTemplate->disable_smart_shrinking = true;
        $pdfTheme = $pdfTemplate->toPdfTheme();
        $this->assertEquals([
            'margin-top' => '0',
            'margin-bottom' => '0',
            'margin-left' => '0',
            'margin-right' => '0',
            'disable-smart-shrinking',
        ], $pdfTheme->getPdfOptions());
    }

    public function testCreate(): void
    {
        self::$template = new PdfTemplate();
        self::$template->name = 'Test';
        self::$template->document_type = 'invoice';
        self::$template->html = 'html';
        self::$template->css = 'css';
        $this->assertTrue(self::$template->save());

        $this->assertEquals(self::$company->id(), self::$template->tenant_id);
    }

    /**
     * @depends testCreate
     */
    public function testEdit(): void
    {
        self::$template->html = 'html2';
        $this->assertTrue(self::$template->save());
    }

    /**
     * @depends testCreate
     */
    public function testQuery(): void
    {
        $templates = PdfTemplate::all();

        $this->assertCount(1, $templates);
        $this->assertEquals(self::$template->id(), $templates[0]->id());
    }

    /**
     * @depends testCreate
     */
    public function testToArray(): void
    {
        $expected = [
            'id' => self::$template->id(),
            'document_type' => 'invoice',
            'html' => 'html2',
            'css' => 'css',
            'footer_css' => null,
            'footer_html' => null,
            'header_css' => null,
            'header_html' => null,
            'margin_bottom' => '0.5cm',
            'margin_left' => '0.5cm',
            'margin_right' => '0.5cm',
            'margin_top' => '0.5cm',
            'disable_smart_shrinking' => false,
            'name' => 'Test',
            'template_engine' => 'twig',
            'created_at' => self::$template->created_at,
            'updated_at' => self::$template->updated_at,
        ];

        $this->assertEquals($expected, self::$template->toArray());
    }

    /**
     * @depends testCreate
     */
    public function testDelete(): void
    {
        $this->assertTrue(self::$template->delete());
    }
}
