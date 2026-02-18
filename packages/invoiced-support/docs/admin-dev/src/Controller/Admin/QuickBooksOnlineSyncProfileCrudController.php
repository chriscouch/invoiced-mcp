<?php

namespace App\Controller\Admin;

use App\Entity\Invoiced\QuickBooksOnlineSyncProfile;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\CodeEditorField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\FormField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;

class QuickBooksOnlineSyncProfileCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return QuickBooksOnlineSyncProfile::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setPageTitle(Crud::PAGE_INDEX, 'QuickBooks Online Integrations')
            ->setSearchFields(['tenant_id', 'tenant.name'])
            ->setDefaultSort(['tenant_id' => 'ASC'])
            ->showEntityActionsInlined();
    }

    public function configureActions(Actions $actions): Actions
    {
        return $actions
            ->add('index', 'detail')
            ->disable('new', 'delete');
    }

    public function configureFields(string $pageName): iterable
    {
        $lastSynced = DateTimeField::new('last_synced', 'Last Synced');
        $invoiceStartDate = DateTimeField::new('invoice_start_date', 'Start Date');
        $discountAccount = TextField::new('discount_account', 'Discount Account');
        $taxCode = TextField::new('tax_code', 'Tax Code');
        $undepositedFundsAccount = TextField::new('undeposited_funds_account', 'Undeposited Funds Account');
        $readInvoices = BooleanField::new('read_invoices', 'Read Invoices');
        $readPdfs = BooleanField::new('read_pdfs', 'Read Invoice PDFs');
        $readInvoicesAsDrafts = BooleanField::new('read_invoices_as_drafts', 'Read Invoices as Drafts');
        $readPayments = BooleanField::new('read_payments', 'Read Payments');
        $customField1 = TextField::new('custom_field_1', 'Custom Field 1');
        $customField2 = TextField::new('custom_field_2', 'Custom Field 2');
        $customField3 = TextField::new('custom_field_3', 'Custom Field 3');
        $namespaceCustomers = BooleanField::new('namespace_customers', 'Namespace Customers');
        $namespaceItems = BooleanField::new('namespace_items', 'Namespace Items');
        $namespaceInvoices = BooleanField::new('namespace_invoices', 'Namespace Invoices');
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
        $tenantId = IntegerField::new('tenant_id');

        if (Crud::PAGE_INDEX === $pageName) {
            return [$tenantId, $tenant, $createdAt];
        } elseif (Crud::PAGE_DETAIL === $pageName) {
            return [FormField::addPanel('Sync Settings'), $lastSynced, $readCursor, $invoiceStartDate, $tenant, $createdAt, $updatedAt, FormField::addPanel('Read Settings'), $readInvoices, $readInvoicesAsDrafts, $readPdfs, $readPayments, FormField::addPanel('Write Settings'), $discountAccount, $taxCode, $undepositedFundsAccount, $customField1, $customField2, $customField3, $namespaceCustomers, $namespaceInvoices, $namespaceItems, $writeCustomers, $writeInvoices, $writePayments, $writeCreditNotes, $paymentAccounts];
        } elseif (Crud::PAGE_EDIT === $pageName) {
            return [FormField::addPanel('Sync Settings'), $invoiceStartDate, FormField::addPanel('Read Settings'), $readInvoices, $readInvoicesAsDrafts, $readPdfs, $readPayments, $readCursor, FormField::addPanel('Write Settings'), $discountAccount, $taxCode, $undepositedFundsAccount, $customField1, $customField2, $customField3, $namespaceCustomers, $namespaceInvoices, $namespaceItems, $writeCustomers, $writeInvoices, $writePayments, $writeCreditNotes, $paymentAccounts];
        }

        return [];
    }
}
