<?php

namespace App\Themes\ValueObjects;

use App\Themes\Interfaces\PdfVariablesInterface;
use App\Themes\Models\Theme;

/**
 * View model for using theme models in PDF templates.
 */
class ThemePdfVariables implements PdfVariablesInterface
{
    private static array $visibleProperties = [
        'use_translations',
        'from_title',
        'to_title',
        'ship_to_title',
        'customer_number_title',
        'show_customer_no',
        'invoice_number_title',
        'date_title',
        'due_date_title',
        'date_format',
        'purchase_order_title',
        'quantity_header',
        'item_header',
        'unit_cost_header',
        'total_title',
        'amount_header',
        'subtotal_title',
        'notes_title',
        'terms_title',
        'terms',
        'header',
        'payment_terms_title',
        'amount_paid_title',
        'balance_title',
        'header_estimate',
        'estimate_number_title',
        'estimate_footer',
        'header_receipt',
        'amount_title',
        'payment_method_title',
        'check_no_title',
        'receipt_footer',
    ];

    public function __construct(protected Theme $theme)
    {
    }

    public function generate(Theme $theme, array $opts = []): array
    {
        $variables = [];
        foreach (self::$visibleProperties as $k) {
            $variables[$k] = $this->theme->$k;
        }

        return $variables;
    }
}
