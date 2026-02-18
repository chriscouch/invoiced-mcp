<?php

namespace App\Integrations\Flywire\Traits;

use App\PaymentProcessing\Models\MerchantAccount;
use App\PaymentProcessing\Models\PaymentMethod;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

trait FlywireTrait
{
    private const TOKENIZABLE = [
        'credit_card',
        'direct_debit',
    ];

    private const NATURAL_SORT = [
        'bank_transfer',
        'credit_card',
        'direct_debit',
        'online',
    ];

    private function paymentCallbackUrl(MerchantAccount $merchantAccount): string
    {
        return $this->urlGenerator->generate(
            'flywire_payment_callback',
            [
                'merchantAccountId' => $merchantAccount->id,
            ],
            UrlGeneratorInterface::ABSOLUTE_URL
        );
    }

    private function refundCallbackUrl(MerchantAccount $merchantAccount): string
    {
        return $this->urlGenerator->generate(
            'flywire_refund_callback',
            [
                'merchantAccountId' => $merchantAccount->id,
            ],
            UrlGeneratorInterface::ABSOLUTE_URL
        );
    }

    /**
     * Build filtering for Flywire Checkout modal to only show the current payment method
     * and enabled payment methods.
     *
     * @param PaymentMethod[] $methods
     */
    protected function buildFilters(string $methodId, array $methods, bool $tokenizationOnly = false): ?array
    {
        $result = [$methodId];

        foreach (self::NATURAL_SORT as $methodId2) {
            if ($tokenizationOnly && !in_array($methodId2, self::TOKENIZABLE)) {
                continue;
            }

            $found = false;
            foreach ($methods as $paymentMethod) {
                if ($paymentMethod->id == $methodId2) {
                    $found = true;
                    break;
                }
            }

            if (!in_array($methodId2, $result) && $found) {
                $result[] = $methodId2;
            }
        }

        return [
            'type' => $result,
        ];
    }

    /**
     * Build sorting for Flywire Checkout to show the selected payment method first.
     */
    protected function buildSort(string $methodId, bool $tokenizationOnly = false): ?array
    {
        $result = [$methodId];

        foreach (self::NATURAL_SORT as $methodId2) {
            if ($tokenizationOnly && !in_array($methodId2, self::TOKENIZABLE)) {
                continue;
            }

            if (!in_array($methodId2, $result)) {
                $result[] = $methodId2;
            }
        }

        return [
            ['type' => $result],
        ];
    }
}
