<?php

namespace App\AccountsPayable\Api;

use App\AccountsPayable\Models\CompanyBankAccount;
use App\AccountsPayable\Traits\SaveCompanyBankAccountApiRouteTrait;
use App\Core\RestApi\Routes\AbstractCreateModelApiRoute;

/**
 * @extends AbstractCreateModelApiRoute<CompanyBankAccount>
 */
class CreateCompanyBankAccountApiRoute extends AbstractCreateModelApiRoute
{
    use SaveCompanyBankAccountApiRouteTrait;
}
