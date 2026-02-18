<?php
namespace App\Automations\Providers;

use App\AccountsReceivable\Libs\CustomerBalanceGenerator;
use App\AccountsReceivable\Models\Customer;
use Symfony\Component\ExpressionLanguage\ExpressionFunction;
use Symfony\Component\ExpressionLanguage\ExpressionFunctionProviderInterface;

class CustomerBalanceExpressionFunctionProvider implements ExpressionFunctionProviderInterface
{
    public function __construct(private readonly CustomerBalanceGenerator $generator)
    {
    }

    public function getFunctions(): array
    {
        return [
            new ExpressionFunction('customerBalance', function (array $object, ?string $currency) {}, function (array $object, ?string $currency = null): ?float {
                if (!isset($object['customer'])) return null;
                $customer = Customer::find(((array) $object['customer'])['id']);
                if (!$customer) return null;
                return $this->generator->totalOutstanding($customer, ($currency ? strtolower($currency) : null) ?? $customer->currency ?? $customer->calculatePrimaryCurrency())->toDecimal();
            }),
        ];
    }
}