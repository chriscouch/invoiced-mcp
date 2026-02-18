<?php

namespace App\Tests\CashApplication\Pdf;

use App\CashApplication\Models\Transaction;
use App\CashApplication\Pdf\TransactionPdf;
use App\Tests\Themes\AbstractCustomerPdfTest;
use App\Themes\Pdf\AbstractCustomerPdf;

class TransactionPdfTest extends AbstractCustomerPdfTest
{
    protected function getObject(): Transaction
    {
        $transaction = new Transaction();
        $transaction->tenant_id = (int) self::$company->id();
        $transaction->setCustomer(self::$customer);
        $transaction->date = time();
        $transaction->currency = 'usd';

        return $transaction;
    }

    protected function getPdfBuilder(): AbstractCustomerPdf
    {
        return new TransactionPdf($this->getObject());
    }

    protected function getExpectedFilename(): string
    {
        return 'Receipt.pdf';
    }

    protected function getExpectedBodyHtmlTemplate(): string
    {
        return (string) file_get_contents(self::$kernel->getProjectDir().'/templates/pdf/classic/receipt.twig');
    }

    protected function getExpectedBodyCss(): string
    {
        return (string) file_get_contents(self::$kernel->getProjectDir().'/templates/pdf/classic/receipt.css');
    }

    protected function getExpectedHtmlParameters(): array
    {
        $theme = self::$company->theme();
        $object = $this->getObject();

        return [
            'theme' => $theme->getThemeVariables()->generate($theme),
            'company' => self::$company->getThemeVariables()->generate($theme, ['showCountry' => false]),
            'customer' => self::$customer->getThemeVariables()->generate($theme),
            'transaction' => $object->getThemeVariables()->generate($theme),
        ];
    }
}
