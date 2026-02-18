<?php

namespace App\Integrations\Textract\Libs;

use App\Core\Files\Models\File;
use App\Integrations\Textract\Enums\TextractProcessingStage;
use App\Integrations\Textract\Models\TextractImport;
use App\Integrations\Textract\ValueObjects\AnalyzedParameters;

class ExpenseAnalyzer extends AbstractAnalyzer
{
    public function send(File $file): string
    {
        $this->statsd->increment('textract-expense-analyze-received');
        $result = $this->client->startExpenseAnalysis($this->getTextractParameters($file));

        return $result->get('JobId');
    }

    public function validate(TextractImport $job): bool
    {
        return 1 === $job->status->value;
    }

    public function analyze(TextractImport $job): void
    {
        $parameters = $this->getInitialParameters($job);

        $result = $this->client->getExpenseAnalysis([
            'JobId' => $job->job_id,
        ]);

        $pages = $result->get('ExpenseDocuments');

        $total = 0;

        foreach ($pages as $page) {
            $parameters = array_replace($parameters, $this->analyzeDocumentAsExpense($page['SummaryFields']));

            $total = isset($parameters['total']) ? $this->parsePrice($parameters['total']) : $total;

            // continue iteration while line item found
            if ($total) {
                break;
            }
        }

        foreach ($pages as $page) {
            $parameters['line_items'] = $this->buildLineItems($page['LineItemGroups']);
        }

        $params = new AnalyzedParameters(
            company: $job->tenant(),
            line_items: $parameters['line_items'],
            number: $parameters['number'] ?? null,
            date: $parameters['date'] ?? null,
            currency: $parameters['currency'] ?? null,
            total: $total,
            vendor: $vendor?->name ?? $parameters['VENDOR_NAME'] ?? $parameters['NAME'] ?? null,
            address1: $parameters['STREET'] ?? null,
            city: $parameters['CITY'] ?? null,
            state: $parameters['STATE'] ?? null,
            postal_code: $parameters['ZIP_CODE'] ?? null,
            country: $parameters['COUNTRY'] ?? null,
        );

        $this->finalize($job, $params);
    }

    private function analyzeDocumentAsExpense(array $summaryFields): array
    {
        $vendorRelatedFields = [
            'VENDOR_NAME' => null,
            'VENDOR_ADDRESS' => null,
            'VENDOR_PHONE' => null,
            'ADDRESS' => null,
            'NAME' => null,
            'ADDRESS_BLOCK' => null,
            'STREET' => null,
            'CITY' => null,
            'STATE' => null,
            'COUNTRY' => null,
            'ZIP_CODE' => null,
        ];

        $parameters = [];

        foreach ($summaryFields as $field) {
            $key = $field['Type']['Text'];
            $value = $field['ValueDetection']['Text'];
            switch ($key) {
                case 'INVOICE_RECEIPT_ID':
                    $parameters['number'] = $value;
                    break;
                case 'INVOICE_RECEIPT_DATE':
                    $parameters['date'] = $value;
                    break;
                case 'TOTAL':
                    $parameters['currency'] = $field['Currency']['Code'] ?? null;
                    $parameters['total'] = $value;
                    break;
                case 'VENDOR_NAME':
                    $parameters['VENDOR_NAME'] = $value;
                    break;
                default:
                    if (array_key_exists($key, $vendorRelatedFields) && isset($field['LabelDetection']) && in_array($field['LabelDetection']['Text'], ['Vendor', 'Remit To', 'Supplier'])) {
                        $parameters[$key] = $value;
                    }
            }
        }

        return $parameters;
    }

    private function buildLineItems(array $lineItemGroups): array
    {
        $lineItems = [];

        foreach ($lineItemGroups as $group) {
            foreach ($group['LineItems'] as $lineItemInput) {
                $lineItem = [];
                $fullDescription = '';
                foreach ($lineItemInput['LineItemExpenseFields'] as $item) {
                    $key = $item['Type']['Text'];
                    $value = $item['ValueDetection']['Text'];
                    if ('ITEM' === $key) {
                        $lineItem['description'] = $value;
                        continue;
                    }
                    if ('EXPENSE_ROW' === $key) {
                        $fullDescription = $value;
                        continue;
                    }
                    if ('PRICE' === $key) {
                        $lineItem['amount'] = $this->parsePrice($value);
                    }
                }

                $lineItem['description'] = $lineItem['description'] ?? $fullDescription;

                $lineItems[] = $lineItem;
            }
        }

        return $lineItems;
    }

    protected function getJobSuccessStatus(): TextractProcessingStage
    {
        return TextractProcessingStage::ExpenseProcessed;
    }
}
