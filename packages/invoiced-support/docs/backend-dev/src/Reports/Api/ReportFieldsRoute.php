<?php

namespace App\Reports\Api;

use App\Core\RestApi\Routes\AbstractApiRoute;
use App\Core\RestApi\ValueObjects\ApiCallContext;
use App\Core\RestApi\ValueObjects\ApiRouteDefinition;
use App\Reports\ReportBuilder\ReportConfiguration;

class ReportFieldsRoute extends AbstractApiRoute
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
        $reportConfiguration = ReportConfiguration::get();
        foreach ($reportConfiguration->all() as $object => $data) {
            $fields = [];
            foreach ($data['fields'] as $fieldId => $field) {
                $fieldData = [
                    'id' => $fieldId,
                    'name' => $field['name'],
                    'type' => $field['type'],
                ];
                if ('enum' == $field['type']) {
                    $fieldData['values'] = $field['values'];
                }
                if ('join' == $field['type']) {
                    $fieldData['join_object'] = $field['join_object'] ?? $fieldId;
                }
                $fields[] = $fieldData;
            }

            usort($fields, fn (array $a, array $b) => $a['name'] <=> $b['name']);
            $objectData = [
                'standalone' => $data['standalone'] ?? true,
                'object' => $object,
                'fields' => $fields,
            ];

            $result['fields'][$object] = $objectData;
        }

        return $result;
    }
}
