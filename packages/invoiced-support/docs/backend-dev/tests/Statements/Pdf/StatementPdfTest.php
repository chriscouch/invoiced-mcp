<?php

namespace App\Tests\Statements\Pdf;

use App\Statements\Libs\BalanceForwardStatement;
use App\Statements\Pdf\StatementPdf;
use App\Tests\Themes\AbstractCustomerPdfTest;
use App\Themes\Pdf\AbstractCustomerPdf;

class StatementPdfTest extends AbstractCustomerPdfTest
{
    protected function getObject(): BalanceForwardStatement
    {
        return self::getService('test.statement_builder')->balanceForward(self::$customer);
    }

    protected function getPdfBuilder(): AbstractCustomerPdf
    {
        return new StatementPdf($this->getObject());
    }

    protected function getExpectedFilename(): string
    {
        return 'Sherlock Statement.pdf';
    }

    protected function getExpectedBodyHtmlTemplate(): string
    {
        return (string) file_get_contents(self::$kernel->getProjectDir().'/templates/pdf/classic/statement.twig');
    }

    protected function getExpectedBodyCss(): string
    {
        return (string) file_get_contents(self::$kernel->getProjectDir().'/templates/pdf/classic/statement.css');
    }

    protected function getExpectedHtmlParameters(): array
    {
        $theme = self::$company->theme();
        $object = $this->getObject();

        return [
            'theme' => $theme->getThemeVariables()->generate($theme),
            'company' => self::$company->getThemeVariables()->generate($theme, ['showCountry' => false]),
            'customer' => self::$customer->getThemeVariables()->generate($theme),
            'statement' => $object->getThemeVariables()->generate($theme),
        ];
    }

    public function testGetFilenameEndDate(): void
    {
        $statement = self::getService('test.statement_builder')->balanceForward(self::$customer, null, strtotime('-1 month'), time());
        $pdf = new StatementPdf($statement);
        $this->assertEquals('Sherlock Statement.pdf', $pdf->getFilename('en_US'));
    }
}
