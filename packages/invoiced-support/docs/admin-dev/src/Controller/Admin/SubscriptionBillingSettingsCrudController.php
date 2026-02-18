<?php

namespace App\Controller\Admin;

use App\Entity\Invoiced\SubscriptionBillingSettings;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;

class SubscriptionBillingSettingsCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return SubscriptionBillingSettings::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('Subscription Billing Settings')
            ->setEntityLabelInPlural('Subscription Billing Settings')
            ->setSearchFields(['tenant_id'])
            ->setDefaultSort(['tenant_id' => 'DESC'])
            ->showEntityActionsInlined();
    }

    public function configureActions(Actions $actions): Actions
    {
        return $actions
            ->add('index', 'detail')
            ->disable('new', 'index', 'edit', 'delete');
    }

    public function configureFields(string $pageName): iterable
    {
        $afterSubscriptionNonpayment = TextField::new('after_subscription_nonpayment');
        $subscriptionDraftInvoices = BooleanField::new('subscription_draft_invoices');
        $tenant = AssociationField::new('tenant');

        if (Crud::PAGE_DETAIL === $pageName) {
            return [
                $tenant,
                $afterSubscriptionNonpayment,
                $subscriptionDraftInvoices,
            ];
        }

        return [];
    }
}
