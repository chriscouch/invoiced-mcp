<?php

namespace App\Core\Multitenant\Models;

use App\Companies\Models\Member;
use App\Core\Multitenant\TenantContextFacade;
use App\Metadata\Libs\RestrictionQueryBuilder;
use App\Core\Orm\ACLModelRequester;
use App\Core\Orm\Query;

trait HasCustomerRestrictionsTrait
{
    public static function customizeBlankQuery(Query $query): Query
    {
        return self::customizeBlankQueryRestrictions($query);
    }

    protected static function customizeBlankQueryRestrictions(Query $query): Query
    {
        // Limit the result set for the member's customer restrictions.
        $requester = ACLModelRequester::get();
        $propertyId = static::getCustomerPropertyName();
        if ($requester instanceof Member) {
            $tenant = TenantContextFacade::get()->get();
            if (Member::CUSTOM_FIELD_RESTRICTION == $requester->restriction_mode) {
                if ($restrictions = $requester->restrictions()) {
                    $restrictionQueryBuilder = new RestrictionQueryBuilder($tenant, $restrictions);
                    $restrictionQueryBuilder->addToOrmQuery($propertyId, $query);
                }
            } elseif (Member::OWNER_RESTRICTION == $requester->restriction_mode) {
                $query->where($propertyId.' IN (SELECT id FROM Customers WHERE tenant_id='.$tenant->id().' AND owner_id='.$requester->user_id.')');
            }
        }

        return $query;
    }

    protected static function getCustomerPropertyName(): string
    {
        return 'customer';
    }
}
