<?php

namespace App\Automations\Api;

use App\Automations\AutomationConfiguration;
use App\Core\RestApi\Routes\AbstractApiRoute;
use App\Core\RestApi\ValueObjects\ApiCallContext;
use App\Core\RestApi\ValueObjects\ApiRouteDefinition;

class AutomationFieldsRoute extends AbstractApiRoute
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
        $configuration = AutomationConfiguration::get();
        foreach ($configuration->all() as $object => $data) {
            $fields = [];
            foreach ($data['fields'] as $fieldId => $field) {
                $fieldData = [
                    'id' => $fieldId,
                    'name' => $field['name'],
                    'type' => $field['type'],
                    'writable' => $field['writable'],
                ];
                if (isset($field['required'])) {
                    $fieldData['required'] = $field['required'];
                }
                if ('enum' == $field['type']) {
                    $fieldData['values'] = $field['values'];
                }
                if ('join' == $field['type']) {
                    $fieldData['join_object'] = $field['join_object'] ?? $fieldId;
                }
                $fields[] = $fieldData;
            }

            $result['fields'][$object] = [
                'object' => $object,
                'properties' => $fields,
                'triggers' => $data['triggers'],
                'actions' => $data['actions'],
                'associatedActionObjects' => $data['associatedActionObjects'] ?? [],
                'subjectActions' => $data['subjectActions'] ?? [],
            ];
        }

        return $result;
    }
}
