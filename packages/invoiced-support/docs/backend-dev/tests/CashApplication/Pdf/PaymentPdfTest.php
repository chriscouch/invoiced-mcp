<?php

namespace App\Tests\CashApplication\Pdf;

use App\CashApplication\Models\Payment;
use App\CashApplication\Pdf\PaymentPdf;
use App\Tests\Themes\AbstractCustomerPdfTest;
use App\Themes\Pdf\AbstractCustomerPdf;

class PaymentPdfTest extends AbstractCustomerPdfTest
{
    protected function getObject(): Payment
    {
        $payment = new Payment();
        $payment->tenant_id = (int) self::$company->id();
        $payment->setCustomer(self::$customer);
        $payment->date = time();
        $payment->currency = 'usd';

        return $payment;
    }

    protected function getPdfBuilder(): AbstractCustomerPdf
    {
        return new PaymentPdf($this->getObject());
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
            'payment' => $object->getThemeVariables()->generate($theme),
            // kept for BC reasons
            'transaction' => $object->getThemeVariables()->generate($theme),
        ];
    }
}
