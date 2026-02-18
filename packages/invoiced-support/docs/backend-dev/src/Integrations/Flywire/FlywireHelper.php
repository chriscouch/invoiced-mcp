<?php

namespace App\Integrations\Flywire;

use App\PaymentProcessing\Models\MerchantAccount;

final class FlywireHelper
{
    /**
     * Gets the Flywire portal code from the merchant's configuration
     * for a given currency.
     */
    public static function getPortalCodeForCurrency(MerchantAccount $merchantAccount, string $currency): ?string
    {
        $credentials = $merchantAccount->credentials;
        $portalCodes = $credentials->flywire_portal_codes ?? [];

        foreach ($portalCodes as $row) {
            if ($row->currency === $currency) {
                return $row->id;
            }
        }

        return null;
    }

    /**
     * Gets a list of all portal codes associated with the merchant's configuration.
     */
    public static function getPortalCodes(MerchantAccount $merchantAccount): array
    {
        $credentials = $merchantAccount->credentials;
        $portalCodes = $credentials->flywire_portal_codes ?? [];
        $result = [];

        foreach ($portalCodes as $row) {
            $ids = explode(',', str_replace(' ', '', $row->id));
            array_push($result, ...array_filter($ids));
        }

        return $result;
    }

    public static function getSecret(MerchantAccount $merchantAccount): string
    {
        $credentials = $merchantAccount->credentials;

        return $credentials->shared_secret ?? '';
    }
}
