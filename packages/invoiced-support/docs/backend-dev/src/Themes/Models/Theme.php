<?php

namespace App\Themes\Models;

use App\Companies\Models\Company;
use App\Core\Multitenant\Models\MultitenantModel;
use App\Themes\Interfaces\PdfVariablesInterface;
use App\Themes\Interfaces\ThemeableInterface;
use App\Themes\Libs\StandardPdfThemeFactory;
use App\Themes\ValueObjects\PdfTheme;
use App\Themes\ValueObjects\ThemePdfVariables;
use App\Core\Orm\Property;
use App\Core\Orm\Traits\AutoTimestamps;
use App\Core\Orm\Type;

/**
 * @property string      $id
 * @property string      $name
 * @property string      $from_title
 * @property string      $to_title
 * @property string      $ship_to_title
 * @property bool        $show_company_email
 * @property bool        $show_company_phone
 * @property bool        $show_company_website
 * @property string      $customer_number_title
 * @property bool        $show_customer_no
 * @property string      $invoice_number_title
 * @property string      $date_title
 * @property string      $due_date_title
 * @property string      $purchase_order_title
 * @property string      $quantity_header
 * @property string      $item_header
 * @property string      $unit_cost_header
 * @property string      $total_title
 * @property string      $amount_header
 * @property string      $subtotal_title
 * @property string      $notes_title
 * @property string      $terms_title
 * @property string|null $terms
 * @property string      $header
 * @property string      $payment_terms_title
 * @property string      $amount_paid_title
 * @property string      $balance_title
 * @property string      $header_estimate
 * @property string      $estimate_number_title
 * @property string|null $estimate_footer
 * @property string      $header_receipt
 * @property string      $amount_title
 * @property string      $payment_method_title
 * @property string      $check_no_title
 * @property string      $receipt_footer
 * @property bool        $use_translations
 * @property string      $style
 * @property int|null    $credit_note_template_id
 * @property int|null    $estimate_template_id
 * @property int|null    $invoice_template_id
 * @property int|null    $receipt_template_id
 * @property int|null    $statement_template_id
 */
class Theme extends MultitenantModel implements ThemeableInterface
{
    use AutoTimestamps;

    private static StandardPdfThemeFactory $standardThemeFactory;

    protected static function getIDProperties(): array
    {
        return ['tenant_id', 'id'];
    }

    protected static function getProperties(): array
    {
        return [
            'id' => new Property(
                mutable: Property::MUTABLE_CREATE_ONLY,
                required: true,
                validate: [
                    ['callable', 'fn' => [self::class, 'validateID']],
                    ['unique', 'column' => 'id'],
                ],
            ),
            'name' => new Property(
                default: 'Default',
            ),

            /* Documents */

            'from_title' => new Property(
                default: 'From',
            ),
            'to_title' => new Property(
                default: 'Bill To',
            ),
            'ship_to_title' => new Property(
                default: 'Ship To',
            ),
            'show_company_email' => new Property(
                type: Type::BOOLEAN,
                default: true,
            ),
            'show_company_phone' => new Property(
                type: Type::BOOLEAN,
                default: true,
            ),
            'show_company_website' => new Property(
                type: Type::BOOLEAN,
                default: true,
            ),
            'customer_number_title' => new Property(
                default: 'Account #',
            ),
            'show_customer_no' => new Property(
                type: Type::BOOLEAN,
                default: false,
            ),
            'invoice_number_title' => new Property(
                default: 'Invoice Number',
            ),
            'date_title' => new Property(
                default: 'Date',
            ),
            'due_date_title' => new Property(
                default: 'Due Date',
            ),
            'purchase_order_title' => new Property(
                default: 'Purchase Order',
            ),
            'quantity_header' => new Property(
                default: 'Quantity',
            ),
            'item_header' => new Property(
                default: 'Item',
            ),
            'unit_cost_header' => new Property(
                default: 'Rate',
            ),
            'total_title' => new Property(
                default: 'Total',
            ),
            'amount_header' => new Property(
                default: 'Amount',
            ),
            'subtotal_title' => new Property(
                default: 'Subtotal',
            ),
            'notes_title' => new Property(
                default: 'Notes',
            ),
            'terms_title' => new Property(
                default: 'Terms',
            ),
            'terms' => new Property(
                null: true,
            ),

            /* Invoices */

            'header' => new Property(
                default: 'INVOICE',
            ),
            'payment_terms_title' => new Property(
                default: 'Payment Terms',
            ),
            'amount_paid_title' => new Property(
                default: 'Amount Paid',
            ),
            'balance_title' => new Property(
                default: 'Balance Due',
            ),

            /* Estimates */

            'header_estimate' => new Property(
                default: 'ESTIMATE',
            ),
            'estimate_number_title' => new Property(
                default: 'Estimate Number',
            ),
            'estimate_footer' => new Property(
                null: true,
            ),

            /* Receipts */

            'header_receipt' => new Property(
                default: 'RECEIPT',
            ),
            'amount_title' => new Property(
                default: 'Amount',
            ),
            'payment_method_title' => new Property(
                default: 'Payment Method',
            ),
            'check_no_title' => new Property(
                default: 'Check #',
            ),
            'receipt_footer' => new Property(
                default: 'Thank you!',
            ),

            /* Appearance */

            'use_translations' => new Property(
                type: Type::BOOLEAN,
                default: true,
            ),
            'style' => new Property(
                validate: ['enum', 'choices' => ['classic', 'compact', 'minimal', 'modern', 'simple']],
                default: 'classic',
            ),
            'credit_note_template_id' => new Property(
                type: Type::INTEGER,
                null: true,
                relation: PdfTemplate::class,
            ),
            'estimate_template_id' => new Property(
                type: Type::INTEGER,
                null: true,
                relation: PdfTemplate::class,
            ),
            'invoice_template_id' => new Property(
                type: Type::INTEGER,
                null: true,
                relation: PdfTemplate::class,
            ),
            'receipt_template_id' => new Property(
                type: Type::INTEGER,
                null: true,
                relation: PdfTemplate::class,
            ),
            'statement_template_id' => new Property(
                type: Type::INTEGER,
                null: true,
                relation: PdfTemplate::class,
            ),
        ];
    }

    //
    // Validators
    //

    /**
     * Validates an ID.
     */
    public static function validateID(mixed $id): bool
    {
        if (!is_string($id)) {
            return false;
        }

        // Allowed characters: a-z, A-Z, 0-9, _, -
        // Min length: 2
        return preg_match('/^[a-z0-9_-]{2,}$/i', $id) > 0;
    }

    //
    // Relationships
    //

    /**
     * Gets the custom PDF template model for a given document type.
     */
    public function getCustomPdfTemplate(string $documentType): ?PdfTemplate
    {
        return $this->relation($documentType.'_template_id');
    }

    /**
     * Gets the PDF theme object for a given document type. This
     * can return a custom PDF theme or a standard one based on
     * the model configuration.
     */
    public function getPdfTheme(string $documentType): PdfTheme
    {
        if ($pdfTemplate = $this->getCustomPdfTemplate($documentType)) {
            if ($pdfTemplate->html) {
                return $pdfTemplate->toPdfTheme();
            }
        }

        return $this->getStandardPdfThemeFactory()
            ->build($this->style, $documentType);
    }

    /**
     * Creates the factory to generate the standard themes.
     */
    protected function getStandardPdfThemeFactory(): StandardPdfThemeFactory
    {
        if (!isset(self::$standardThemeFactory)) {
            self::$standardThemeFactory = new StandardPdfThemeFactory();
        }

        return self::$standardThemeFactory;
    }

    //
    // ThemeableInterface
    //

    public function theme(): Theme
    {
        return $this;
    }

    public function getThemeVariables(): PdfVariablesInterface
    {
        return new ThemePdfVariables($this);
    }

    public function getThemeCompany(): Company
    {
        return $this->tenant();
    }
}
