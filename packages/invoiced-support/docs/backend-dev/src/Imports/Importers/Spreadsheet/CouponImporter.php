<?php

namespace App\Imports\Importers\Spreadsheet;

use App\AccountsReceivable\Models\Coupon;

/**
 * Spreadsheet importer for coupons.
 */
class CouponImporter extends PricingObjectImporter
{
    public function getModelClass(): string
    {
        return Coupon::class;
    }
}
