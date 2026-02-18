<?php

namespace App\Tests\Themes;

use App\Tests\AppTestCase;
use App\Themes\Models\Theme;
use App\Themes\Models\PdfTemplate;

class ThemeTest extends AppTestCase
{
    private static Theme $theme;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        self::hasCompany();
    }

    public function testCustomPdfTemplate(): void
    {
        $theme = new Theme();
        $this->assertNull($theme->getCustomPdfTemplate('invoice'));

        $pdfTemplate = new PdfTemplate(['id' => -1]);
        $theme->invoice_template_id = -1;
        $theme->setRelation('invoice_template_id', $pdfTemplate);

        $this->assertEquals($pdfTemplate, $theme->getCustomPdfTemplate('invoice'));

        // these currently should not have a custom template assigned
        $this->assertNull($theme->getCustomPdfTemplate('estimate'));
        $this->assertNull($theme->getCustomPdfTemplate('statement'));
        $this->assertNull($theme->getCustomPdfTemplate('receipt'));
        $this->assertNull($theme->getCustomPdfTemplate('credit_note'));

        // assign a custom template to the credit note
        $theme->credit_note_template_id = -1;
        $theme->setRelation('credit_note_template_id', $pdfTemplate);
        $this->assertEquals($pdfTemplate, $theme->getCustomPdfTemplate('credit_note'));
    }

    public function testGetPdfTheme(): void
    {
        $theme = new Theme();

        $html = (string) file_get_contents(self::$kernel->getProjectDir().'/templates/pdf/classic/invoice.twig');
        $css = (string) file_get_contents(self::$kernel->getProjectDir().'/templates/pdf/classic/invoice.css');
        $this->assertEquals($html, $theme->getPdfTheme('invoice')->getBodyHtml());
        $this->assertEquals($css, $theme->getPdfTheme('invoice')->getBodyCss());

        $pdfTemplate = new PdfTemplate(['id' => -1]);
        $pdfTemplate->html = 'html';
        $pdfTemplate->css = 'css';
        $theme->invoice_template_id = -1;
        $theme->setRelation('invoice_template_id', $pdfTemplate);
        $this->assertEquals('html', $theme->getPdfTheme('invoice')->getBodyHtml());
        $this->assertEquals('css', $theme->getPdfTheme('invoice')->getBodyCss());
    }

    public function testCreateMissingID(): void
    {
        $theme = new Theme();
        $theme->name = 'Test';
        $this->assertFalse($theme->save());
    }

    public function testCreateInvalidID(): void
    {
        $theme = new Theme();
        $theme->name = 'Test';
        $theme->id = '$*%)#*%#)%';
        $this->assertFalse($theme->save());
    }

    public function testCreate(): void
    {
        self::$theme = new Theme();
        self::$theme->name = 'Test';
        self::$theme->id = 'test';
        $this->assertTrue(self::$theme->save());

        $this->assertEquals(self::$company->id(), self::$theme->tenant_id);
        $this->assertEquals('Test', self::$theme->name);
    }

    /**
     * @depends testCreate
     */
    public function testCreateNonUnique(): void
    {
        $theme = new Theme();
        $theme->name = 'Test';
        $theme->id = 'test';
        $this->assertFalse($theme->save());
    }

    /**
     * @depends testCreate
     */
    public function testEdit(): void
    {
        self::$theme->to_title = 'Customer';
        $this->assertTrue(self::$theme->save());
    }

    /**
     * @depends testCreate
     */
    public function testQuery(): void
    {
        $themes = Theme::all();

        $this->assertCount(1, $themes);
        $this->assertEquals(self::$theme->id(), $themes[0]->id());
    }

    public function testToArrayDefault(): void
    {
        $default = new Theme([self::$company->id(), null]);

        $expected = [
            'id' => null,
            'name' => 'Default',
            'from_title' => 'From',
            'to_title' => 'Bill To',
            'ship_to_title' => 'Ship To',
            'customer_number_title' => 'Account #',
            'show_company_email' => true,
            'show_company_phone' => true,
            'show_company_website' => true,
            'show_customer_no' => false,
            'invoice_number_title' => 'Invoice Number',
            'date_title' => 'Date',
            'payment_terms_title' => 'Payment Terms',
            'due_date_title' => 'Due Date',
            'purchase_order_title' => 'Purchase Order',
            'quantity_header' => 'Quantity',
            'item_header' => 'Item',
            'unit_cost_header' => 'Rate',
            'amount_header' => 'Amount',
            'subtotal_title' => 'Subtotal',
            'notes_title' => 'Notes',
            'terms_title' => 'Terms',
            'terms' => null,
            'header' => 'INVOICE',
            'amount_paid_title' => 'Amount Paid',
            'balance_title' => 'Balance Due',
            'header_estimate' => 'ESTIMATE',
            'estimate_number_title' => 'Estimate Number',
            'estimate_footer' => null,
            'total_title' => 'Total',
            'header_receipt' => 'RECEIPT',
            'amount_title' => 'Amount',
            'payment_method_title' => 'Payment Method',
            'check_no_title' => 'Check #',
            'receipt_footer' => 'Thank you!',
            'use_translations' => true,
            'style' => 'classic',
            'credit_note_template_id' => null,
            'estimate_template_id' => null,
            'statement_template_id' => null,
            'receipt_template_id' => null,
            'invoice_template_id' => null,
        ];

        $arr = $default->toArray();
        unset($arr['created_at']);
        unset($arr['updated_at']);

        $this->assertEquals($expected, $arr);
    }

    /**
     * @depends testCreate
     */
    public function testToArray(): void
    {
        $expected = [
            'id' => 'test',
            'name' => 'Test',
            'from_title' => 'From',
            'to_title' => 'Customer',
            'ship_to_title' => 'Ship To',
            'customer_number_title' => 'Account #',
            'show_company_email' => true,
            'show_company_phone' => true,
            'show_company_website' => true,
            'show_customer_no' => false,
            'invoice_number_title' => 'Invoice Number',
            'date_title' => 'Date',
            'payment_terms_title' => 'Payment Terms',
            'due_date_title' => 'Due Date',
            'purchase_order_title' => 'Purchase Order',
            'quantity_header' => 'Quantity',
            'item_header' => 'Item',
            'unit_cost_header' => 'Rate',
            'amount_header' => 'Amount',
            'subtotal_title' => 'Subtotal',
            'notes_title' => 'Notes',
            'terms_title' => 'Terms',
            'terms' => null,
            'header' => 'INVOICE',
            'amount_paid_title' => 'Amount Paid',
            'balance_title' => 'Balance Due',
            'header_estimate' => 'ESTIMATE',
            'estimate_number_title' => 'Estimate Number',
            'estimate_footer' => null,
            'total_title' => 'Total',
            'header_receipt' => 'RECEIPT',
            'amount_title' => 'Amount',
            'payment_method_title' => 'Payment Method',
            'check_no_title' => 'Check #',
            'receipt_footer' => 'Thank you!',
            'use_translations' => true,
            'style' => 'classic',
            'credit_note_template_id' => null,
            'estimate_template_id' => null,
            'statement_template_id' => null,
            'receipt_template_id' => null,
            'invoice_template_id' => null,
            'created_at' => self::$theme->created_at,
            'updated_at' => self::$theme->updated_at,
        ];

        $this->assertEquals($expected, self::$theme->toArray());
    }

    public function testGetDefault(): void
    {
        $default = new Theme([self::$company->id(), null]);

        // test a random key
        $this->assertEquals('Date', $default->date_title);

        // sample random keys
        $this->assertEquals('Date', $default->date_title);
        $this->assertEquals('Subtotal', $default->subtotal_title);
    }

    /**
     * @depends testCreate
     */
    public function testGet(): void
    {
        $this->assertEquals(self::$company->id(), self::$theme->tenant_id);
    }

    /**
     * @depends testCreate
     */
    public function testDelete(): void
    {
        $this->assertTrue(self::$theme->delete());
    }
}
