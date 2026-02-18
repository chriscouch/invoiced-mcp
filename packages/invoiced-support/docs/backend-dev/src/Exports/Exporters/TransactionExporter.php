<?php

namespace App\Exports\Exporters;

use App\CashApplication\Models\Transaction;
use App\Core\Orm\Model;
use App\Core\Orm\Query;
use App\Core\Utils\Enums\ObjectType;

/**
 * @extends AbstractCsvExporter<Transaction>
 */
class TransactionExporter extends AbstractCsvExporter
{
    private array $invoiceNumbers = [];

    protected function getQuery(array $options): Query
    {
        $listQueryBuilder = $this->listQueryFactory->get(Transaction::class, $this->company, $options);
        $listQueryBuilder->setSort('date ASC');

        return $listQueryBuilder->getBuildQuery();
    }

    protected function getColumns(): array
    {
        $columns = [
            'type',
            'customer.name',
            'customer.number',
            'invoice',
            'date',
            'currency',
            'amount',
            'method',
            'gateway',
            'gateway_id',
            'status',
            'notes',
        ];

        $metadataColumns = $this->getMetadataColumns(ObjectType::Transaction);

        return array_merge($columns, $metadataColumns);
    }

    protected function getCsvColumnValue(Model $model, string $field): string
    {
        if ($model instanceof Transaction) {
            // customer
            if (str_starts_with($field, 'customer.')) {
                $customer = $model->customer();
                $field = str_replace('customer.', '', $field);

                return $this->getCsvColumnValue($customer, $field);
            }

            if ('invoice' == $field && $invoice = $model->invoice) {
                return $this->getInvoiceNumber($invoice);
            }

            if ($field === 'notes') {
                $payment = $model->payment;
                if ($payment instanceof Model) {
                    return $this->getCsvColumnValue($payment, 'notes');
                }

                return '';
            }
        }

        return parent::getCsvColumnValue($model, $field);
    }

    /**
     * Gets a invoice's number.
     */
    protected function getInvoiceNumber(int $id): string
    {
        if (!array_key_exists($id, $this->invoiceNumbers)) {
            $this->invoiceNumbers[$id] = $this->database->fetchOne('SELECT number FROM Invoices WHERE id=?', [$id]);
        }

        return $this->invoiceNumbers[$id];
    }

    public static function getId(): string
    {
        return 'transaction_csv';
    }
}
