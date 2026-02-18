<?php

namespace App\Themes\Libs;

use App\Core\Pdf\Exception\PdfException;
use App\Core\Templating\Exception\MustacheException;
use App\Core\Templating\Exception\RenderException;
use App\Core\Templating\MustacheRenderer;
use App\Core\Templating\TwigContext;
use App\Core\Templating\TwigFacade;
use App\Core\Templating\TwigRenderer;
use App\Themes\Pdf\AbstractCustomerPdf;
use App\Themes\ValueObjects\PdfTheme;

/**
 * This class handles the conversion of an object PDF
 * into a fully formed HTML document to be passed to
 * wkhtmltopdf.
 */
class HtmlDocumentGenerator
{
    public function __construct(private AbstractCustomerPdf $customerPdf)
    {
    }

    /**
     * Generates an HTML document given a template, parameters, and CSS.
     *
     * @throws PdfException when the template cannot be rendered
     */
    public function build(string $template, ?string $css, bool $isBody, string $locale): string
    {
        $this->customerPdf->setLocale($locale);

        // determine what engine is used to render this template
        $engine = $this->customerPdf->getPdfTheme()->getTemplateEngine();
        if (PdfTheme::TEMPLATE_ENGINE_MUSTACHE == $engine) {
            $html = $this->renderMustache($template, $this->customerPdf->getHtmlParameters());
        } else {
            $moneyFormat = $this->customerPdf->getMoneyFormat();
            $moneyFormat['locale'] = $locale;
            $twigContext = new TwigContext(
                $this->customerPdf->getCompany(),
                $this->customerPdf->getDocumentCurrency(),
                $moneyFormat,
                $this->customerPdf->getTranslator()
            );
            $html = $this->renderTwig($template, $this->customerPdf->getHtmlParameters(), $twigContext);
        }

        return $this->finalize($html, $css, $isBody, $locale);
    }

    /**
     * Renders a template using Mustache.
     *
     * @param string $template Mustache template
     *
     * @throws PdfException when the template cannot be rendered
     */
    private function renderMustache(string $template, array $parameters): string
    {
        try {
            return MustacheRenderer::get()->render($template, $parameters);
        } catch (MustacheException $e) {
            throw new PdfException('Could not render document due to a parsing error: '.$e->getMessage(), 0, $e);
        }
    }

    /**
     * Renders a template using Twig.
     *
     * @param string $template Twig template
     *
     * @throws PdfException when the template cannot be rendered
     */
    private function renderTwig(string $template, array $parameters, TwigContext $context): string
    {
        try {
            return TwigRenderer::get()->render($template, $parameters, $context);
        } catch (RenderException $e) {
            throw new PdfException('Could not render document due to a parsing error: '.$e->getMessage(), 0, $e);
        }
    }

    /**
     * Converts an HTML and CSS snippet into a fully formed HTML document.
     */
    private function finalize(string $html, ?string $css, bool $isBody, string $locale): string
    {
        $company = $this->customerPdf->getCompany();

        return TwigFacade::get()->render('pdf/parent.twig', [
            'title' => $this->customerPdf->getFilename($locale),
            'css' => $css,
            'html' => $html,
            'testMode' => $isBody && $company->test_mode,
            'highlightColor' => $company->highlight_color,
        ]);
    }
}
