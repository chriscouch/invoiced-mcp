<?php

namespace App\CustomerPortal\Cards;

use App\AccountsReceivable\Models\Customer;
use App\Core\I18n\PhoneFormatter;
use App\CustomerPortal\Interfaces\CardInterface;
use App\CustomerPortal\Libs\CustomerPortal;
use App\CustomerPortal\Libs\CustomerPortalHelper;

class BillingDetailsCard implements CardInterface
{
    public function getData(CustomerPortal $customerPortal): array
    {
        $company = $customerPortal->company();
        /** @var Customer $customer */
        $customer = $customerPortal->getSignedInCustomer();

        $updateBillingInfoUrl = null;
        if ($customerPortal->allowEditingContactInformation()) {
            $updateBillingInfoUrl = '/billingInfo/'.$customer->client_id;
        }

        $customFieldValues = CustomerPortalHelper::getCustomFields($company, $customer, $customer->object, $customer);

        $showAccountNumber = $company->defaultTheme()->show_customer_no;

        return [
            'customer' => $customer,
            'phone' => PhoneFormatter::format(
                (string) $customer->phone,
                $customer->country,
            ),
            'address' => $customer->address,
            'accountNumber' => $showAccountNumber ? $customer->number : null,
            'customFields' => $customFieldValues,
            'updateBillingInfoUrl' => $updateBillingInfoUrl,
        ];
    }
}
