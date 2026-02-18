<?php

namespace App\CustomerPortal\Cards;

use App\AccountsReceivable\Models\Customer;
use App\CustomerPortal\Interfaces\CardInterface;
use App\CustomerPortal\Libs\CustomerPortal;
use App\CustomerPortal\Libs\CustomerPortalHelper;
use App\Companies\Models\Company;
use Symfony\Component\HttpFoundation\RequestStack;

class PaymentMethodCard implements CardInterface
{
    public function __construct(private RequestStack $requestStack)
    {
    }

    public function getData(CustomerPortal $customerPortal): array
    {
        $company = $customerPortal->company();
        /** @var Customer $customer */
        $customer = $customerPortal->getSignedInCustomer();

        $flashBag = $this->requestStack->getSession()->getFlashBag(); /* @phpstan-ignore-line */
        $paymentSourceConnected = count($flashBag->get('paymentSourceConnected')) > 0;

        $paymentSources = [];
        foreach ($customer->paymentSources() as $paymentSource) {
            $paymentSources[] = [
                'object' => $paymentSource->object,
                'id' => $paymentSource->id(),
                'icon' => CustomerPortalHelper::getPaymentSourceIcon($paymentSource),
                'description' => $paymentSource->toString(),
                'default' => $paymentSource->isDefault(),
                'needsVerification' => $paymentSource->needsVerification(),
            ];
        }

        $settings = $customerPortal->getPaymentFormSettings();

        return [
            'paymentSources' => $paymentSources,
            'subdomain' => $company->getSubdomainUsername(),
            'clientId' => $customer->client_id,
            'paymentSourceConnected' => $paymentSourceConnected,
            'autoPayEnabled' => $customer->autopay,
            'canEnrollInAutoPay' => $settings->allowAutoPayEnrollment,
            'autoPayDelay' => $this->getAutoPayDelay($company, $customer),
        ];
    }

    private function getAutoPayDelay(Company $company, Customer $customer): int
    {
        $days = $customer->autopay_delay_days;
        if ($days >= 0) {
            return $days;
        }

        $days = $company->accounts_receivable_settings->autopay_delay_days;
        if ($days >= 0) {
            return $days;
        }

        return 0;
    }
}
