<?php

namespace App\Network\Models;

use App\Companies\Models\Company;
use App\Core\Orm\Model;
use App\Core\Orm\Property;
use App\Core\Orm\Traits\AutoTimestamps;

/**
 * @property int     $id
 * @property Company $vendor
 * @property int     $vendor_id
 * @property Company $customer
 * @property int     $customer_id
 */
class NetworkConnection extends Model
{
    use AutoTimestamps;

    protected static function getProperties(): array
    {
        return [
            'customer' => new Property(
                belongs_to: Company::class,
            ),
            'vendor' => new Property(
                belongs_to: Company::class,
            ),
        ];
    }

    /**
     * Gets the company in the transaction that is not the
     * given company.
     */
    public function getCounterparty(Company $company): Company
    {
        return $company->id == $this->customer_id ? $this->vendor : $this->customer;
    }

    /**
     * Gets the network connection for a customer and vendor.
     */
    public static function forCustomer(Company $vendor, Company $customer): ?self
    {
        return NetworkConnection::where('vendor_id', $vendor)
            ->where('customer_id', $customer)
            ->oneOrNull();
    }
}
