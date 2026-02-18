<?php

namespace App\Core\Templating;

use App\Core\I18n\MoneyFormatter;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;

class TwigExtension extends AbstractExtension
{
    public function getFilters(): array
    {
        return [
            new TwigFilter('money', [$this, 'money'], ['is_safe' => ['html'], 'needs_context' => true]),
            new TwigFilter('money_unit_cost', [$this, 'moneyUnitCost'], ['is_safe' => ['html'], 'needs_context' => true]),
        ];
    }

    /**
     * Adds the "|money" filter to Twig.
     */
    public function money(array $context, ?float $amount, string $currency): string
    {
        return MoneyFormatter::get()->currencyFormatHtml((float) $amount, $currency, $context['_moneyOptions']);
    }

    /**
     * Adds the "|money_unit_cost" filter to Twig.
     * Formats a number, i.e. 3000 -> 3,000 without performing any
     * rounding since PHP's number_format() implicitly rounds.
     */
    public function moneyUnitCost(array $context, ?float $amount, string $currency): string
    {
        return MoneyFormatter::get()->currencyFormatHtml((float) $amount, $currency, $context['_unitCostMoneyOptions']);
    }
}
