<?php

namespace App\Integrations\Textract\ValueObjects;

use App\AccountsPayable\Models\Vendor;
use App\Companies\Models\Company;
use App\Core\I18n\Countries;
use App\Core\I18n\ValueObjects\Money;
use Carbon\CarbonImmutable;

class AnalyzedParameters
{
    const UNCATEGORIZED = 'Uncategorized Expense';

    public ?Vendor $vendorObject = null;
    public ?string $vendor = null;
    public string $currency = 'usd';
    public array $line_items;
    public ?string $date;

    public function __construct(
        Company $company,
        array $line_items,
        public ?string $number = null,
        ?string $date = null,
        ?string $currency = null,
        public float $total = 0,
        ?string $vendor = null,
        public ?string $address1 = null,
        public ?string $city = null,
        public ?string $state = null,
        public ?string $postal_code = null,
        public readonly ?string $country = null,
    ) {
        // assure that line items are arrays not objects
        $this->line_items = array_map(fn ($item) => (array) $item, $line_items);
        // replace all spacing characters with single space
        if ($vendor) {
            $this->vendor = preg_replace('!\s+!', ' ', $vendor);
        }

        if ($currency && preg_match('/^[a-zA-Z]{3}$/', $currency)) {
            $this->currency = $currency;
        }

        $delta = Money::fromDecimal($this->currency, $total)->subtract(
            Money::fromDecimal($this->currency, array_reduce($this->line_items, fn ($total, $item) => $total + ($item['amount'] ?? 0), 0))
        );
        if (!$delta->isZero()) {
            $this->line_items[] = [
                'description' => self::UNCATEGORIZED,
                'amount' => $delta->toDecimal(),
            ];
        }

        $this->date = $date ? CarbonImmutable::parse($date)->format($company->date_format) : null;
    }

    public function isValid(): bool
    {
        return $this->vendorObject && $this->number && $this->total && count($this->line_items);
    }

    public function toArray(): array
    {
        $serialized = (array) $this;
        unset($serialized['vendorObject']);

        return $serialized;
    }

    public function getCountry(): ?string
    {
        return $this->country && Countries::validateCountry($this->country) ? $this->country : null;
    }
}
