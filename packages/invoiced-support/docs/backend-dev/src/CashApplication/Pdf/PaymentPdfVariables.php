<?php

namespace App\CashApplication\Pdf;

use App\AccountsReceivable\Models\ReceivableDocument;
use App\CashApplication\Models\Payment;
use App\Core\I18n\MoneyFormatter;
use App\Core\I18n\ValueObjects\Money;
use App\Integrations\Flywire\Models\FlywirePayment;
use App\PaymentProcessing\Models\PaymentMethod;
use App\Themes\Interfaces\PdfVariablesInterface;
use App\Themes\Models\Theme;

class PaymentPdfVariables implements PdfVariablesInterface
{
    public function __construct(protected Payment $payment)
    {
    }

    public function generate(Theme $theme, array $opts = []): array
    {
        $this->payment = $this->addSurchargePercentage($this->payment);
        $variables = $this->payment->toArray();
        $htmlify = $opts['htmlify'] ?? true;

        // payment date
        $dateFormat = $this->payment->dateFormat();
        $variables['date'] = date($dateFormat, $variables['date']);
        $variables['time'] = $variables['date'];

        // payment method
        $variables['method'] = $this->payment->getMethod()->toString();

        // check #
        $variables['check_no'] = (PaymentMethod::CHECK == $this->payment->method) ? $this->payment->reference : false;

        // get the payment breakdown
        $seen = [];
        $breakdown = $this->payment->breakdown();
        foreach (['invoices', 'estimates', 'creditNotes'] as $type) {
            $variableKey = 'creditNotes' == $type ? 'credit_notes' : $type;
            $variables[$variableKey] = [];
            /** @var ReceivableDocument $document */
            foreach ($breakdown[$type] as $document) {
                $key = $type.$document->id();
                if (!isset($seen[$key])) {
                    $variables[$variableKey][] = $document->getThemeVariables()->generate($theme, $opts);
                    $seen[$key] = true;
                }
            }
        }

        // payment source
        $charge = $this->payment->charge;
        $source = $charge?->payment_source;
        $variables['payment_source'] = $source ? $source->toString(true) : false;

        // get the total payment amounts
        $amount = $this->payment->getAmount();
        $refundAmount = new Money($this->payment->currency, 0);

        if ($charge = $this->payment->charge) {
            $refundAmount = Money::fromDecimal($charge->currency, $charge->amount_refunded);
        }

        if ($htmlify) {
            $formatter = MoneyFormatter::get();
            $moneyFormat = $this->payment->moneyFormat();
            $variables['amount_refunded'] = $refundAmount->isPositive() ? $formatter->formatHtml($refundAmount, $moneyFormat) : false;
            $variables['amount_credited'] = ($breakdown['credited']->isPositive()) ? $formatter->formatHtml($breakdown['credited'], $moneyFormat) : false;
            $variables['amount_convenience_fee'] = ($breakdown['convenienceFee']->isPositive()) ? $formatter->formatHtml($breakdown['convenienceFee'], $moneyFormat) : false;
            $variables['surcharge_fee'] = ($breakdown['surchargeFee']->isPositive()) ? $formatter->formatHtml($breakdown['surchargeFee'], $moneyFormat) : false;
            $variables['amount'] = $formatter->formatHtml($amount);
        } else {
            $variables['amount_refunded'] = $refundAmount->isPositive() ? $refundAmount->toDecimal() : false;
            $variables['amount_credited'] = ($breakdown['credited']->isPositive()) ? $breakdown['credited']->toDecimal() : false;
            $variables['amount_convenience_fee'] = ($breakdown['convenienceFee']->isPositive()) ? $breakdown['convenienceFee']->toDecimal() : false;
            $variables['surcharge_fee'] = ($breakdown['surchargeFee']->isPositive()) ? $breakdown['surchargeFee']->toDecimal() : false;
            $variables['amount'] = $amount->toDecimal();
        }

        return $variables;
    }

    private function addSurchargePercentage(Payment $payment): Payment
    {
        // let's add surcharge percentage if there was any
        $flywirePayment = FlywirePayment::where('ar_payment_id', $payment->id)
            ->oneOrNull();

        if ($flywirePayment && $flywirePayment->surcharge_percentage > 0)
            $payment->setSurchargePercentage($flywirePayment->surcharge_percentage);

        return $payment;
    }
}
