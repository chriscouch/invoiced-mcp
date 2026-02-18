<?php

namespace App\AccountsReceivable\Pdf;

use App\AccountsReceivable\Models\CreditNote;
use App\Themes\Models\Theme;

/**
 * View model for credit note PDF templates.
 */
class CreditNotePdfVariables extends DocumentPdfVariables
{
    public function __construct(CreditNote $creditNote)
    {
        parent::__construct($creditNote);
    }

    public function generate(Theme $theme, array $opts = []): array
    {
        $variables = parent::generate($theme, $opts);

        // this is kept around for BC with mustache templates
        $htmlify = $opts['htmlify'] ?? true;
        if ($htmlify) {
            $variables['customFields'] = $variables['custom_fields'];
        }

        return $variables;
    }
}
