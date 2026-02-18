<?php

namespace App\Tests\AccountsReceivable\Libs;

use App\AccountsReceivable\Libs\CustomerPermissionHelper;
use App\AccountsReceivable\Models\Customer;
use App\Companies\Models\Member;
use App\Tests\AppTestCase;

class CustomerPermissionHelperTest extends AppTestCase
{
    public function testCanSeeCustomerOwnerRestriction(): void
    {
        $member = new Member(['user_id' => 1]);
        $member->restriction_mode = Member::OWNER_RESTRICTION;

        $customer = new Customer();
        $this->assertFalse(CustomerPermissionHelper::canSeeCustomer($customer, $member));

        $customer->owner_id = 1;
        $this->assertTrue(CustomerPermissionHelper::canSeeCustomer($customer, $member));
    }

    public function testCanSeeCustomerCustomFieldRestriction(): void
    {
        $member = new Member();
        $member->restriction_mode = Member::CUSTOM_FIELD_RESTRICTION;
        $member->restrictions = ['territory' => ['Texas']];

        $customer = new Customer();
        $this->assertFalse(CustomerPermissionHelper::canSeeCustomer($customer, $member));

        $customer->metadata = (object) ['territory' => 'Not Texas'];
        $this->assertFalse(CustomerPermissionHelper::canSeeCustomer($customer, $member));

        $customer->metadata = (object) ['territory' => 'Texas'];
        $this->assertTrue(CustomerPermissionHelper::canSeeCustomer($customer, $member));
    }

    public function testCanSeeCustomerNoRestriction(): void
    {
        $member = new Member();
        $member->restriction_mode = Member::UNRESTRICTED;

        $customer = new Customer();
        $this->assertTrue(CustomerPermissionHelper::canSeeCustomer($customer, $member));
    }
}
