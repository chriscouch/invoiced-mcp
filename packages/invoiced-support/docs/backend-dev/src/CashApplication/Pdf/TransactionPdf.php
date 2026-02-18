<?php

namespace App\CashApplication\Pdf;

use App\Core\Pdf\PdfMerger;
use App\CashApplication\Models\Transaction;
use App\Themes\Pdf\AbstractCustomerPdf;
use App\Themes\ValueObjects\PdfTheme;
use App\Themes\Models\Theme;
use mikehaertl\tmp\File;

/**
 * Responsible for rendering payment receipt PDFs.
 */
class TransactionPdf extends AbstractCustomerPdf
{
    private Theme $theme;
    private string $filename;

    public function __construct(private Transaction $transaction)
    {
        $this->theme = $transaction->theme();

        parent::__construct($transaction->customer());
    }

    /**
     * Gets the associated transaction.
     */
    public function getTransaction(): Transaction
    {
        return $this->transaction;
    }

    //
    // PdfBuilderInterface
    //

    /**
     * Derives the filename for the PDF produced by this invoice.
     */
    public function getFilename(string $locale): string
    {
        if (!isset($this->filename)) {
            $this->filename = $this->getTranslator()->trans('filenames.receipt', [], 'pdf', $locale).'.pdf';
        }

        return $this->filename;
    }

    public function build(string $locale): string
    {
        // first page is the receipt
        $receipt = parent::build($locale);
        $files = [
            new File($receipt, 'pdf'),
        ];

        // add the attached invoices as separate pages
        $breakdown = $this->transaction->breakdown();
        foreach ($breakdown['invoices'] as $invoice) {
            $builder = $invoice->getPdfBuilder();
            $files[] = new File($builder->build($locale), 'pdf');
        }

        // merge the documents into a single PDF
        $merger = new PdfMerger();
        $pdf = $merger->merge($files);

        return (string) file_get_contents($pdf);
    }

    //
    // AbstractCustomerPdf
    //

    protected function generatePdfTheme(): PdfTheme
    {
        return $this->theme->getPdfTheme('receipt');
    }

    protected function generateHtmlParameters(): array
    {
        $params = [];

        // use the company's timezone for php date/time functions
        $company = $this->transaction->tenant();
        $company->useTimezone();
        $customer = $this->transaction->customer();

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

        // transaction
        $transactionVariables = $this->transaction->getThemeVariables();
        $params['transaction'] = $transactionVariables->generate($this->theme, $opts);

        return $params;
    }

    public function getDocumentCurrency(): string
    {
        return $this->transaction->currency;
    }
}
