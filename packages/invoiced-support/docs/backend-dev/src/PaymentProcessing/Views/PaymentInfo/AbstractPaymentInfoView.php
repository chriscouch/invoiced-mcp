<?php

namespace App\PaymentProcessing\Views\PaymentInfo;

use App\Core\I18n\Countries;
use App\PaymentProcessing\Interfaces\PaymentInfoViewInterface;
use Twig\Environment;

abstract class AbstractPaymentInfoView implements PaymentInfoViewInterface
{
    public function __construct(protected Environment $twig)
    {
    }

    /**
     * Returns a sorted list of countries.
     */
    protected function getCountries(): array
    {
        $countriesData = new Countries();
        $countries = $countriesData->all();

        usort($countries, fn ($a, $b) => strcasecmp($a['country'], $b['country']));

        return $countries;
    }
}
