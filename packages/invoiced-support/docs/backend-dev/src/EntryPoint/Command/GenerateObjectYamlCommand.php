<?php

namespace App\EntryPoint\Command;

use App\Core\Orm\Property;
use App\Core\Utils\Enums\ObjectType;
use App\Core\Orm\Model;
use RuntimeException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Yaml\Yaml;

class GenerateObjectYamlCommand extends Command
{
    protected function configure(): void
    {
        $this
            ->setName('object:yaml')
            ->setDescription('Generates the YAML object configuration for a model')
            ->addArgument(
                'object',
                InputArgument::REQUIRED,
                'Object type to generate (i.e. customer or invoice)'
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $object = $input->getArgument('object');
        $modelClass = ObjectType::fromTypeName($object)->modelClass();

        /** @var Model $model */
        $model = new $modelClass();
        $properties = $model::definition()->all();
        $result = [
            'fields' => [],
        ];
        foreach ($properties as $property) {
            $addedFields = $this->getFields($model, $property);
            $result['fields'] = array_replace($result['fields'], $addedFields);
        }

        ksort($result['fields']);

        $output->writeln(Yaml::dump([$object => $result], 2, 4, Yaml::DUMP_OBJECT_AS_MAP));

        return 0;
    }

    private function getFields(Model $model, Property $property): array
    {
        $name = $property->name;

        // Special case for tenant_id column
        if ('tenant_id' == $name) {
            return [
                'company' => [
                    'name' => 'Company',
                    'type' => 'join',
                    'parent_column' => 'tenant_id',
                    'writable' => false,
                    'filterable' => false,
                ],
            ];
        }

        if ($join = $property->relation) {
            /** @var Model $joinModel */
            $joinModel = new $join();
            try {
                $joinObject = ObjectType::fromModel($joinModel)->typeName();
            } catch (RuntimeException) {
                // skip if model does not exist
                return [];
            }

            $ids = $joinModel::definition()->getIds();
            // compoosite keys currently not supported
            if (count($ids) > 1) {
                return [];
            }

            return [
                $name => [
                    'name' => $property->getTitle($model),
                    'type' => 'join',
                ],
            ];
        }

        // Special case for auto timestamp columns
        if (in_array($name, ['created_at', 'updated_at', 'deleted_at'])) {
            return [
                $name => [
                    'name' => $property->getTitle($model),
                    'type' => 'datetime',
                    'date_format' => 'Y-m-d H:i:s',
                    'writable' => false,
                ],
            ];
        }

        // Special case for ID columns
        if (in_array($name, $model::definition()->getIds())) {
            return [
                $name => [
                    'name' => 'id' == $name ? 'ID' : $property->getTitle($model),
                    'type' => 'string',
                    'writable' => false,
                ],
            ];
        }

        // Special case for enum columns
        if ('enum' == $property->type && $enumClass = $property->enum_class) {
            $values = [];

            if (method_exists($enumClass, 'cases')) {
                foreach ($enumClass::cases() as $case) {
                    // Some enums have a different case name, API value, and backed value.
                    // This handles that case where all 3 are different.
                    if (is_object($case) && method_exists($case, 'toString')) {
                        $caseValue = $case->toString();
                    } else {
                        $caseValue = $case->value;
                    }
                    $values[$caseValue] = $case->name;
                }
            }

            return [
                $name => [
                    'name' => $property->getTitle($model),
                    'type' => $property->type ?? 'string',
                    'values' => $values,
                ],
            ];
        }

        return [
            $name => [
                'name' => $property->getTitle($model),
                'type' => $property->type ?? 'string',
            ],
        ];
    }
}
