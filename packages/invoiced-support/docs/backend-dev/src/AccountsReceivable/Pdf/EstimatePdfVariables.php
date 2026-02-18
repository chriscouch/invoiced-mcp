<?php

namespace App\AccountsReceivable\Pdf;

use App\AccountsReceivable\Models\Estimate;
use App\Themes\Models\Theme;

/**
 * View model for estimate PDF templates.
 */
class EstimatePdfVariables extends DocumentPdfVariables
{
    public function __construct(Estimate $estimate)
    {
        parent::__construct($estimate);
    }

    public function generate(Theme $theme, array $opts = []): array
    {
        $variables = parent::generate($theme, $opts);

        // expiration date
        $dateFormat = $this->document->dateFormat();
        $variables['expiration_date'] = ($variables['expiration_date'] > 0) ? date($dateFormat, $variables['expiration_date']) : null;

        // footer
        $variables['terms'] = $theme->estimate_footer;

        // this is kept around for BC with mustache templates
        $htmlify = $opts['htmlify'] ?? true;
        if ($htmlify) {
            $variables['terms'] = nl2br(htmlentities((string) $variables['terms'], ENT_QUOTES));

            $variables['customFields'] = $variables['custom_fields'];
        }

        return $variables;
    }
}
