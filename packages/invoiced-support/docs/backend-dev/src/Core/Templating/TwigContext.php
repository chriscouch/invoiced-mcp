<?php

namespace App\Core\Templating;

use App\Companies\Models\Company;
use Symfony\Contracts\Translation\TranslatorInterface;

class TwigContext
{
    public function __construct(
        private Company $company,
        private string $defaultCurrency,
        private array $moneyFormat,
        private TranslatorInterface $translator,
    ) {
    }

    /**
     * Gets the Twig template parameters from this context.
     */
    public function getParameters(): array
    {
        $moneyOptions = $this->moneyFormat;
        $unitCostMoneyOptions = $moneyOptions;
        $precision = $this->company->accounts_receivable_settings->unit_cost_precision;
        if (null !== $precision) {
            $unitCostMoneyOptions['precision'] = $precision;
        }

        return [
            '_translator' => $this->translator,
            '_defaultCurrency' => $this->defaultCurrency,
            '_moneyOptions' => $moneyOptions,
            '_unitCostMoneyOptions' => $unitCostMoneyOptions,
        ];
    }
}
