<?php

namespace App\AccountsReceivable\Traits;

use App\AccountsReceivable\Models\Customer;
use App\Companies\Models\Company;
use App\Core\I18n\Countries;
use App\Core\I18n\PhoneFormatter;
use App\Core\I18n\ValueObjects\Money;
use App\Core\Multitenant\Models\MultitenantModel;
use App\CustomerPortal\Libs\CustomerPortal;
use App\CustomerPortal\Libs\CustomerPortalHelper;
use App\Metadata\Interfaces\MetadataModelInterface;
use App\PaymentProcessing\Models\PaymentSource;
use libphonenumber\PhoneNumberFormat;
use Symfony\Component\HttpFoundation\Session\SessionInterface;

trait CustomerPortalViewVariablesTrait
{
    protected function makeBillFrom(Company $company, bool $showCountry): array
    {
        return [
            'logo' => $company->logo,
            'name' => $company->getDisplayName(),
            'address' => $company->address($showCountry, false),
            'email' => $company->email,
            'phone' => PhoneFormatter::format((string) $company->phone, $company->country, $showCountry ? PhoneNumberFormat::INTERNATIONAL : PhoneNumberFormat::NATIONAL),
            'website' => $company->website,
        ];
    }

    protected function makeBillTo(Customer $customer, bool $showCountry, bool $showAccountNumber): array
    {
        return [
            'name' => $customer->name,
            'attentionTo' => $customer->attention_to,
            'address' => $customer->address(false),
            'email' => $customer->email,
            'phone' => PhoneFormatter::format((string) $customer->phone, $customer->country, $showCountry ? PhoneNumberFormat::INTERNATIONAL : PhoneNumberFormat::NATIONAL),
            'accountNumber' => $showAccountNumber ? $customer->number : null,
        ];
    }

    protected function makeCustomFields(MetadataModelInterface&MultitenantModel $model, Customer $customer): array
    {
        $company = $model->tenant();
        $objectType = $model->getObjectName();

        return CustomerPortalHelper::getCustomFields($company, $customer, $objectType, $model);
    }

    /**
     * Generates a URL (path only) to a given customer portal route.
     */
    protected function generatePortalUrl(CustomerPortal $portal, string $route, array $parameters = []): string
    {
        $parameters['subdomain'] = $portal->company()->getSubdomainUsername();

        return $this->urlGenerator->generate($route, $parameters);
    }

    protected function returnToPaymentForm(CustomerPortal $portal, SessionInterface $session, PaymentSource $source): string
    {
        $params = $session->get('payment_form_return');
        $params['method'] = 'saved:'.$source->object.':'.$source->id();
        $session->set('payment_form_return', $params);

        return $this->generatePortalUrl($portal, 'customer_portal_payment_form');
    }

    protected function calculateTotals(array $results, array $keys, string $currency): array
    {
        $totals = [];
        foreach ($keys as $k) {
            $runningTotal = new Money($currency, 0);
            foreach ($results as $row) {
                $amount = $row['_'.$k];
                if ($amount->currency == $currency) {
                    $runningTotal = $runningTotal->add($amount);
                }
            }
            $totals[$k.'_currency'] = $runningTotal->currency;
            $totals[$k] = $runningTotal->toDecimal();
        }

        return $totals;
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
