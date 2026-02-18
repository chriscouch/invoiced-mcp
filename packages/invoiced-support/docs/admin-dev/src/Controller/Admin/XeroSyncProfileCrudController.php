<?php

namespace App\Controller\Admin;

use App\Entity\Invoiced\XeroSyncProfile;
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

class XeroSyncProfileCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return XeroSyncProfile::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setPageTitle(Crud::PAGE_INDEX, 'Xero Integrations')
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
        $integrationVersion = IntegerField::new('integration_version');
        $lastSynced = DateTimeField::new('last_synced', 'Last Synced');
        $invoiceStartDate = DateTimeField::new('invoice_start_date', 'Start Date');
        $readCursor = DateTimeField::new('read_cursor');
        $taxMode = TextField::new('tax_mode');
        $salesTaxAccount = TextField::new('sales_tax_account');
        $taxType = TextField::new('tax_type', 'Tax Type');
        $addTaxLineItem = BooleanField::new('add_tax_line_item', 'Add Tax Line Item');
        $undepositedFundsAccount = TextField::new('undeposited_funds_account', 'Undeposited Funds Account');
        $convenienceFeeAccount = TextField::new('convenience_fee_account');
        $paymentAccounts = CodeEditorField::new('payment_accounts', 'Payment Deposits')
            ->setNumOfRows(3)
            ->setLanguage('js');
        $itemAccount = TextField::new('item_account', 'Sales Account');
        $discountAccount = TextField::new('discount_account');
        $sendItemCode = BooleanField::new('send_item_code', 'Send Item Code');
        $readInvoices = BooleanField::new('read_invoices', 'Read Invoices');
        $readPdfs = BooleanField::new('read_pdfs', 'Read Invoice PDFs');
        $readInvoicesAsDrafts = BooleanField::new('read_invoices_as_drafts', 'Read Invoices as Drafts');
        $readPayments = BooleanField::new('read_payments', 'Read Payments');
        $readCreditNotes = BooleanField::new('read_credit_notes');
        $readCustomers = BooleanField::new('read_customers');
        $writeCustomers = BooleanField::new('write_customers');
        $writeCreditNotes = BooleanField::new('write_credit_notes');
        $writeInvoices = BooleanField::new('write_invoices');
        $writePayments = BooleanField::new('write_payments');
        $writeConvenienceFees = BooleanField::new('write_convenience_fees');
        $createdAt = DateTimeField::new('created_at');
        $updatedAt = DateTimeField::new('updated_at', 'Updated At');
        $tenant = AssociationField::new('tenant');
        $tenantId = IntegerField::new('tenant_id');

        if (Crud::PAGE_INDEX === $pageName) {
            return [$tenantId, $tenant, $createdAt];
        } elseif (Crud::PAGE_DETAIL === $pageName) {
            return [FormField::addPanel('Sync Settings'), $integrationVersion, $lastSynced, $invoiceStartDate, $tenant, $createdAt, $updatedAt, FormField::addPanel('Read Settings'), $readCursor, $readCustomers, $readInvoices, $readCreditNotes, $readPdfs, $readInvoicesAsDrafts, $readPayments, FormField::addPanel('Write Settings'), $writeCustomers, $writeInvoices, $writeCreditNotes, $writePayments, $writeConvenienceFees, $itemAccount, $discountAccount, $convenienceFeeAccount, $sendItemCode, $taxMode, $salesTaxAccount, $paymentAccounts, FormField::addPanel('V1 Settings'), $taxType, $addTaxLineItem, $undepositedFundsAccount];
        } elseif (Crud::PAGE_EDIT === $pageName) {
            return [FormField::addPanel('Sync Settings'), $integrationVersion, $invoiceStartDate, FormField::addPanel('Read Settings'), $readCursor, $readCustomers, $readInvoices, $readCreditNotes, $readPdfs, $readInvoicesAsDrafts, $readPayments, FormField::addPanel('Write Settings'), $writeCustomers, $writeInvoices, $writeCreditNotes, $writePayments, $writeConvenienceFees, $itemAccount, $discountAccount, $convenienceFeeAccount, $sendItemCode, $taxMode, $salesTaxAccount, $paymentAccounts, FormField::addPanel('V1 Settings'), $taxType, $addTaxLineItem, $undepositedFundsAccount];
        }

        return [];
    }
}
