<?php

namespace App\Statements\Pdf;

use App\Statements\Libs\AbstractStatement;
use App\Themes\Pdf\AbstractCustomerPdf;
use App\Themes\ValueObjects\PdfTheme;
use App\Themes\Models\Theme;

/**
 * Responsible for rendering account statement PDFs.
 */
class StatementPdf extends AbstractCustomerPdf
{
    private Theme $theme;
    private string $filename;

    public function __construct(private AbstractStatement $statement)
    {
        $this->theme = $this->statement->theme();

        parent::__construct($this->statement->customer);
    }

    /**
     * Gets the associated statement.
     */
    public function getStatement(): AbstractStatement
    {
        return $this->statement;
    }

    //
    // PdfBuilderInterface
    //

    public function getFilename(string $locale): string
    {
        if (!isset($this->filename)) {
            $customer = $this->statement->getSendCustomer();
            $this->filename = $this->getTranslator()->trans('filenames.statement', ['%customerName%' => $customer->name], 'pdf', $locale).'.pdf';
        }

        return $this->filename;
    }

    //
    // AbstractCustomerPdf
    //

    protected function generatePdfTheme(): PdfTheme
    {
        return $this->theme->getPdfTheme('statement');
    }

    protected function generateHtmlParameters(): array
    {
        $params = [];

        // use the company's timezone for php date/time functions
        $company = $this->getCompany();
        $company->useTimezone();
        $customer = $this->statement->getSendCustomer();

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

        // statement
        $statementVariables = $this->statement->getThemeVariables();
        $params['statement'] = $statementVariables->generate($this->theme, $opts);

        return $params;
    }

    public function getDocumentCurrency(): string
    {
        return $this->statement->currency;
    }
}
