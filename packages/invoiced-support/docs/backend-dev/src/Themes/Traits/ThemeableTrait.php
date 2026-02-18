<?php

namespace App\Themes\Traits;

use App\Companies\Models\Company;
use App\Themes\Models\Theme;

trait ThemeableTrait
{
    private Theme $_theme;

    /**
     * Gets the PHP date format for this object.
     */
    public function dateFormat(): string
    {
        return str_replace('y', 'Y', $this->getThemeCompany()->date_format);
    }

    /**
     * Gets the theme for this object.
     */
    public function theme(): Theme
    {
        if (!isset($this->_theme)) {
            $this->_theme = $this->getThemeCompany()
                ->defaultTheme();
        }

        return $this->_theme;
    }

    /**
     * Gets the company model.
     */
    public function getThemeCompany(): Company
    {
        return $this->tenant();
    }
}
