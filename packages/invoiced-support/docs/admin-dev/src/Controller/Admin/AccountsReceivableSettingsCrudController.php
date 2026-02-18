<?php

namespace App\Controller\Admin;

use App\Entity\Invoiced\AccountsReceivableSettings;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\Field;
use EasyCorp\Bundle\EasyAdminBundle\Field\FormField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;

class AccountsReceivableSettingsCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return AccountsReceivableSettings::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('A/R Settings')
            ->setEntityLabelInPlural('A/R Settings')
            ->setSearchFields(['tenant_id'])
            ->setDefaultSort(['tenant_id' => 'DESC'])
            ->showEntityActionsInlined();
    }

    public function configureActions(Actions $actions): Actions
    {
        return $actions
            ->add('index', 'detail')
            ->disable('new', 'index', 'delete');
    }

    public function configureFields(string $pageName): iterable
    {
        $chaseNewInvoices = Field::new('chase_new_invoices');
        $defaultCollectionMode = TextField::new('default_collection_mode');
        $paymentTerms = TextField::new('payment_terms');
        $agingBuckets = TextField::new('aging_buckets');
        $agingDate = TextField::new('aging_date');
        $defaultTemplateId = IntegerField::new('default_template_id');
        $defaultThemeId = IntegerField::new('default_theme_id');
        $addPaymentPlanOnImport = TextField::new('add_payment_plan_on_import');
        $defaultConsolidatedInvoicing = BooleanField::new('default_consolidated_invoicing');
        $unitCostPrecision = IntegerField::new('unit_cost_precision');
        $allowChasing = BooleanField::new('allow_chasing');
        $chaseSchedule = TextareaField::new('chase_schedule');
        $autopayDelayDays = IntegerField::new('autopay_delay_days');
        $paymentRetrySchedule = TextField::new('payment_retry_schedule');
        $transactionsInheritInvoiceMetadata = BooleanField::new('transactions_inherit_invoice_metadata');
        $autoApplyCredits = BooleanField::new('auto_apply_credits');
        $savedCardsRequireCvc = BooleanField::new('saved_cards_require_cvc');
        $debitCardsOnly = BooleanField::new('debit_cards_only');
        $emailProvider = TextField::new('email_provider');
        $bcc = TextField::new('bcc');
        $replyToInboxId = IntegerField::new('reply_to_inbox_id');
        $taxCalculator = TextField::new('tax_calculator');
        $tenant = AssociationField::new('tenant');

        if (Crud::PAGE_DETAIL === $pageName) {
            return [
                FormField::addPanel('Defaults'),
                $tenant,
                $chaseNewInvoices,
                $defaultCollectionMode,
                $paymentTerms,
                $agingBuckets,
                $agingDate,
                $defaultTemplateId,
                $defaultThemeId,
                $addPaymentPlanOnImport,
                $defaultConsolidatedInvoicing,
                FormField::addPanel('Invoicing'),
                $unitCostPrecision,
                FormField::addPanel('Payments'),
                $autopayDelayDays,
                $paymentRetrySchedule,
                $transactionsInheritInvoiceMetadata,
                $autoApplyCredits,
                $savedCardsRequireCvc,
                $debitCardsOnly,
                FormField::addPanel('Email'),
                $emailProvider,
                $bcc,
                $replyToInboxId,
                FormField::addPanel('Sales Tax'),
                $taxCalculator,
                FormField::addPanel('Chasing (Legacy)'),
                $allowChasing,
                $chaseSchedule,
            ];
        } elseif (Crud::PAGE_EDIT === $pageName) {
            return [
                FormField::addPanel('Defaults'),
                $addPaymentPlanOnImport,
                $defaultConsolidatedInvoicing,
                FormField::addPanel('Payments'),
                $transactionsInheritInvoiceMetadata,
                $savedCardsRequireCvc,
                $debitCardsOnly,
            ];
        }

        return [];
    }
}
