<?php

namespace App\CustomerPortal\Libs;

use App\AccountsReceivable\Libs\CustomerHierarchy;
use App\AccountsReceivable\Libs\CustomerPermissionHelper;
use App\AccountsReceivable\Models\Contact;
use App\AccountsReceivable\Models\Customer;
use App\Companies\Models\Member;
use App\Core\Authentication\Libs\UserContext;
use Doctrine\DBAL\Connection;

readonly class CustomerPortalSecurityChecker
{
    public function __construct(
        private Connection $database,
        private CustomerPortalContext $customerPortalContext,
        private UserContext $userContext,
    ) {
    }

    public function canAccessCustomer(Customer $customer): bool
    {
        $customerPortal = $this->customerPortalContext->getOrFail();

        // Check if the currently signed in customer matches
        $signedInCustomer = $customerPortal->getSignedInCustomer();
        if ($signedInCustomer?->id == $customer->id) {
            return true;
        }

        // Check based on signed in email address
        if ($signedInEmail = $customerPortal->getSignedInEmail()) {
            if ($this->isCustomerAllowedForEmail($signedInEmail, $customer)) {
                return true;
            }
        }

        // Check for a signed in Invoiced user
        $user = $this->userContext->get();
        if ($user?->isFullySignedIn()) {
            $member = Member::getForUser($user);
            if ($member && CustomerPermissionHelper::canSeeCustomer($customer, $member)) {
                return true;
            }

            // Check based on user's email address
            if ($this->isCustomerAllowedForEmail($user->email, $customer)) {
                return true;
            }
        }

        return false;
    }

    private function isCustomerAllowedForEmail(string $email, Customer $customer): bool
    {
        if ($email == $customer->email) {
            return true;
        }

        return (bool) Contact::where('customer_id', $customer->id)
            ->where('email', $email)
            ->count();
    }

    /**
     * Gets a list of customer profiles that match a
     * given email address.
     *
     * @return Customer[]
     */
    public function getCustomersForEmail(CustomerPortal $portal, CustomerHierarchy $hierarchy): array
    {
        $user = $this->userContext->get();
        $customer = $portal->getSignedInCustomer();
        if ($user?->isFullySignedIn()) {
            $email = $user->email;
        } else if($portal->getSignedInEmail()){
            $email = $portal->getSignedInEmail();
        }else if ($customer){
            $email = $customer?->email ?? $customer->parentCustomer()?->email;
        }else{
            return [];
        }

        if ($email) {
            $sql = $this->database->prepare('
    SELECT id FROM (
        SELECT id
        FROM Customers
        WHERE email = :email AND tenant_id = :tenantId

        UNION ALL

        SELECT customer_id as id
        FROM Contacts
        WHERE email = :email AND tenant_id = :tenantId
    ) as ids GROUP by id
');
            $sql->bindValue('email', $email);
            $sql->bindValue('tenantId', $portal->company()->id());
            $ids = $sql->executeQuery()->fetchFirstColumn();
            $ids = array_unique(array_merge($ids, $hierarchy->getSubCustomerIdsByQuery($ids)));
        }else if ($customer){
            $id = $customer->parentCustomer()?->id ?? $customer->id;
            $ids = [$id];
            $ids = array_unique(array_merge($ids, $hierarchy->getSubCustomerIdsByQuery($ids)));
        }

        if(empty($ids)){
            return [];
        }

        $customers = Customer::queryWithoutMultitenancyUnsafe()
            ->with('tenant_id')
            ->where('id', $ids)
            ->all()
            ->toArray();

        usort($customers, fn ($a, $b) => strcasecmp($a->name, $b->name));

        return $customers;
    }
}
