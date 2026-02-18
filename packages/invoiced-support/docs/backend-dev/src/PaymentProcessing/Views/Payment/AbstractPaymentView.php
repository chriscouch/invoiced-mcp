<?php

namespace App\PaymentProcessing\Views\Payment;

use App\Core\I18n\Countries;
use App\PaymentProcessing\Interfaces\PaymentViewInterface;
use App\PaymentProcessing\Models\MerchantAccount;
use App\PaymentProcessing\Models\PaymentFlow;
use App\PaymentProcessing\Models\PaymentMethod;
use App\PaymentProcessing\ValueObjects\PaymentForm;
use Symfony\Contracts\Translation\TranslatorInterface;
use Twig\Environment;

/**
 * Base class for payment form views.
 */
abstract class AbstractPaymentView implements PaymentViewInterface
{
    public function __construct(
        protected Environment $twig,
        protected TranslatorInterface $translator,
    ) {
    }

    abstract protected function getTemplate(): string;

    public function render(PaymentForm $form, PaymentMethod $paymentMethod, ?MerchantAccount $merchantAccount, PaymentFlow $paymentFlow): string
    {
        return $this->twig->render(
            $this->getTemplate(),
            $this->getViewParameters($form, $paymentMethod, $merchantAccount, $paymentFlow)
        );
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
