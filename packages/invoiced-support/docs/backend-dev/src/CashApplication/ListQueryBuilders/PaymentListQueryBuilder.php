<?php

namespace App\CashApplication\ListQueryBuilders;

use App\CashApplication\Models\Payment;
use App\Core\RestApi\ValueObjects\ListFilter;
use App\Core\ListQueryBuilders\AbstractListQueryBuilder;

/**
 * @extends AbstractListQueryBuilder<Payment>
 */
class PaymentListQueryBuilder extends AbstractListQueryBuilder
{
    protected function fixLegacyOptions(ListFilter $filter): ListFilter
    {
        $filter = parent::fixLegacyOptions($filter);
        $filter = $this->fixLegacyNumericJson($filter, 'balance');

        return $this->fixLegacyNumericJson($filter, 'amount');
    }

    public function initialize(): void
    {
        $this->query = Payment::queryWithTenant($this->company);
        $this->addFilters();
    }

    public static function getClassString(): string
    {
        return Payment::class;
    }
}
