<?php

namespace App\AccountsPayable\ListQueryBuilder;

use App\AccountsPayable\Models\Vendor;
use App\Core\ListQueryBuilders\AbstractListQueryBuilder;

/**
 * @extends AbstractListQueryBuilder<Vendor>
 */
class VendorListQueryBuilder extends AbstractListQueryBuilder
{
    public function initialize(): void
    {
        $this->query = Vendor::queryWithTenant($this->company);

        $this->addFilters();
    }

    public static function getClassString(): string
    {
        return Vendor::class;
    }
}
