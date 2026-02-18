<?php

namespace App\CashApplication\Pdf;

use App\AccountsReceivable\Models\Customer;
use App\AccountsReceivable\Models\ReceivableDocument;
use App\CashApplication\Models\Payment;
use App\Core\Pdf\PdfMerger;
use App\Themes\Models\Theme;
use App\Themes\Pdf\AbstractCustomerPdf;
use App\Themes\ValueObjects\PdfTheme;
use mikehaertl\tmp\File;

/**
 * Responsible for rendering payment receipt PDFs.
 */
class PaymentPdf extends AbstractCustomerPdf
{
    private Theme $theme;
    private string $filename;
    const int TIMEOUT_NUM_OF_SECONDS = 50;

    public function __construct(private Payment $payment)
    {
        $this->theme = $payment->theme();

        parent::__construct($payment->customer()); /* @phpstan-ignore-line */
    }

    /**
     * Gets the associated payment.
     */
    public function getPayment(): Payment
    {
        return $this->payment;
    }

    //
    // PdfBuilderInterface
    //

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

        // add the attached invoices, estimates, and credit notes as separate pages
        $seen = [];
        $merger = new PdfMerger();
        $start = microtime(true); // we need to track the timeout time

        $timeoutSeconds = self::TIMEOUT_NUM_OF_SECONDS;
        $maxExecutionTimeSetByPhp = ini_get('max_execution_time');
        if ($maxExecutionTimeSetByPhp < self::TIMEOUT_NUM_OF_SECONDS)
            $timeoutSeconds = $maxExecutionTimeSetByPhp - 5; // to make sure that even if the php has shorter execution time than nginx, it will take the sorter period

        $breakdown = $this->payment->breakdown();
        foreach (['invoices', 'estimates', 'creditNotes'] as $type) {
            /** @var ReceivableDocument $document */
            foreach ($breakdown[$type] as $document) {
                $key = $type.$document->id();
                if (!isset($seen[$key]) && $builder = $document->getPdfBuilder()) {
                    $files[] = new File($builder->build($locale), 'pdf');
                    $seen[$key] = true;
                }

                // we need to make sure that time out does not happen, if it does return only the first document
                if (php_sapi_name() !== "cli" && microtime(true) - $start > $timeoutSeconds) {
                    $pdf = $merger->merge([$files[0]]);
                    return (string) file_get_contents($pdf);
                }
            }
        }

        // merge the documents into a single PDF
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
        $company = $this->payment->tenant();
        $company->useTimezone();
        /** @var Customer $customer */
        $customer = $this->payment->customer();

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

        // payment
        $paymentVariables = $this->payment->getThemeVariables();
        $params['payment'] = $paymentVariables->generate($this->theme, $opts);

        // transaction (for BC purposes with transactions)
        $params['transaction'] = $params['payment'];

        return $params;
    }

    public function getDocumentCurrency(): string
    {
        return $this->payment->currency;
    }
}
