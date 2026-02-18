<?php

namespace App\Themes\Interfaces;

use App\Companies\Models\Company;
use App\Themes\Models\Theme;

interface ThemeableInterface
{
    /**
     * Gets the theme for this object.
     */
    public function theme(): Theme;

    /**
     * Gets the view model used for rendering the HTML theme.
     */
    public function getThemeVariables(): PdfVariablesInterface;

    /**
     * Gets the company model.
     */
    public function getThemeCompany(): Company;
}
