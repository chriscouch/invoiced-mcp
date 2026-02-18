<?php

namespace App\Core\Templating;

use App\Core\I18n\MoneyFormatter;
use NumberFormatter;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;
use Twig\TwigFunction;

class TwigUserTemplateExtension extends AbstractExtension
{
    public function getFilters(): array
    {
        return [
            new TwigFilter('money', [$this, 'money'], ['is_safe' => ['html'], 'needs_context' => true]),
            new TwigFilter('money_unit_cost', [$this, 'moneyUnitCost'], ['is_safe' => ['html'], 'needs_context' => true]),
            new TwigFilter('number_format_no_round', [$this, 'numberFormatNoRound'], ['needs_context' => true]),
        ];
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('trans', [$this, 'trans'], ['needs_context' => true]),
            new TwigFunction('transchoice', [$this, 'transchoice'], ['needs_context' => true]),
            new TwigFunction('dump_scope', [$this, 'dumpScope'], ['is_safe' => ['html'], 'needs_context' => true]),
            new TwigFunction('dump', [$this, 'dump'], ['is_safe' => ['html']]),
        ];
    }

    /**
     * Adds the "|money" filter to Twig.
     */
    public function money(array $context, ?float $amount, ?string $currency = null): string
    {
        if (!$currency) {
            $currency = $context['_defaultCurrency'];
        }

        $value = MoneyFormatter::get()->currencyFormatHtml((float) $amount, (string) $currency, $context['_moneyOptions']);

        // Bugfix: the ₹ symbol is not supported in
        // our PDF system so replace it with the English version.
        if ('inr' == $currency) {
            return str_replace('₹', 'Rs', $value);
        }

        return $value;
    }

    /**
     * Adds the "|money_unit_cost" filter to Twig.
     * Formats a number, i.e. 3000 -> 3,000 without performing any
     * rounding since PHP's number_format() implicitly rounds.
     */
    public function moneyUnitCost(array $context, ?float $amount, ?string $currency = null): string
    {
        if (!$currency) {
            $currency = $context['_defaultCurrency'];
        }

        $value = MoneyFormatter::get()->currencyFormatHtml((float) $amount, (string) $currency, $context['_unitCostMoneyOptions']);

        // Bugfix: the ₹ symbol is not supported in
        // our PDF system so replace it with the English version.
        if ('inr' == $currency) {
            return str_replace('₹', 'Rs', $value);
        }

        return $value;
    }

    /**
     * Adds the "|number_format_no_round" filter to Twig.
     */
    public function numberFormatNoRound(array $context, ?float $n, ?string $locale = null): string
    {
        if (!$locale) {
            $locale = $context['_moneyOptions']['locale'];
        }

        $formatter = new NumberFormatter($locale, NumberFormatter::DECIMAL);
        $formatter->setAttribute(NumberFormatter::MAX_FRACTION_DIGITS, 5);

        return (string) $formatter->format((float) $n);
    }

    /**
     * Adds the "trans(key, options, domain)" function to Twig.
     */
    public function trans(array $context, string $key, array $options = [], string $domain = 'pdf'): string
    {
        return $context['_translator']->trans($key, $options, $domain);
    }

    /**
     * Adds the "transchoice(key, number, options, domain)" function to Twig.
     */
    public function transchoice(array $context, string $key, int $number, array $options = [], string $domain = 'pdf'): string
    {
        return $context['_translator']->transChoice($key, $number, $options, $domain);
    }

    /**
     * Adds the "dump_scope()" function to Twig.
     */
    public function dumpScope(array $context): string
    {
        // hide variables that are prefixed with "_"
        $variables = [];
        foreach ($context as $k => $v) {
            if ('_' != substr($k, 0, 1)) {
                $variables[$k] = $v;
            }
        }

        return '<pre class="dump-scope">'.json_encode($variables, JSON_PRETTY_PRINT).'</pre>';
    }

    /**
     * Adds the "dump($variable)" function to Twig.
     */
    public function dump(mixed $value): string
    {
        return '<pre class="dump-scope">'.json_encode($value, JSON_PRETTY_PRINT).'</pre>';
    }
}
