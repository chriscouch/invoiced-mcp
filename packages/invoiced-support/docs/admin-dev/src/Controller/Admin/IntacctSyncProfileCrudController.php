<?php

namespace App\Controller\Admin;

use App\Entity\Invoiced\IntacctSyncProfile;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\CodeEditorField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\FormField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;

class IntacctSyncProfileCrudController extends AbstractCrudController
{
    private const INTEGRATION_VERSION = ['2' => 2, '3' => 3];
    private const CUSTOMER_SOURCE = ['Customer' => 'customer', 'Bill To Contact' => 'bill_to_contact'];

    public static function getEntityFqcn(): string
    {
        return IntacctSyncProfile::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setPageTitle(Crud::PAGE_INDEX, 'Intacct Integrations')
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
        $integrationVersion = ChoiceField::new('integration_version', 'Integration Version')->setChoices(self::INTEGRATION_VERSION);
        $readCustomers = BooleanField::new('read_customers', 'Read Customers');
        $writeCustomers = BooleanField::new('write_customers', 'Write Customers');
        $readInvoices = BooleanField::new('read_invoices', 'Read Invoices');
        $writeInvoices = BooleanField::new('write_invoices', 'Write Invoices');
        $writeToOrderEntry = BooleanField::new('write_to_order_entry', 'Write to Order Entry');
        $readArAdjustments = BooleanField::new('read_ar_adjustments', 'Read A/R Adjustments');
        $readCreditNotes = BooleanField::new('read_credit_notes', 'Read Credit Notes');
        $writeCreditNotes = BooleanField::new('write_credit_notes', 'Write Credit Notes');
        $readPayments = BooleanField::new('read_payments', 'Read Payments');
        $writePayments = BooleanField::new('write_payments', 'Write Payments');
        $lastSynced = DateTimeField::new('last_synced', 'Last Synced');
        $itemAccount = TextField::new('item_account', 'Item Account');
        $paymentAccounts = CodeEditorField::new('payment_accounts', 'Payment Accounts')
            ->setNumOfRows(3)
            ->setLanguage('js');
        $invoiceStartDate = DateTimeField::new('invoice_start_date', 'Start Date');
        $itemLocationId = TextField::new('item_location_id', 'Item Location Id');
        $itemDepartmentId = TextField::new('item_department_id', 'Item Department Id');
        $invoiceTypes = CodeEditorField::new('invoice_types', 'Invoice Types')
            ->setNumOfRows(1)
            ->setLanguage('js');
        $invoiceImportMapping = CodeEditorField::new('invoice_import_mapping', 'Order Entry Transaction Read Mapping')
            ->setNumOfRows(3)
            ->setLanguage('js');
        $customerQueryAddon = TextField::new('customer_read_query_addon', 'Customer Read Query Addon');
        $arAdjustmentQueryAddon = TextField::new('ar_adjustment_read_query_addon', 'A/R Adjustment Read Query Addon');
        $invoiceImportQueryAddon = CodeEditorField::new('invoice_import_query_addon', 'Order Entry Transaction Read Query Addon')
            ->setNumOfRows(3)
            ->setLanguage('js');
        $lineItemImportMapping = CodeEditorField::new('line_item_import_mapping', 'Order Entry Line Item Read Mapping')
            ->setNumOfRows(3)
            ->setLanguage('js');
        $customerCustomFieldMapping = CodeEditorField::new('customer_custom_field_mapping', 'Customer Custom Field Mapping')
            ->setNumOfRows(3)
            ->setLanguage('js');
        $invoiceCustomFieldMapping = CodeEditorField::new('invoice_custom_field_mapping', 'Invoice Custom Field Mapping')
            ->setNumOfRows(3)
            ->setLanguage('js');
        $lineItemCustomFieldMapping = CodeEditorField::new('line_item_custom_field_mapping', 'Line Item Custom Field Mapping')
            ->setNumOfRows(3)
            ->setLanguage('js');
        $createdAt = DateTimeField::new('created_at');
        $updatedAt = DateTimeField::new('updated_at', 'Updated At');
        $customerTopLevel = BooleanField::new('customer_top_level', 'Sync Customers at Top Level');
        $customerImportType = ChoiceField::new('customer_import_type', 'Customer Source')->setChoices(self::CUSTOMER_SOURCE);
        $mapCatalogItemToItemId = BooleanField::new('map_catalog_item_to_item_id', 'Send Item ID');
        $invoiceLocationIdFilter = TextField::new('invoice_location_id_filter', 'Order Entry Transaction Location ID Filter');
        $shipToInvoiceDistributionList = BooleanField::new('ship_to_invoice_distribution_list', 'Ship To Invoice Distribution List');
        $paymentPlanImportSettings = CodeEditorField::new('payment_plan_import_settings', 'Payment Plan Read Settings')
            ->setNumOfRows(3)
            ->setLanguage('js');
        $readCursor = DateTimeField::new('read_cursor', 'Read Cursor');
        $tenant = AssociationField::new('tenant');
        $tenantId = IntegerField::new('tenant_id');
        $readBatchSize = IntegerField::new('read_batch_size');
        $panelSyncSettings = FormField::addPanel('Sync Settings');
        $panelReadMapping = FormField::addPanel('Read Mapping');
        $panelReadFilters = FormField::addPanel('Read Filters');
        $panelWriteMapping = FormField::addPanel('Write Mapping');

        if (Crud::PAGE_INDEX === $pageName) {
            return [$tenantId, $tenant, $createdAt];
        } elseif (Crud::PAGE_DETAIL === $pageName) {
            return [$panelSyncSettings, $integrationVersion, $tenant, $lastSynced, $createdAt, $updatedAt, $readCustomers, $writeCustomers, $customerTopLevel, $readInvoices, $writeInvoices, $writeToOrderEntry, $readArAdjustments, $readCreditNotes, $writeCreditNotes, $readPayments, $writePayments, $panelReadMapping, $customerImportType, $invoiceTypes, $invoiceImportMapping, $lineItemImportMapping, $paymentPlanImportSettings, $shipToInvoiceDistributionList, $panelReadFilters, $readCursor, $customerQueryAddon, $arAdjustmentQueryAddon, $invoiceImportQueryAddon, $invoiceLocationIdFilter, $invoiceStartDate, $readBatchSize, $panelWriteMapping, $customerCustomFieldMapping, $invoiceCustomFieldMapping, $lineItemCustomFieldMapping, $paymentAccounts, $itemAccount, $itemLocationId, $itemDepartmentId, $mapCatalogItemToItemId];
        } elseif (Crud::PAGE_EDIT === $pageName) {
            return [$panelSyncSettings, $readCustomers, $writeCustomers, $customerTopLevel, $readInvoices, $writeInvoices, $writeToOrderEntry, $readArAdjustments, $readCreditNotes, $writeCreditNotes, $readPayments, $writePayments, $panelReadMapping, $customerImportType, $invoiceTypes, $invoiceImportMapping, $lineItemImportMapping, $paymentPlanImportSettings, $shipToInvoiceDistributionList, $panelReadFilters, $readCursor, $customerQueryAddon, $arAdjustmentQueryAddon, $invoiceImportQueryAddon, $invoiceLocationIdFilter, $invoiceStartDate, $readBatchSize, $panelWriteMapping, $customerCustomFieldMapping, $invoiceCustomFieldMapping, $lineItemCustomFieldMapping, $paymentAccounts, $itemAccount, $itemLocationId, $itemDepartmentId, $mapCatalogItemToItemId];
        }

        return [];
    }
}
