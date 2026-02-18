<?php

namespace App\Themes\Pdf;

use App\AccountsReceivable\Models\Customer;
use App\Companies\Models\Company;
use App\Core\I18n\TranslatorFacade;
use App\Core\Pdf\Exception\PdfException;
use App\Core\Pdf\Pdf;
use App\Core\Statsd\StatsdFacade;
use App\Themes\Interfaces\PdfBuilderInterface;
use App\Themes\Libs\HtmlDocumentGenerator;
use App\Themes\ValueObjects\PdfTheme;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Any customer-owned object that generates a PDF document
 * can extend this class to manage the PDF generation process.
 */
abstract class AbstractCustomerPdf implements PdfBuilderInterface
{
    private PdfTheme $pdfTheme;
    private HtmlDocumentGenerator $htmlDocumentGenerator;
    private array $htmlParameters;
    private TranslatorInterface $translator;

    public function __construct(private Customer $customer)
    {
    }

    /**
     * Generates the PDF parameters used to render the document.
     */
    abstract protected function generatePdfTheme(): PdfTheme;

    /**
     * Gets the default currency used for this document.
     */
    abstract public function getDocumentCurrency(): string;

    /**
     * Generates the HTML parameters to inject into the HTML template.
     */
    abstract protected function generateHtmlParameters(): array;

    /**
     * Gets the company.
     */
    public function getCompany(): Company
    {
        return $this->customer->tenant();
    }

    /**
     * Gets the money format used for this document.
     */
    public function getMoneyFormat(): array
    {
        return $this->customer->moneyFormat();
    }

    /**
     * Gets the HTML parameters to inject into the HTML template.
     */
    public function getHtmlParameters(): array
    {
        if (!isset($this->htmlParameters)) {
            $this->htmlParameters = $this->generateHtmlParameters();
        }

        return $this->htmlParameters;
    }

    public function toHtml(string $locale): string
    {
        // grab the right HTML template
        $pdfTheme = $this->getPdfTheme();
        $template = $pdfTheme->getBodyHtml();

        // convert it into a fully-formed HTML document
        return $this->getHtmlDocumentGenerator()
            ->build($template, $pdfTheme->getBodyCss(), true, $locale);
    }

    /**
     * Generates the HTML for the header section of the PDF.
     *
     * @throws PdfException when the HTML cannot be generated
     */
    private function generateHeaderHtml(string $locale): ?string
    {
        // grab the right HTML template
        $pdfTheme = $this->getPdfTheme();
        $template = $pdfTheme->getHeaderHtml();
        if (!$template) {
            return null;
        }

        // convert it into a fully-formed HTML document
        return $this->getHtmlDocumentGenerator()
            ->build($template, $pdfTheme->getHeaderCss(), false, $locale);
    }

    /**
     * Generates the HTML for the footer section of the PDF.
     *
     * @throws PdfException when the HTML cannot be generated
     */
    private function generateFooterHtml(string $locale): ?string
    {
        // grab the right HTML template
        $pdfTheme = $this->getPdfTheme();
        $template = $pdfTheme->getFooterHtml();
        if (!$template) {
            return null;
        }

        // convert it into a fully-formed HTML document
        return $this->getHtmlDocumentGenerator()
            ->build($template, $pdfTheme->getFooterCss(), false, $locale);
    }

    /**
     * Sets the PDF theme for the object.
     * (useful for testing).
     */
    public function setPdfTheme(PdfTheme $pdfTheme): void
    {
        $this->pdfTheme = $pdfTheme;
    }

    public function getPdfTheme(): PdfTheme
    {
        if (!isset($this->pdfTheme)) {
            $this->pdfTheme = $this->generatePdfTheme();
        }

        return $this->pdfTheme;
    }

    public function build(string $locale): string
    {
        $this->setLocale($locale);
        $html = $this->toHtml($locale);
        $statsd = StatsdFacade::get();
        if (0 === strlen($html)) {
            $statsd->increment('pdf.no_html');

            throw new PdfException('Failed to build HTML');
        }

        $pdfTheme = $this->getPdfTheme();
        $pdfOptions = $pdfTheme->getPdfOptions();

        if ($headerHtml = $this->generateHeaderHtml($locale)) {
            $pdfOptions['header-html'] = $headerHtml;
        }

        if ($footerHtml = $this->generateFooterHtml($locale)) {
            $pdfOptions['footer-html'] = $footerHtml;
        }

        $pdf = Pdf::make();
        $pdf->addPage($html);
        $pdf->setOptions($pdfOptions);
        $content = (string) $pdf->toString();

        if (!$content) {
            $statsd->increment('pdf.failed');

            throw new PdfException('PDF failed to generate: '.$pdf->getError());
        }

        $statsd->increment('pdf.generated');

        return $content;
    }

    /**
     * Gets the translator used to render the document.
     */
    public function getTranslator(): TranslatorInterface
    {
        if (!isset($this->translator)) {
            $this->translator = TranslatorFacade::get();
            $this->translator->setLocale($this->customer->getLocale());
        }

        return $this->translator;
    }

    /**
     * Sets the locale on the translator to the customer's locale.
     */
    public function setLocale(string $locale): void
    {
        $this->getTranslator()->setLocale($locale);
    }

    /**
     * Creates the class to generate HTML documents from template fragments.
     */
    private function getHtmlDocumentGenerator(): HtmlDocumentGenerator
    {
        if (!isset($this->htmlDocumentGenerator)) {
            $this->htmlDocumentGenerator = new HtmlDocumentGenerator($this);
        }

        return $this->htmlDocumentGenerator;
    }
}
