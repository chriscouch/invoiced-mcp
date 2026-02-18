<?php

namespace App\AccountsPayable\Libs;

use App\AccountsPayable\Models\CompanyBankAccount;
use App\Core\RestApi\Exception\InvalidRequest;
use App\Core\Utils\ModelUtility;
use App\Integrations\Plaid\Libs\PlaidApi;
use Throwable;

class CompanyBankAccountSave
{
    public function __construct(
        private readonly PlaidApi $plaidApi,
    ) {
    }

    public function save(CompanyBankAccount $bankAccount): CompanyBankAccount
    {
        if ($bankAccount->plaid) {
            $bankAccount = $this->getPlaidNumbers($bankAccount);

            $this->validateUnique($bankAccount);
        }
        if ($bankAccount->default) {
            $bankAccount = $this->markDefault($bankAccount);
        }

        $bankAccount->saveOrFail();

        return $bankAccount;
    }

    public function getPlaidNumbers(CompanyBankAccount $bankAccount): CompanyBankAccount
    {
        if ($bankAccount->plaid) {
            try {
                $numbers = $this->plaidApi->getAccount($bankAccount->plaid);
                $bankAccount->account_number = $numbers->account;
                $bankAccount->routing_number = $numbers->routing;
            } catch (Throwable) {
            }
        }

        return $bankAccount;
    }

    public function markDefault(CompanyBankAccount $bankAccount): CompanyBankAccount
    {
        $qry = CompanyBankAccount::where('default', 1);
        if ($bankAccount->id) {
            $qry->where('id', $bankAccount->id, '!=');
        }
        /** @var CompanyBankAccount[] $oldDefaults */
        $oldDefaults = $qry->first(100);
        foreach ($oldDefaults as $default) {
            $default->default = false;
            $default->saveOrFail();
        }

        return $bankAccount;
    }

    public function getMatch(CompanyBankAccount $model): ?CompanyBankAccount
    {
        $qry = CompanyBankAccount::withoutDeleted()
            ->where('routing_number', $model->routing_number);
        if ($model->id) {
            $qry->where('id', $model->id, '!=');
        }
        /** @var CompanyBankAccount[] $accounts */
        $accounts = ModelUtility::getAllModelsGenerator($qry);

        foreach ($accounts as $account) {
            if ($account->account_number === $model->account_number) {
                return $account;
            }
        }

        return null;
    }

    private function validateUnique(CompanyBankAccount $model): void
    {
        if ($this->getMatch($model)) {
            throw new InvalidRequest('This bank account already exists');
        }
    }
}
