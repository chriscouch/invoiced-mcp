<?php

namespace App\Core\I18n;

use Exchanger\Exchanger;
use Exchanger\Service\Chain;
use Exchanger\Service\CurrencyLayer;

class CurrencyExchangerFactory
{
    public function __construct(
        private string $currencyLayerKey,
    ) {
    }

    public function make(): Exchanger
    {
        return new Exchanger(
            new Chain([
                new CurrencyLayer(null, null, [
                    'enterprise' => true,
                    'access_key' => $this->currencyLayerKey,
                ]),
            ])
        );
    }
}
