<?php

namespace App\Reports\ValueObjects;

use App\AccountsReceivable\Models\Customer;
use App\Companies\Models\Company;
use App\Companies\Models\Member;

final class DashboardContext
{
    public function __construct(
        public readonly Company $company,
        public readonly ?Member $member = null,
        public readonly ?Customer $customer = null,
    ) {
    }
}
