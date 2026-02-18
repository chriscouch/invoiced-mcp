<?php

namespace App\Companies\Libs;

use App\Companies\Models\Company;
use App\Core\Authentication\Models\User;
use Doctrine\DBAL\Connection;

class UserCustomerServiceProfile
{
    public function __construct(private Connection $database, private CompanyCustomerServiceProfile $companyProfile)
    {
    }

    /**
     * Builds a profile of this user for customer service purposes.
     */
    public function build(User $user): array
    {
        // # of sign ins
        $signInCount = $this->database->fetchOne('SELECT COUNT(*) FROM AccountSecurityEvents WHERE user_id = ?', [$user->id()]);

        // timestamp last seen
        $lastSeen = $this->database->fetchOne('SELECT created_at FROM AccountSecurityEvents WHERE user_id = ? ORDER BY created_at DESC', [$user->id()]);

        /* Get the user's list of companies */

        $cids = $this->database->createQueryBuilder()
            ->select('c.id')
            ->from('Members', 'cm')
            ->join('cm', 'Companies', 'c', 'cm.tenant_id = c.id')
            ->where('cm.user_id = :userId')
            ->setParameter('userId', $user->id())
            ->andWhere('c.canceled = 0')
            ->andWhere('(expires = 0 OR expires > '.time().')')
            ->fetchFirstColumn();

        $companies = [];
        foreach ($cids as $id) {
            $company = Company::findOrFail($id);
            $companies[] = $this->companyProfile->build($company, $user);
        }

        return [
            // user profile
            'id' => $user->id(),
            'first_name' => $user->first_name,
            'last_name' => $user->last_name,
            'created_at' => date('n/j/Y', $user->created_at),
            'sign_in_count' => $signInCount,
            'current_sign_in_at' => date('n/j/Y', strtotime($lastSeen)),
            'enabled' => $user->enabled,
            'generated_at' => date('Y-m-d H:i:s \Z'),
            // businesses they are a member of
            'companies' => $companies,
        ];
    }
}
