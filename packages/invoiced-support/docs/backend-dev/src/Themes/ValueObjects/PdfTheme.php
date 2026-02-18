<?php

namespace App\Themes\ValueObjects;

/**
 * This class represents an un-rendered PDF document. It contains
 * the templates and associated configuration needed to build a PDF
 * document with wkhtmltopdf. What is not covered by this class are
 * the actual variables injected into the templates and the actual
 * rendering. This is covered elsewhere.
 */
class PdfTheme
{
    const TEMPLATE_ENGINE_MUSTACHE = 'mustache';
    const TEMPLATE_ENGINE_TIWG = 'twig';

    private string $templateEngine;

    public function __construct(string $templateEngine, private string $bodyHtml, private string $bodyCss, private ?string $headerHtml = null, private ?string $headerCss = null, private ?string $footerHtml = null, private ?string $footerCss = null, private array $pdfOptions = [])
    {
        if (self::TEMPLATE_ENGINE_TIWG != $templateEngine && self::TEMPLATE_ENGINE_MUSTACHE != $templateEngine) {
            throw new \InvalidArgumentException('Unsupported template engine: '.$templateEngine);
        }

        $this->templateEngine = $templateEngine;
    }

    /**
     * Gets the template engine used to render the document.
     *
     * @return string `mustache` or `twig`
     */
    public function getTemplateEngine(): string
    {
        return $this->templateEngine;
    }

    /**
     * Indicates whether template variables should be
     * formatted for HTML. The legacy Mustache template
     * engine needs variables to be already converted to
     * HTML because it does not have filters like Twig.
     */
    public function shouldHtmlifyVariables(): bool
    {
        return 'mustache' == $this->getTemplateEngine();
    }

    /**
     * Gets the HTML template used to generate PDFs.
     */
    public function getBodyHtml(): string
    {
        return $this->bodyHtml;
    }

    /**
     * Gets the CSS stylesheet used to generate PDFs.
     */
    public function getBodyCss(): string
    {
        return $this->bodyCss;
    }

    /**
     * Generates an HTML document from this object to be used
     * as the header. Returns `null` when there is no header.
     */
    public function getHeaderHtml(): ?string
    {
        return $this->headerHtml;
    }

    /**
     * Gets the CSS stylesheet used to generate the header of the PDF.
     */
    public function getHeaderCss(): ?string
    {
        return $this->headerCss;
    }

    /**
     * Generates an HTML document from this object to be used
     * as the footer. Returns `null` when there is no footer.
     */
    public function getFooterHtml(): ?string
    {
        return $this->footerHtml;
    }

    /**
     * Gets the CSS stylesheet used to generate the footer of the PDF.
     */
    public function getFooterCss(): ?string
    {
        return $this->footerCss;
    }

    /**
     * Gets the overridden options when generating PDFs through wkhtmltopdf.
     */
    public function getPdfOptions(): array
    {
        return $this->pdfOptions;
    }
}
