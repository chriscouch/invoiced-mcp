<?php

namespace App\Controller\Admin;

use App\Entity\Invoiced\CustomerPortalSettings;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;

class CustomerPortalSettingsCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return CustomerPortalSettings::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('Customer Portal Settings')
            ->setEntityLabelInPlural('Customer Portal Settings')
            ->setSearchFields(['tenant_id'])
            ->setDefaultSort(['tenant_id' => 'DESC'])
            ->showEntityActionsInlined();
    }

    public function configureActions(Actions $actions): Actions
    {
        return $actions
            ->add('index', 'detail')
            ->disable('new', 'index', 'delete', 'edit');
    }

    public function configureFields(string $pageName): iterable
    {
        $allowInvoicePaymentSelector = BooleanField::new('allow_invoice_payment_selector', 'Allow Applying Credits');
        $allowPartialPayments = BooleanField::new('allow_partial_payments');
        $allowAutopayEnrollment = BooleanField::new('allow_autopay_enrollment');
        $allowBillingPortalCancellations = BooleanField::new('allow_billing_portal_cancellations', 'Allow Subscription Cancellations');
        $billingPortalShowCompanyName = BooleanField::new('billing_portal_show_company_name', 'Show Company Name');
        $allowBillingPortalProfileChanges = BooleanField::new('allow_billing_portal_profile_changes', 'Allow Editing Customer Profile');
        $googleAnalyticsId = TextField::new('google_analytics_id');
        $tenant = AssociationField::new('tenant');
        $enabled = BooleanField::new('enabled');
        $includeSubCustomers = BooleanField::new('include_sub_customers');
        $showPoweredBy = BooleanField::new('show_powered_by');
        $requireAuthentication = BooleanField::new('require_authentication');
        $allowEditingContacts = BooleanField::new('allow_editing_contacts');
        $invoicePaymentToItemSelection = BooleanField::new('invoice_payment_to_item_selection');
        $welcomeMessage = TextField::new('welcome_message');

        if (Crud::PAGE_DETAIL === $pageName) {
            return [
                $tenant,
                $enabled,
                $billingPortalShowCompanyName,
                $showPoweredBy,
                $requireAuthentication,
                $invoicePaymentToItemSelection,
                $includeSubCustomers,
                $allowInvoicePaymentSelector,
                $allowPartialPayments,
                $allowAutopayEnrollment,
                $allowBillingPortalCancellations,
                $allowBillingPortalProfileChanges,
                $allowEditingContacts,
                $googleAnalyticsId,
                $welcomeMessage,
            ];
        }

        return [];
    }
}
