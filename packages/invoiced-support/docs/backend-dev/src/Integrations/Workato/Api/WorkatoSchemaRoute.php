<?php

namespace App\Integrations\Workato\Api;

use App\Core\Orm\Iterator;
use App\Core\RestApi\Routes\AbstractApiRoute;
use App\Core\RestApi\ValueObjects\ApiCallContext;
use App\Core\RestApi\ValueObjects\ApiRouteDefinition;
use App\Core\Utils\Enums\ObjectType;
use App\Imports\Libs\ImportConfiguration;
use App\Metadata\Models\CustomField;
use RuntimeException;

class WorkatoSchemaRoute extends AbstractApiRoute
{
    public function getDefinition(): ApiRouteDefinition
    {
        return new ApiRouteDefinition(
            queryParameters: null,
            requestParameters: [],
            requiredPermissions: [],
        );
    }

    public function buildResponse(ApiCallContext $context): mixed
    {
        $object = $context->request->attributes->get('object');

        $config = ImportConfiguration::get();
        $fields = [];

        try {
            $type = ObjectType::fromTypeName($object);
        } catch (RuntimeException) {
            return [];
        }

        $definition = $type->modelClass()::definition();

        foreach ($config->getFields($object) as $name => $field) {
            unset($field['writable']);
            unset($field['summarize']);
            unset($field['filterable']);
            unset($field['aliases']);
            $field['name'] = $name;

            $item = $definition->get($name);
            if ($item?->required) {
                $field['optional'] = false;
            }
            if ($item?->default) {
                $field['default'] = $item->default;
            }

            $fields[] = $this->getType($field, $field);
        }

        usort($fields, fn (array $a, array $b) => $a['name'] <=> $b['name']);

        /** @var Iterator|CustomField[] $customFields */
        $customFields = CustomField::where('object', $object)->all();

        if ($customFields->count()) {
            $properties = [];
            foreach ($customFields as $field) {
                $schema = [
                    'name' => $field->name,
                ];
                $properties[] = $this->getType($field->toArray(), $schema);
            }

            $fields[] = [
                'name' => 'metadata',
                'type' => 'object',
                'properties' => $properties,
            ];
        }

        return $fields;
    }

    private function getType(array $field, array $schema): array
    {
        $type = $field['type'];

        if (in_array($type, ['string', 'integer', 'date'])) {
            $schema['type'] = 'string';

            return $schema;
        }
        if ('boolean' === $type) {
            $schema['control_type'] = 'checkbox';
            unset($schema['type']);

            return $schema;
        }
        if ('double' === $type) {
            $schema['type'] = 'number';

            return $schema;
        }
        if ('enum' === $type) {
            $values = [];
            if (isset($field['values'])) {
                foreach ($field['values'] as $key => $value) {
                    $values[] = [$value, $key];
                }
            } elseif (isset($field['choices'])) {
                $values = array_map(fn ($value) => [$value, $value], $field['choices']);
            }

            if (count($values) > 0) {
                $schema['control_type'] = 'select';
                $schema['pick_list'] = $values;
                unset($schema['type'], $schema['values'], $schema['choices']);

                return $schema;
            }
        }

        // for value like money
        $schema['type'] = 'string';

        return $schema;
    }
}
