<?php

namespace App\Themes\Libs;

use App\Themes\ValueObjects\PdfTheme;
use InvalidArgumentException;

/**
 * This class generates the built-in themes that are stored
 * in the filesystem. Themes are stored here:.
 *
 *   templates/pdf/theme_id/document_type.extension
 *
 * Which translates to: (example)
 *
 *   templates/pdf/classic/invoice.pdf
 *   templates/pdf/classic/invoice.css
 *
 * There is also support for optional header and footer fragments.
 * The locations are:
 *
 *   templates/pdf/theme_id/document_type_header.extension
 *   templates/pdf/theme_id/document_type_footer.extension
 */
class StandardPdfThemeFactory
{
    private static array $themes = [
        'classic',
        'compact',
        'minimal',
        'modern',
        'simple',
    ];

    private static array $documentTypes = [
        'invoice',
        'credit_note',
        'estimate',
        'statement',
        'receipt',
    ];

    private string $templatesDir;

    public function __construct()
    {
        $this->templatesDir = dirname(__DIR__, 3).'/templates';
    }

    public function build(string $name, string $documentType): PdfTheme
    {
        if (!in_array($name, self::$themes)) {
            throw new InvalidArgumentException('Theme does not exist: '.$name);
        }

        if (!in_array($documentType, self::$documentTypes)) {
            throw new InvalidArgumentException('Unsupported document type: '.$documentType);
        }

        $bodyHtml = $this->getContents($name, $documentType, '.twig');
        $bodyCss = $this->getContents($name, $documentType, '.css');
        $headerHtml = $this->getOptionalContents($name, $documentType.'_header', '.twig');
        $headerCss = $this->getOptionalContents($name, $documentType.'_header', '.css');
        $footerHtml = $this->getOptionalContents($name, $documentType.'_footer', '.twig');
        $footerCss = $this->getOptionalContents($name, $documentType.'_footer', '.css');
        $pdfOptions = $this->getPdfOptions($name, $documentType);

        return new PdfTheme(PdfTheme::TEMPLATE_ENGINE_TIWG, $bodyHtml, $bodyCss, $headerHtml, $headerCss, $footerHtml, $footerCss, $pdfOptions);
    }

    /**
     * Gets the contents of a PDF template.
     */
    private function getContents(string $name, string $documentType, string $extension): string
    {
        return (string) file_get_contents($this->templatesDir.'/pdf/'.$name.'/'.$documentType.$extension);
    }

    /**
     * Gets the contents of a PDF template, if available.
     */
    private function getOptionalContents(string $name, string $documentType, string $extension): ?string
    {
        $filename = $this->templatesDir.'/pdf/'.$name.'/'.$documentType.$extension;
        if (!file_exists($filename)) {
            return null;
        }

        return (string) file_get_contents($filename);
    }

    /**
     * Gets the PDF overrides for a given theme and document type.
     */
    private function getPdfOptions(string $name, string $documentType): array
    {
        if ('minimal' === $name && in_array($documentType, ['credit_note', 'estimate', 'invoice'])) {
            $options = [
                'margin-top' => '0.75cm',
                'margin-left' => '0.5cm',
                'margin-right' => '0.5cm',
                'margin-bottom' => '4cm',
            ];
        } else {
            $options = [
                'margin-top' => '0.75cm',
                'margin-left' => '0.5cm',
                'margin-right' => '0.5cm',
                'margin-bottom' => '1.25cm',
            ];
        }

        if (getenv('NEW_WKHTMLTOPDF')) {
            $options[] = 'disable-smart-shrinking';
        }

        return $options;
    }
}
