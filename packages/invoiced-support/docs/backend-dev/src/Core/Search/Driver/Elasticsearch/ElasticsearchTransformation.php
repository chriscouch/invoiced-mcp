<?php

namespace App\Core\Search\Driver\Elasticsearch;

use App\Companies\Models\Company;
use DateTime;

class ElasticsearchTransformation
{
    const DATE_FORMAT = 'Y-m-d';

    private static array $dateProperties = [
        'date',
        'due_date',
        'next_payment_attempt',
    ];

    public static function intoIndex(Company $company, array $document): array
    {
        $document['_tenantId'] = $company->id();

        // Convert date properties to the Elasticsearch format.
        foreach (self::$dateProperties as $key) {
            if (isset($document[$key])) {
                $document[$key] = date(self::DATE_FORMAT, $document[$key]);
            }
        }

        // Convert metadata properties into an array of values.
        // This is done because we do not have the flattened data
        // type, which requires the X-Pack plugin. X-Pack is not
        // available on AWS Elasticsearch.
        // In this process only the values are indexed and searchable.
        // The object type is not used because it would create an
        // explosion of mappings for new metadata field ID.
        if (isset($document['metadata'])) {
            // Only index string values since numbers and booleans
            // would not be great to search.
            $filtered = [];
            foreach ($document['metadata'] as $value) {
                if (is_string($value)) {
                    $filtered[] = $value;
                }
            }
            $document['metadata'] = $filtered;
        }

        return $document;
    }

    public static function fromIndex(array $hit): array
    {
        $document = $hit['_source'];
        $document['object'] = ElasticsearchIndexConfiguration::normalizeIndexName($hit['_index']);
        $document['id'] = $hit['_id'];
        unset($document['_tenantId']);

        // Convert date properties back to UNIX timestamps
        foreach (self::$dateProperties as $key) {
            if (isset($document[$key]) && $date = DateTime::createFromFormat(self::DATE_FORMAT, $document[$key])) {
                $document[$key] = (int) $date->format('U');
            }
        }

        return $document;
    }
}
