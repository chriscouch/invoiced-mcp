<?php

namespace App\Core\Templating;

use App\AccountsReceivable\Libs\CustomerBalanceGenerator;
use App\AccountsReceivable\Models\Customer;
use App\CashApplication\Models\CreditBalance;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;

class TwigAutomationsExtension extends AbstractExtension
{
    public function __construct(private readonly CustomerBalanceGenerator $balance)
    {
    }

    public function getFilters(): array
    {
        return [
            new TwigFilter('balance', [$this, 'balance'], ['is_safe' => ['html'], 'needs_context' => true]),
            new TwigFilter('credit_balance', [$this, 'creditBalance'], ['is_safe' => ['html'], 'needs_context' => true]),
        ];
    }

    /**
     * Adds the "|balance" filter to Twig.
     * retrieves customer balance.
     */
    public function balance(array $context, array $data, ?string $currency = null): float
    {
        if (!isset($data['id'])) {
            return 0;
        }

        $customer = Customer::findOrFail($data['id']);

        return CreditBalance::lookup($customer, $currency)->toDecimal();
    }

    /**
     * Adds the "|credit_balance" filter to Twig.
     * retrieves customer credit balance.
     */
    public function creditBalance(array $context, array $data, ?string $currency = null): float
    {
        if (!isset($data['id'])) {
            return 0;
        }

        if (!$currency) {
            $currency = $context['_defaultCurrency'];
        }

        $customer = Customer::findOrFail($data['id']);

        return $this->balance->totalOutstanding($customer, $currency)->subtract($this->balance->openCreditNotes($customer, $currency))->toDecimal();
    }
}
