<?php

namespace App\AccountsPayable\Api;

use App\AccountsPayable\Models\CompanyBankAccount;
use App\AccountsPayable\Traits\SaveCompanyBankAccountApiRouteTrait;
use App\Core\RestApi\Routes\AbstractEditModelApiRoute;

/**
 * @extends AbstractEditModelApiRoute<CompanyBankAccount>
 */
class EditCompanyBankAccount extends AbstractEditModelApiRoute
{
    use SaveCompanyBankAccountApiRouteTrait;
}
