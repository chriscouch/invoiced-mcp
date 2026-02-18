<?php

namespace App\Themes\Interfaces;

use App\Themes\Models\Theme;

/**
 * This interface provides a contract for generating the variables
 * injected into PDF templates, given a model like an Invoice.
 * Classes implementing this interface will only work with a
 * specific type of model.
 */
interface PdfVariablesInterface
{
    /**
     * Generates the variables to be injected into the
     * PDF template for the model this class represents.
     */
    public function generate(Theme $theme, array $opts = []): array;
}
