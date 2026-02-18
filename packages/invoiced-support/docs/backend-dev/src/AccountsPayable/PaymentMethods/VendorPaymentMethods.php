<?php

namespace App\AccountsPayable\PaymentMethods;

use App\AccountsPayable\Models\Vendor;
use App\PaymentProcessing\Models\PaymentMethod;

class VendorPaymentMethods
{
    /**
     * Gets a list of payment methods supported by a vendor.
     *
     * Possible values: ach, credit_card
     */
    public function getForVendor(Vendor $vendor): array
    {
        $result = [];

        if ($method = $this->getAch($vendor)) {
            $result[] = $method;
        }

        if ($method = $this->getCreditCard($vendor)) {
            $result[] = $method;
        }

        return $result;
    }

    private function getAch(Vendor $vendor): ?array
    {
        if (!$vendor->bank_account_id) {
            return null;
        }

        return [
            'type' => 'ach',
        ];
    }

    private function getCreditCard(Vendor $vendor): ?array
    {
        $networkConnection = $vendor->network_connection;
        if (!$networkConnection) {
            return null;
        }

        // Check if the payee has credit card payments enabled
        $vendorCompany = $networkConnection->vendor;
        $paymentMethod = PaymentMethod::queryWithTenant($vendorCompany)
            ->where('id', PaymentMethod::CREDIT_CARD)
            ->where('enabled', true)
            ->oneOrNull();
        if (!$paymentMethod) {
            return null;
        }

        // Stripe credit card processing is required to pay from an A/P account
        if ('stripe' != $paymentMethod->gateway) {
            return null;
        }

        return [
            'type' => 'credit_card',
            'currency' => $vendorCompany->currency,
            'convenience_fee_percent' => $paymentMethod->convenience_fee,
            'min' => $paymentMethod->min,
            'max' => $paymentMethod->max,
        ];
    }
}
