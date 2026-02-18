<?php

namespace App\AccountsReceivable\Pdf;

use App\AccountsReceivable\Models\ReceivableDocument;
use App\Core\Files\Models\Attachment;
use App\Core\Utils\Enums\ObjectType;
use App\Themes\Models\Theme;
use App\Themes\Pdf\AbstractCustomerPdf;
use App\Themes\ValueObjects\PdfTheme;

abstract class DocumentPdf extends AbstractCustomerPdf
{
    private Theme $theme;
    private string $filename;

    public function __construct(protected ReceivableDocument $document)
    {
        $this->theme = $document->theme();

        parent::__construct($document->customer());
    }

    /**
     * Gets the document.
     */
    public function getDocument(): ReceivableDocument
    {
        return $this->document;
    }

    //
    // PdfBuilderInterface
    //

    public function getFilename(string $locale): string
    {
        if (!isset($this->filename)) {
            $key = 'filenames.'.$this->document->object;

            $this->filename = $this->getTranslator()->trans($key, ['%number%' => $this->document->number], 'pdf', $locale).'.pdf';
        }

        return $this->filename;
    }

    public function build(string $locale): string
    {
        // check if there is a stored version of the PDF via attachments
        $pdfAttachment = Attachment::where('parent_type', $this->document->object)
            ->where('parent_id', $this->document)
            ->where('location', Attachment::LOCATION_PDF)
            ->oneOrNull();

        if ($pdfAttachment instanceof Attachment) {
            return $pdfAttachment->file()->getContent();
        }

        // otherwise build the document on the fly
        return parent::build($locale);
    }

    //
    // AbstractCustomerPdf
    //

    protected function generatePdfTheme(): PdfTheme
    {
        return $this->theme->getPdfTheme($this->document->object);
    }

    protected function generateHtmlParameters(): array
    {
        $params = [];

        // use the company's timezone for php date/time functions
        $company = $this->document->tenant();
        $company->useTimezone();
        $customer = $this->document->customer();

        $htmlify = $this->getPdfTheme()->shouldHtmlifyVariables();
        $opts = ['htmlify' => $htmlify];

        // theme
        $themeVariables = $this->theme->getThemeVariables();
        $params['theme'] = $themeVariables->generate($this->theme, $opts);

        // company
        $companyVariables = $company->getThemeVariables();
        $showCountry = $customer->country && $customer->country != $company->country;
        $companyOpts = ['showCountry' => $showCountry, 'htmlify' => $htmlify];
        $params['company'] = $companyVariables->generate($this->theme, $companyOpts);

        // customer
        $customerVariables = $customer->getThemeVariables();
        $params['customer'] = $customerVariables->generate($this->theme, $opts);

        // object
        $obj = ObjectType::fromModel($this->document)->typeName();
        $documentVariables = $this->document->getThemeVariables();
        $params[$obj] = $documentVariables->generate($this->theme, $opts);

        return $params;
    }

    public function getDocumentCurrency(): string
    {
        return $this->document->currency;
    }
}
