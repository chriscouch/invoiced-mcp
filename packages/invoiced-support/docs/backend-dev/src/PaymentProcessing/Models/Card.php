<?php

namespace App\PaymentProcessing\Models;

use App\Core\Orm\Property;
use App\Core\Orm\Type;
use App\PaymentProcessing\Gateways\FlywireGateway;

/**
 * This references a credit card stored on a payment gateway.
 *
 * @property string      $funding
 * @property string      $brand
 * @property string      $last4
 * @property int         $exp_month
 * @property int         $exp_year
 * @property string|null $issuing_country
 */
class Card extends PaymentSource
{
    protected static function getProperties(): array
    {
        return [
            'funding' => new Property(
                validate: ['enum', 'choices' => ['credit', 'debit', 'prepaid', 'unknown']],
                default: 'unknown',
            ),
            'brand' => new Property(
                required: true,
            ),
            'last4' => new Property(
                required: true,
            ),
            'exp_month' => new Property(
                type: Type::INTEGER,
                required: true,
            ),
            'exp_year' => new Property(
                type: Type::INTEGER,
                required: true,
            ),
            'issuing_country' => new Property(
                null: true,
            ),
        ];
    }

    public function toString($short = false): string
    {
        $brand = match (strtolower($this->brand)) {
            // Source: https://docs.adyen.com/pt/payment-methods/cards/custom-card-integration/#supported-card-types
            'amex', 'americanexpress', 'american express' => 'Amex',
            'cartebancaire' => 'Carte Bancaires',
            'cirrus' => 'Cirrus',
            'codensa' => 'Codensa',
            'cup' => 'China Union Pay',
            'dankort' => 'Dankort',
            'diners', 'diners club', 'diners club international', 'dinersclub' => 'Diners Club',
            'discover' => 'Discover',
            'electron' => 'Electron',
            'elo' => 'ELO',
            'jcb' => 'JCB',
            'laser' => 'Laser',
            'maestro' => 'Maestro',
            'maestrouk' => 'Maestro UK',
            'mc', 'mastercard' => 'Mastercard',
            'solo' => 'Solo',
            'unknown' => '',
            'visa' => 'Visa',
            default => $this->brand,
        };

        $str = $brand.' *'.$this->last4;
        if ($short) {
            return $str;
        }

        $t = (int) mktime(0, 0, 0, $this->exp_month, 1, $this->exp_year);

        return $str.' (expires '.date('M', $t).' \''.date('y', $t).')';
    }

    public function getMethod(): string
    {
        return PaymentMethod::CREDIT_CARD;
    }

    public function supportsConvenienceFees(): bool
    {
        return FlywireGateway::ID !== $this->gateway;
    }

    public function needsVerification(): bool
    {
        return false;
    }
}
