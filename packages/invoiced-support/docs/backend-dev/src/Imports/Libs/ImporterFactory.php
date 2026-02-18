<?php

namespace App\Imports\Libs;

use App\Imports\Importers\BankFeedTransactionBaiImporter;
use App\Imports\Importers\Spreadsheet\BillImporter;
use App\Imports\Importers\Spreadsheet\ContactImporter;
use App\Imports\Importers\Spreadsheet\CouponImporter;
use App\Imports\Importers\Spreadsheet\CreditBalanceAdjustmentImporter;
use App\Imports\Importers\Spreadsheet\CreditNoteImporter;
use App\Imports\Importers\Spreadsheet\CustomerImporter;
use App\Imports\Importers\Spreadsheet\EstimateImporter;
use App\Imports\Importers\Spreadsheet\InvoiceImporter;
use App\Imports\Importers\Spreadsheet\ItemImporter;
use App\Imports\Importers\Spreadsheet\PaymentImporter;
use App\Imports\Importers\Spreadsheet\PaymentSourceImporter;
use App\Imports\Importers\Spreadsheet\PaymentSourceRemapImporter;
use App\Imports\Importers\Spreadsheet\PendingLineItemImporter;
use App\Imports\Importers\Spreadsheet\PlanImporter;
use App\Imports\Importers\Spreadsheet\RemittanceAdviceImporter;
use App\Imports\Importers\Spreadsheet\SubscriptionImporter;
use App\Imports\Importers\Spreadsheet\TaxRateImporter;
use App\Imports\Importers\Spreadsheet\TransactionImporter;
use App\Imports\Importers\Spreadsheet\VendorCreditImporter;
use App\Imports\Importers\Spreadsheet\VendorImporter;
use App\Imports\Importers\Spreadsheet\VendorPaymentImporter;
use App\Imports\Interfaces\ImporterInterface;
use InvalidArgumentException;
use Symfony\Component\DependencyInjection\ServiceLocator;

class ImporterFactory
{
    public function __construct(private ServiceLocator $handlerLocator)
    {
    }

    /**
     * Gets the importer for this import.
     *
     * @throws InvalidArgumentException
     */
    public function get(string $type): ImporterInterface
    {
        $class = match ($type) {
            'bank_feed_transaction_bai' => BankFeedTransactionBaiImporter::class,
            'bill' => BillImporter::class,
            'catalog_item' => ItemImporter::class,
            'contact' => ContactImporter::class,
            'coupon' => CouponImporter::class,
            'credit_balance_adjustment' => CreditBalanceAdjustmentImporter::class,
            'credit_note' => CreditNoteImporter::class,
            'customer' => CustomerImporter::class,
            'estimate' => EstimateImporter::class,
            'invoice' => InvoiceImporter::class,
            'item' => ItemImporter::class,
            'payment' => PaymentImporter::class,
            'payment_source' => PaymentSourceImporter::class,
            'payment_source_remap' => PaymentSourceRemapImporter::class,
            'pending_line_item' => PendingLineItemImporter::class,
            'plan' => PlanImporter::class,
            'remittance_advice' => RemittanceAdviceImporter::class,
            'subscription' => SubscriptionImporter::class,
            'tax_rate' => TaxRateImporter::class,
            'transaction' => TransactionImporter::class,
            'vendor' => VendorImporter::class,
            'vendor_credit' => VendorCreditImporter::class,
            'vendor_payment' => VendorPaymentImporter::class,
            default => throw new InvalidArgumentException('Importer does not exist: '.$type),
        };

        return $this->handlerLocator->get($class);
    }
}
