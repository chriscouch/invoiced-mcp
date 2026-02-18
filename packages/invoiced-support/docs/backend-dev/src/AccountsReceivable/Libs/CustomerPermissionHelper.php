<?php

namespace App\AccountsReceivable\Libs;

use App\AccountsReceivable\Models\Customer;
use App\Companies\Models\Member;

class CustomerPermissionHelper
{
    /**
     * Checks if a company member has permission to view a customer.
     */
    public static function canSeeCustomer(Customer $customer, Member $member): bool
    {
        if (Member::OWNER_RESTRICTION == $member->restriction_mode) {
            // owner check prevents null==null (unsaved member)
            return $customer->owner_id && $customer->owner_id == $member->user_id;
        }

        if (Member::CUSTOM_FIELD_RESTRICTION == $member->restriction_mode) {
            if ($restrictions = $member->restrictions()) {
                $metadata = $customer->metadata;
                foreach ($restrictions as $restriction) {
                    $key = $restriction->getKey();
                    if (!property_exists($metadata, $key)) {
                        continue;
                    }

                    $values = $restriction->getValues();
                    foreach ($values as $value) {
                        if ($metadata->{$key} == $value) {
                            return true;
                        }
                    }
                }

                return false;
            }
        }

        return true;
    }
}
