<?php

namespace App\Themes\Models;

use App\Core\Multitenant\Models\MultitenantModel;
use App\Themes\ValueObjects\PdfTheme;
use App\Core\Orm\Property;
use App\Core\Orm\Traits\AutoTimestamps;
use App\Core\Orm\Type;

/**
 * @property int         $id
 * @property string      $name
 * @property string      $document_type
 * @property string      $html
 * @property string      $css
 * @property string|null $header_html
 * @property string|null $footer_html
 * @property string|null $header_css
 * @property string|null $footer_css
 * @property string      $margin_top
 * @property string      $margin_bottom
 * @property string      $margin_left
 * @property string      $margin_right
 * @property bool        $disable_smart_shrinking
 * @property string      $template_engine
 */
class PdfTemplate extends MultitenantModel
{
    use AutoTimestamps;

    protected static function getProperties(): array
    {
        return [
            'name' => new Property(
                required: true,
            ),
            'document_type' => new Property(
                required: true,
                validate: ['enum', 'choices' => ['invoice', 'estimate', 'receipt', 'statement', 'credit_note']],
            ),
            'html' => new Property(
                required: true,
            ),
            'css' => new Property(
                required: true,
            ),
            'header_html' => new Property(),
            'header_css' => new Property(),
            'footer_html' => new Property(),
            'footer_css' => new Property(),
            'margin_top' => new Property(
                default: '0.5cm',
            ),
            'margin_bottom' => new Property(
                default: '0.5cm',
            ),
            'margin_left' => new Property(
                default: '0.5cm',
            ),
            'margin_right' => new Property(
                default: '0.5cm',
            ),
            'disable_smart_shrinking' => new Property(
                type: Type::BOOLEAN,
            ),
            'template_engine' => new Property(
                validate: ['enum', 'choices' => ['mustache', 'twig']],
                default: PdfTheme::TEMPLATE_ENGINE_TIWG,
            ),
        ];
    }

    public function toPdfTheme(): PdfTheme
    {
        $pdfOptions = [
            'margin-top' => $this->margin_top,
            'margin-left' => $this->margin_left,
            'margin-right' => $this->margin_right,
            'margin-bottom' => $this->margin_bottom,
        ];

        if ($this->disable_smart_shrinking) {
            $pdfOptions[] = 'disable-smart-shrinking';
        }

        return new PdfTheme($this->template_engine, $this->html, $this->css, $this->header_html, $this->header_css, $this->footer_html, $this->footer_css, $pdfOptions);
    }
}
