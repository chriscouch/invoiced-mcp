<?php

namespace App\Companies\Pdf;

use App\Companies\Models\Company;
use App\Core\I18n\PhoneFormatter;
use App\Themes\Interfaces\PdfVariablesInterface;
use App\Themes\Models\Theme;
use libphonenumber\PhoneNumberFormat;

/**
 * View model for using company models in PDF templates.
 */
class CompanyPdfVariables implements PdfVariablesInterface
{
    private const VISIBLE_PROPERTIES = [
        'country',
        'currency',
        'highlight_color',
        'language',
        'logo',
        'tax_id',
        'username',
        'url',
    ];

    public function __construct(protected Company $company)
    {
    }

    public function generate(Theme $theme, array $opts = []): array
    {
        $htmlify = $opts['htmlify'] ?? true;

        $variables = ['name' => $this->company->getDisplayName()];
        foreach (self::VISIBLE_PROPERTIES as $k) {
            $variables[$k] = $this->company->$k;
        }

        // Address
        $variables['address'] = $this->company->address($opts['showCountry'], false);
        if ($htmlify) {
            $variables['address'] = nl2br(htmlentities($variables['address'], ENT_QUOTES));
        }

        // Email
        $variables['email'] = null;
        if ($theme->show_company_email) {
            $variables['email'] = $this->company->email;
        }

        // Phone
        $variables['phone'] = null;
        if ($theme->show_company_phone) {
            $variables['phone'] = PhoneFormatter::format(
                (string) $this->company->phone,
                $this->company->country,
                $opts['showCountry'] ? PhoneNumberFormat::INTERNATIONAL : PhoneNumberFormat::NATIONAL
            );
        }

        // Website
        $variables['website'] = null;
        if ($theme->show_company_website) {
            $variables['website'] = str_replace(['http://', 'https://'], ['', ''], (string) $this->company->website);
        }

        return $variables;
    }
}
