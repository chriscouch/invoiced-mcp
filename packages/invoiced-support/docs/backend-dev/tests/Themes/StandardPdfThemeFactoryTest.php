<?php

namespace App\Tests\Themes;

use App\Tests\AppTestCase;
use App\Themes\Libs\StandardPdfThemeFactory;

class StandardPdfThemeFactoryTest extends AppTestCase
{
    private static string $templatesDir;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        self::$templatesDir = dirname(dirname(__DIR__)).'/templates/pdf';
    }

    public function getFactory(): StandardPdfThemeFactory
    {
        return new StandardPdfThemeFactory();
    }

    public function testBuildClassicInvoice(): void
    {
        $factory = $this->getFactory();
        $theme = $factory->build('classic', 'invoice');
        $this->assertEquals(file_get_contents(self::$templatesDir.'/classic/invoice.twig'), $theme->getBodyHtml());
        $this->assertEquals(file_get_contents(self::$templatesDir.'/classic/invoice.css'), $theme->getBodyCss());
    }

    public function testBuildClassicEstimate(): void
    {
        $factory = $this->getFactory();
        $theme = $factory->build('classic', 'estimate');
        $this->assertEquals(file_get_contents(self::$templatesDir.'/classic/estimate.twig'), $theme->getBodyHtml());
        $this->assertEquals(file_get_contents(self::$templatesDir.'/classic/estimate.css'), $theme->getBodyCss());
    }

    public function testBuildClassicCreditNote(): void
    {
        $factory = $this->getFactory();
        $theme = $factory->build('classic', 'credit_note');
        $this->assertEquals(file_get_contents(self::$templatesDir.'/classic/credit_note.twig'), $theme->getBodyHtml());
        $this->assertEquals(file_get_contents(self::$templatesDir.'/classic/credit_note.css'), $theme->getBodyCss());
    }

    public function testBuildClassicStatement(): void
    {
        $factory = $this->getFactory();
        $theme = $factory->build('classic', 'statement');
        $this->assertEquals(file_get_contents(self::$templatesDir.'/classic/statement.twig'), $theme->getBodyHtml());
        $this->assertEquals(file_get_contents(self::$templatesDir.'/classic/statement.css'), $theme->getBodyCss());
    }

    public function testBuildClassicReceipt(): void
    {
        $factory = $this->getFactory();
        $theme = $factory->build('classic', 'receipt');
        $this->assertEquals(file_get_contents(self::$templatesDir.'/classic/receipt.twig'), $theme->getBodyHtml());
        $this->assertEquals(file_get_contents(self::$templatesDir.'/classic/receipt.css'), $theme->getBodyCss());
    }
}
