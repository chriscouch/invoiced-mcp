<?php

namespace App\Controller\Admin;

use App\Entity\Invoiced\AccountingSyncProfile;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\CodeEditorField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\FormField;
use EasyCorp\Bundle\EasyAdminBundle\Filter\ChoiceFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\NumericFilter;

class AccountingSyncProfileCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return AccountingSyncProfile::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('Accounting Sync Profile')
            ->setEntityLabelInPlural('Accounting Sync Profiles')
            ->setSearchFields(['id', 'tenant.name', 'tenant_id'])
            ->setDefaultSort(['id' => 'ASC'])
            ->showEntityActionsInlined();
    }

    public function configureActions(Actions $actions): Actions
    {
        return $actions
            ->add('index', 'detail')
            ->disable('new', 'delete');
    }

    public function configureFilters(Filters $filters): Filters
    {
        return $filters
            ->add(ChoiceFilter::new('integration', 'Integration')->setChoices(AccountingSyncFieldMappingCrudController::INTEGRATION_CHOICES))
            ->add(NumericFilter::new('tenant_id', 'Tenant ID'));
    }

    public function configureFields(string $pageName): iterable
    {
        $lastSynced = DateTimeField::new('last_synced', 'Last Synced');
        $integration = ChoiceField::new('integration')
            ->setChoices(AccountingSyncFieldMappingCrudController::INTEGRATION_CHOICES);
        $startDate = DateField::new('invoice_start_date', 'Start Date');
        $readCustomers = BooleanField::new('read_customers', 'Read Customers');
        $readInvoices = BooleanField::new('read_invoices', 'Read Invoices');
        $readPdfs = BooleanField::new('read_pdfs', 'Read Invoice PDFs');
        $readInvoicesAsDrafts = BooleanField::new('read_invoices_as_drafts', 'Read Invoices as Drafts');
        $readCreditNotes = BooleanField::new('read_credit_notes', 'Read Credit Notes');
        $readPayments = BooleanField::new('read_payments', 'Read Payments');
        $createdAt = DateTimeField::new('created_at');
        $updatedAt = DateTimeField::new('updated_at', 'Updated At');
        $writeCustomers = BooleanField::new('write_customers', 'Write Customers');
        $writeInvoices = BooleanField::new('write_invoices', 'Write Invoices');
        $writePayments = BooleanField::new('write_payments', 'Write Payments');
        $writeCreditNotes = BooleanField::new('write_credit_notes', 'Write Credit Notes');
        $paymentAccounts = CodeEditorField::new('payment_accounts', 'Payment Deposits')
            ->setNumOfRows(3)
            ->setLanguage('js');
        $readCursor = DateTimeField::new('read_cursor', 'Read Cursor');
        $tenant = AssociationField::new('tenant');
        $parameters = CodeEditorField::new('parameters', 'Custom Settings')
            ->setNumOfRows(10)
            ->setLanguage('js');

        if (Crud::PAGE_INDEX === $pageName) {
            return [$tenant, $integration, $createdAt];
        } elseif (Crud::PAGE_DETAIL === $pageName) {
            return [FormField::addPanel('Sync Settings'), $integration, $lastSynced, $startDate, $tenant, $createdAt, $updatedAt, FormField::addPanel('Read Settings'), $readCursor, $readCustomers, $readInvoices, $readInvoicesAsDrafts, $readPdfs, $readCreditNotes, $readPayments, FormField::addPanel('Write Settings'), $writeCustomers, $writeInvoices, $writeCreditNotes, $writePayments, $paymentAccounts, FormField::addPanel('Custom Settings'), $parameters];
        } elseif (Crud::PAGE_EDIT === $pageName) {
            return [FormField::addPanel('Sync Settings'), $startDate, FormField::addPanel('Read Settings'), $readCursor, $readCustomers, $readInvoices, $readInvoicesAsDrafts, $readPdfs, $readPayments, FormField::addPanel('Write Settings'), $writeCustomers, $writeInvoices, $writeCreditNotes, $writePayments, $paymentAccounts, FormField::addPanel('Custom Settings'), $parameters];
        }

        return [];
    }
}
