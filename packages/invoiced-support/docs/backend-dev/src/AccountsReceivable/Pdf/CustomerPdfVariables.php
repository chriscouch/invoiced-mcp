<?php

namespace App\AccountsReceivable\Pdf;

use App\AccountsReceivable\Models\Customer;
use App\Themes\Interfaces\PdfVariablesInterface;
use App\Themes\Models\Theme;

/**
 * View model for using customer models in PDF templates.
 */
class CustomerPdfVariables implements PdfVariablesInterface
{
    private static array $visibleProperties = [
        'attention_to',
        'autopay',
        'chase',
        'country',
        'email',
        'language',
        'name',
        'number',
        'payment_terms',
        'phone',
        'tax_id',
        'taxable',
        'avalara_exemption_number',
        'type',
        'metadata',
    ];

    public function __construct(protected Customer $customer)
    {
    }

    public function generate(Theme $theme, array $opts = []): array
    {
        $htmlify = $opts['htmlify'] ?? true;

        $billToCustomer = $this->customer->getBillToCustomer();

        $variables = [];
        foreach (self::$visibleProperties as $k) {
            $variables[$k] = $billToCustomer->$k;
        }

        $variables['address'] = $billToCustomer->address;
        if ($htmlify) {
            $variables['address'] = nl2br(htmlentities($variables['address'], ENT_QUOTES));
        }

        // metadata
        $variables['metadata'] = (array) $variables['metadata'];

        return $variables;
    }
}
