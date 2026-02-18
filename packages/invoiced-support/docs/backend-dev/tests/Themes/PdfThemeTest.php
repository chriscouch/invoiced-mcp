<?php

namespace App\Tests\Themes;

use App\Tests\AppTestCase;
use App\Themes\ValueObjects\PdfTheme;

class PdfThemeTest extends AppTestCase
{
    public function testGetters(): void
    {
        $theme = new PdfTheme('twig', 'body_html', 'body_css', 'header_html', 'header_css', 'footer_html', 'footer_css', ['test' => true]);
        $this->assertEquals('twig', $theme->getTemplateEngine());
        $this->assertEquals('body_html', $theme->getBodyHtml());
        $this->assertEquals('body_css', $theme->getBodyCss());
        $this->assertEquals('header_html', $theme->getHeaderHtml());
        $this->assertEquals('header_css', $theme->getHeaderCss());
        $this->assertEquals('footer_html', $theme->getFooterHtml());
        $this->assertEquals('footer_css', $theme->getFooterCss());
        $this->assertEquals(['test' => true], $theme->getPdfOptions());
    }
}
