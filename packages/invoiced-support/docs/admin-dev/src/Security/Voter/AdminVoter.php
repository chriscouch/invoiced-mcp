<?php

namespace App\Security\Voter;

use App\Entity\CustomerAdmin\AuditEntry;
use App\Entity\CustomerAdmin\Contract;
use App\Entity\CustomerAdmin\Order;
use App\Entity\CustomerAdmin\User;
use App\Entity\Invoiced\AccountingSyncFieldMapping;
use App\Entity\Invoiced\AccountingSyncReadFilter;
use App\Entity\Invoiced\AccountSecurityEvent;
use App\Entity\Invoiced\BillingProfile;
use App\Entity\Invoiced\CanceledCompany;
use App\Entity\Invoiced\Company;
use App\Entity\Invoiced\CompanyNote;
use App\Entity\Invoiced\CustomerVolume;
use App\Entity\Invoiced\Dashboard;
use App\Entity\Invoiced\Feature;
use App\Entity\Invoiced\IntacctSyncProfile;
use App\Entity\Invoiced\InvoiceVolume;
use App\Entity\Invoiced\Member;
use App\Entity\Invoiced\MerchantAccount;
use App\Entity\Invoiced\PurchasePageContext;
use App\Entity\Invoiced\Quota;
use App\Entity\Invoiced\Template;
use App\Entity\Invoiced\UsagePricingPlan;
use App\Entity\Invoiced\User as InvoicedUser;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;
use Symfony\Component\Security\Core\Security;

class AdminVoter extends Voter
{
    private const SHOW = 'show';
    private const LIST = 'list';
    private const NEW = 'new';
    private const EDIT = 'edit';
    private const DELETE = 'delete';

    private const CREATE_PERMISSIONS = [
        'ROLE_SALES' => [
            BillingProfile::class,
            PurchasePageContext::class,
            Order::class,
        ],
        'ROLE_CUSTOMER_SUPPORT' => [
            AccountingSyncFieldMapping::class,
            AccountingSyncReadFilter::class,
            BillingProfile::class,
            CompanyNote::class,
            Contract::class,
            Dashboard::class,
            Feature::class,
            MerchantAccount::class,
            Order::class,
            PurchasePageContext::class,
            Quota::class,
            Template::class,
            UsagePricingPlan::class,
        ],
        'ROLE_ADMIN' => [
            AccountingSyncFieldMapping::class,
            AccountingSyncReadFilter::class,
            BillingProfile::class,
            CompanyNote::class,
            Contract::class,
            Dashboard::class,
            Feature::class,
            MerchantAccount::class,
            Order::class,
            PurchasePageContext::class,
            Quota::class,
            Template::class,
            UsagePricingPlan::class,
        ],
        'ROLE_SUPER_ADMIN' => [
            User::class,
        ],
    ];

    public function __construct(private Security $security)
    {
    }

    protected function supports($attribute, $subject): bool
    {
        return in_array($attribute, [self::SHOW, self::LIST, self::NEW, self::EDIT, self::DELETE]);
    }

    /**
     * @param string $attribute
     */
    protected function voteOnAttribute($attribute, $subject, TokenInterface $token): bool
    {
        // if the user is anonymous, do not grant access
        $user = $token->getUser();
        if (!$user instanceof User) {
            return false;
        }

        if (self::LIST == $attribute) {
            return $this->canList($subject);
        }

        if (self::SHOW == $attribute) {
            return $this->canShow($subject, $user);
        }

        if (self::NEW == $attribute) {
            return $this->checkPermissionsArray($subject, self::CREATE_PERMISSIONS);
        }

        if (self::EDIT == $attribute) {
            return $this->canEdit($subject);
        }

        if (self::DELETE == $attribute) {
            return $this->canDelete($subject, $user);
        }

        return false;
    }

    private function canList(mixed $subject): bool
    {
        // users and audit entries are only visible to super admins
        if (($subject instanceof User || $subject instanceof AuditEntry) && !$this->security->isGranted('ROLE_SUPER_ADMIN')) {
            return false;
        }

        // list permission on everything else is enabled for everyone
        return true;
    }

    private function canShow(mixed $subject, User $user): bool
    {
        // only super administrators can view other users
        if ($subject instanceof User && $user->getId() != $subject->getId() && !$this->security->isGranted('ROLE_SUPER_ADMIN')) {
            return false;
        }

        // only super administrators can view audit entries
        if ($subject instanceof AuditEntry && !$this->security->isGranted('ROLE_SUPER_ADMIN')) {
            return false;
        }

        // show permission on everything else is enabled for everyone
        return true;
    }

    private function canEdit(mixed $subject): bool
    {
        // only super administrators can edit internal users
        if ($subject instanceof User && !$this->security->isGranted('ROLE_SUPER_ADMIN')) {
            return false;
        }

        // all roles are allowed to edit orders
        if ($subject instanceof Order) {
            return true;
        }

        // sales role allowed to edit purchase pages
        if ($subject instanceof PurchasePageContext && $this->security->isGranted('ROLE_SALES')) {
            return true;
        }

        // edit permission is allowed on all other entities
        // for administrators and customer support reps
        return $this->security->isGranted('ROLE_ADMIN') ||
            $this->security->isGranted('ROLE_CUSTOMER_SUPPORT');
    }

    private function canDelete(mixed $subject, User $user): bool
    {
        // certain entities cannot be deleted
        if ($subject instanceof Company ||
            $subject instanceof CanceledCompany ||
            $subject instanceof InvoicedUser ||
            $subject instanceof AuditEntry ||
            $subject instanceof IntacctSyncProfile ||
            $subject instanceof AccountSecurityEvent ||
            $subject instanceof Member ||
            $subject instanceof CustomerVolume ||
            $subject instanceof InvoiceVolume) {
            return false;
        }

        // delete permission is allowed on all other entities
        // for administrators and customer support reps
        if ($subject instanceof PurchasePageContext && $this->security->isGranted('ROLE_SALES')) {
            return true;
        }

        if ($subject instanceof User && !$this->security->isGranted('ROLE_SUPER_ADMIN')) {
            return false;
        }

        // super admins cannot delete themselves
        if ($subject instanceof User && $user instanceof User && $user->getId() == $subject->getId()) {
            return false;
        }

        // delete permission is allowed on all other entities
        // for administrators and customer support reps
        return $this->security->isGranted('ROLE_ADMIN') ||
            $this->security->isGranted('ROLE_CUSTOMER_SUPPORT');
    }

    private function checkPermissionsArray(mixed $subject, array $permissionsByRole): bool
    {
        $className = is_object($subject) ? get_class($subject) : $subject;

        foreach ($permissionsByRole as $role => $permissions) {
            if (!$this->security->isGranted($role)) {
                continue;
            }

            if (in_array($className, $permissions)) {
                return true;
            }
        }

        return false;
    }
}
