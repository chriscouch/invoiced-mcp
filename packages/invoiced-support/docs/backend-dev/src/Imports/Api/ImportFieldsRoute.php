<?php

namespace App\Imports\Api;

use App\Core\RestApi\Routes\AbstractApiRoute;
use App\Core\RestApi\ValueObjects\ApiCallContext;
use App\Core\RestApi\ValueObjects\ApiRouteDefinition;
use App\Imports\Libs\ImportConfiguration;

class ImportFieldsRoute extends AbstractApiRoute
{
    public function getDefinition(): ApiRouteDefinition
    {
        return new ApiRouteDefinition(
            queryParameters: [],
            requestParameters: [],
            requiredPermissions: [],
        );
    }

    public function buildResponse(ApiCallContext $context): array
    {
        $result = ['fields' => []];
        $reportConfiguration = ImportConfiguration::get();
        foreach ($reportConfiguration->all() as $type => $data) {
            $fields = [];
            foreach ($data['fields'] as $id => $field) {
                $field['id'] = $id;
                unset($field['writable']);
                unset($field['summarize']);
                unset($field['values']);
                unset($field['filterable']);
                $fields[] = $field;
            }

            usort($fields, fn (array $a, array $b) => $a['name'] <=> $b['name']);
            $entry = [
                'type' => $type,
                'name' => $data['name'],
                'properties' => $fields,
                'operations' => $data['supportedOperations'],
            ];

            if (isset($data['customFieldType'])) {
                $entry['customFieldType'] = $data['customFieldType'];
            }
            if (isset($data['hasLineItemCustomFields'])) {
                $entry['hasLineItemCustomFields'] = $data['hasLineItemCustomFields'];
            }

            $result['fields'][] = $entry;
        }

        return $result;
    }
}
