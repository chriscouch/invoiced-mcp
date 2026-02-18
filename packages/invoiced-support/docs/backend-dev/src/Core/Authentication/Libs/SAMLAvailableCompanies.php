<?php

namespace App\Core\Authentication\Libs;

use App\Companies\Models\Member;
use App\Core\Authentication\Models\CompanySamlSettings;
use App\Core\Authentication\Models\User;

class SAMLAvailableCompanies
{
    /**
     * @return CompanySamlSettings[]
     */
    public function get(User $user, ?int $companyId = null): array
    {
        $qry = Member::queryWithoutMultitenancyUnsafe()
            ->where('user_id', $user->id());
        if ($companyId) {
            $qry->where('tenant_id', $companyId);
        }
        $members = $qry->all()->toArray();

        if (!$members) {
            return [];
        }

        return CompanySamlSettings::where('company_id', array_map(fn ($m) => $m->tenant_id, $members))
            ->where('enabled', true)
            ->all()
            ->toArray();
    }
}
