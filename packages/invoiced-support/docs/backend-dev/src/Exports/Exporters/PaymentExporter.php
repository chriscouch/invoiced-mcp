<?php

namespace App\Exports\Exporters;

use App\CashApplication\Enums\PaymentItemType;
use App\CashApplication\Models\Payment;
use App\Core\Orm\Model;
use App\Core\Orm\Query;
use App\Core\Utils\Enums\ObjectType;

/**
 * @extends AbstractCsvExporter<Payment>
 */
class PaymentExporter extends AbstractCsvExporter
{
    private const APPLIED_TO_COLUMNS = [
        'applied_to',
        'document_number',
        'credit_note',
        'applied_amount',
    ];

    private array $creditNoteNumbers = [];
    private array $estimateNumbers = [];
    private array $invoiceNumbers = [];

    protected function getQuery(array $options): Query
    {
        $listQueryBuilder = $this->listQueryFactory->get(Payment::class, $this->company, $options);
        $listQueryBuilder->setSort('date ASC');

        return $listQueryBuilder->getBuildQuery();
    }

    protected function getColumns(): array
    {
        $columns = [
            'customer.name',
            'customer.number',
            'date',
            'currency',
            'amount',
            'balance',
            'reference',
            'method',
            'source',
            'status',
            'notes',
        ];

        $metadataColumns = $this->getMetadataColumns(ObjectType::Payment);

        return array_merge(
            $columns,
            $metadataColumns,
            [
                'applied_to',
                'document_number',
                'credit_note',
                'applied_amount',
            ]
        );
    }

    protected function getCsvModelItems(Model $model): ?array
    {
        // Ensure the payment is always represented even if it has no line items
        return $model->applied_to ?: null;
    }

    protected function isLineItemColumn(string $column): bool
    {
        return in_array($column, self::APPLIED_TO_COLUMNS);
    }

    protected function getCsvColumnValue(Model $model, string $field): string
    {
        if ($model instanceof Payment) {
            // customer
            if (str_starts_with($field, 'customer.')) {
                $customer = $model->customer();
                $field = str_replace('customer.', '', $field);

                return $customer ? $this->getCsvColumnValue($customer, $field) : '';
            }

            // status
            if ('status' == $field) {
                if ($model->voided) {
                    return 'voided';
                } elseif (!$model->applied) {
                    return 'unapplied';
                }

                return 'applied';
            }
        }

        return parent::getCsvColumnValue($model, $field);
    }

    /**
     * @param array $item
     */
    protected function getCsvLineItemColumnValue(Model $model, string $field, mixed $item): string
    {
        if (!$item) {
            return '';
        }

        if ('applied_to' == $field) {
            return $item['type'];
        }

        if ('document_number' == $field) {
            if (PaymentItemType::AppliedCredit->value == $item['type'] || PaymentItemType::CreditNote->value == $item['type']) {
                $docType = $item['document_type'] ?? '';

                return $this->getDocumentNumber($docType, $item[$docType] ?? 0);
            } elseif (PaymentItemType::Estimate->value == $item['type']) {
                return $this->getEstimateNumber($item['estimate']);
            } elseif (PaymentItemType::Invoice->value == $item['type']) {
                return $this->getInvoiceNumber($item['invoice']);
            }

            return '';
        }

        if ('credit_note' == $field) {
            if (PaymentItemType::CreditNote->value == $item['type']) {
                return $this->getCreditNoteNumber($item['credit_note']);
            }

            return '';
        }

        if ('applied_amount' == $field) {
            return $item['amount'];
        }

        return '';
    }

    private function getDocumentNumber(string $type, int $id): string
    {
        if ($id <= 0) {
            return '';
        }

        if ('credit_note' == $type) {
            return $this->getCreditNoteNumber($id);
        } elseif ('estimate' == $type) {
            return $this->getEstimateNumber($id);
        }

        return $this->getInvoiceNumber($id);
    }

    /**
     * Gets the number of a credit note.
     */
    private function getCreditNoteNumber(int $id): string
    {
        if (!array_key_exists($id, $this->creditNoteNumbers)) {
            $this->creditNoteNumbers[$id] = $this->database->fetchOne('SELECT number FROM CreditNotes WHERE id=?', [$id]);
        }

        return $this->creditNoteNumbers[$id];
    }

    /**
     * Gets the number of an estimate.
     */
    private function getEstimateNumber(int $id): string
    {
        if (!array_key_exists($id, $this->estimateNumbers)) {
            $this->estimateNumbers[$id] = $this->database->fetchOne('SELECT number FROM Estimates WHERE id=?', [$id]);
        }

        return $this->estimateNumbers[$id];
    }

    /**
     * Gets the number of an invoice.
     */
    private function getInvoiceNumber(int $id): string
    {
        if (!array_key_exists($id, $this->invoiceNumbers)) {
            $this->invoiceNumbers[$id] = $this->database->fetchOne('SELECT number FROM Invoices WHERE id=?', [$id]);
        }

        return $this->invoiceNumbers[$id];
    }

    public static function getId(): string
    {
        return 'payment_csv';
    }
}
