<?php

namespace App\Integrations\Textract\Libs;

use App\Core\Files\Models\File;
use App\Core\Multitenant\TenantContext;
use App\Integrations\Textract\Enums\TextractProcessingStage;
use App\Integrations\Textract\Models\TextractImport;
use App\Integrations\Textract\ValueObjects\AnalyzedParameters;
use Aws\Sdk;
use Doctrine\DBAL\Connection;

class AIAnalyzer extends AbstractAnalyzer
{
    private const QUERIES = [
        'vendor' => 'What is vendor name',
        'number' => 'What is invoice number',
        'date' => 'What is invoice date',
        'total' => 'What is invoice total',
        'currency' => 'What is invoice currency',
        'address1' => 'What is vendor street address',
        'city' => 'What is vendor city',
        'state' => 'What is vendor state',
        'postal_code' => 'What is vendor zip code',
        'country' => 'What is vendor country',
        'item1_name' => 'What is 1st line item name',
        'item1_amount' => 'What is 1st line item amount',
        'item2_name' => 'What is 2nd line item name',
        'item2_amount' => 'What is 2nd line item amount',
        'item3_name' => 'What is 3rd line item name',
        'item3_amount' => 'What is 3rd line item amount',
        'item4_name' => 'What is 4th line item name',
        'item4_amount' => 'What is 4th line item amount',
        'item5_name' => 'What is 5th line item name',
        'item5_amount' => 'What is 5th line item amount',
        'item6_name' => 'What is 6th line item name',
        'item6_amount' => 'What is 6th line item amount',
        'item7_name' => 'What is 7th line item name',
        'item7_amount' => 'What is 7th line item amount',
        'item8_name' => 'What is 8th line item name',
        'item8_amount' => 'What is 8th line item amount',
        'item9_name' => 'What is 9th line item name',
        'item9_amount' => 'What is 9th line item amount',
        'item10_name' => 'What is 10th line item name',
        'item10_amount' => 'What is 10th line item amount',
    ];

    public function __construct(
        Connection $connection,
        TenantContext $tenantContext,
        string $bucketRegion,
        string $bucket,
        string $textractRole,
        string $textractTopic,
        Sdk $aws,
        private readonly string $adapterId,
        private readonly string $adapterVersion,
    ) {
        parent::__construct($connection, $tenantContext, $bucketRegion, $bucket, $textractRole, $textractTopic, $aws);
    }

    public function send(File $file): string
    {
        $this->statsd->increment('textract-document-analyze-received');
        $parameters = $this->getTextractParameters($file);
        $parameters['FeatureTypes'] = ['QUERIES', 'TABLES'];
        $parameters['QueriesConfig'] = [
            'Queries' => array_map(fn ($qry) => ['Text' => $qry], array_values(self::QUERIES)),
            'Adapters' => [[
                'AdapterId' => $this->adapterId,
                'Version' => $this->adapterVersion,
            ]],
        ];

        $result = $this->client->startDocumentAnalysis($parameters);

        return $result->get('JobId');
    }

    public function validate(TextractImport $job): bool
    {
        return $job->status->value < 3;
    }

    public function analyze(TextractImport $job): void
    {
        $parameters = $this->getInitialParameters($job);

        $result = $this->client->getDocumentAnalysis([
            'JobId' => $job->job_id,
        ]);

        $blocks = $result->get('Blocks');

        $reversedQueries = array_flip(self::QUERIES);
        $queryIds = [];

        foreach ($blocks as $block) {
            if ('QUERY' === $block['BlockType']) {
                if (isset($block['Relationships'])) {
                    $parametersKey = $reversedQueries[$block['Query']['Text']];
                    $answers = array_filter($block['Relationships'], fn ($item) => 'ANSWER' === $item['Type']);
                    foreach ($answers as $answer) {
                        foreach ($answer['Ids'] as $id) {
                            $queryIds[$id] = $parametersKey;
                        }
                    }
                }
            }
        }
        foreach ($blocks as $block) {
            if ('QUERY_RESULT' === $block['BlockType']) {
                $key = $queryIds[$block['Id']];
                if ('currency' === $key) {
                    $parameters[$key] = $block['Text'] && preg_match('/^[a-zA-Z]{3}$/', $block['Text']) ? $block['Text'] : ($parameters[$key] ?? null);
                } else {
                    $parameters[$key] = $block['Text'];
                }
            }
        }

        $params = new AnalyzedParameters(
            company: $job->tenant(),
            line_items: $this->buildLineItems($parameters),
            number: $parameters['number'] ?? null,
            date: $parameters['date'] ?? null,
            currency: $parameters['currency'] ?? null,
            total: isset($parameters['total']) ? $this->parsePrice($parameters['total']) : 0,
            vendor: $job->vendor?->name ?? $parameters['vendor'] ?? null,
            address1: $parameters['address1'] ?? null,
            city: $parameters['city'] ?? null,
            state: $parameters['state'] ?? null,
            postal_code: $parameters['postal_code'] ?? null,
            country: $parameters['country'] ?? null,
        );

        $this->finalize($job, $params);
    }

    private function buildLineItems(array $parameters): array
    {
        $backUp = (array) ($parameters['line_items'] ?? []);
        // remove uncategorized expense
        $lineItems = array_filter($backUp, fn ($item) => AnalyzedParameters::UNCATEGORIZED !== $item['description']);

        // array to match line items by value
        $lineItems = array_map(fn ($item) => $item['amount'], $lineItems);

        $resultLineItems = [];

        // parsing line items
        foreach ($parameters as $key => $value) {
            if (!str_starts_with($key, 'item')) {
                continue;
            }
            $lineItemIndex = preg_replace('/[^\d]/', '', $key);
            --$lineItemIndex;
            $lineItemKey = str_contains($key, 'name') ? 'description' : 'amount';
            if ('amount' === $lineItemKey) {
                $value = $this->parsePrice($value);
                $toDelete = array_search($value, $lineItems);
                if (false !== $toDelete) {
                    unset($lineItems[$toDelete]);
                }
            }
            if (!isset($resultLineItems[$lineItemIndex])) {
                $resultLineItems[$lineItemIndex] = [];
            }

            $resultLineItems[$lineItemIndex][$lineItemKey] = $value;
        }

        // no new line items found
        if (!$resultLineItems) {
            return $backUp;
        }

        // not all line items found from the list 1
        // we search backwards, to fill the array
        $backUp = array_reverse($backUp);
        foreach ($backUp as $item) {
            if (!$lineItems) {
                break;
            }
            $toAdd = array_search($item['amount'], $lineItems);
            if (false !== $toAdd) {
                unset($lineItems[$toAdd]);
                $resultLineItems[] = $item;
            }
        }

        return array_values($resultLineItems);
    }

    protected function getJobSuccessStatus(): TextractProcessingStage
    {
        return TextractProcessingStage::DocumentProcessed;
    }
}
