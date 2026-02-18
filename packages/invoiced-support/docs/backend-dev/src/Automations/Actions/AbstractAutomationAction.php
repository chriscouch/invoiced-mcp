<?php

namespace App\Automations\Actions;

use App\AccountsReceivable\Models\LineItem;
use App\Automations\AutomationConfiguration;
use App\Automations\Exception\AutomationException;
use App\Automations\Interfaces\AutomationActionInterface;
use App\Core\Multitenant\Models\MultitenantModel;
use App\Core\Utils\Enums\ObjectType;
use App\Integrations\AccountingSync\Enums\TransformFieldType;
use App\Integrations\AccountingSync\ReadSync\TransformerHelper;
use App\Integrations\AccountingSync\ValueObjects\TransformField;
use App\Metadata\Interfaces\MetadataModelInterface;
use App\Metadata\Models\CustomField;
use App\PaymentPlans\Models\PaymentPlan;
use App\PaymentPlans\Models\PaymentPlanInstallment;
use App\Core\Orm\Type;
use stdClass;

abstract class AbstractAutomationAction implements AutomationActionInterface
{
    abstract protected function getAction(): string;

    /**
     * Marshal value to the correct type.
     */
    public function marshalValue(string $type, mixed $value): mixed
    {
        $type = match ($type) {
            Type::ARRAY => TransformFieldType::Array,
            Type::BOOLEAN => TransformFieldType::Boolean,
            Type::DATE, Type::DATE_UNIX, Type::DATETIME => TransformFieldType::DateUnix,
            Type::FLOAT, Type::INTEGER => TransformFieldType::Float,
            default => TransformFieldType::String,
        };
        if (TransformFieldType::Array === $type && is_string($value)) {
            $value = json_decode($value, true);
        }

        return TransformerHelper::transformValue(new TransformField('', '', $type), $value);
    }

    protected function setValue(MultitenantModel $object, array $fields, string $fieldName, mixed $value): void
    {
        if (str_starts_with($fieldName, 'metadata.')) {
            $subProperty = substr($fieldName, 9);

            /** @var MetadataModelInterface $object */
            $metadata = $object->metadata;
            if (!is_object($metadata)) {
                $metadata = new stdClass();
            }
            if (null === $value) {
                unset($metadata->$subProperty);
                $object->metadata = $metadata;

                return;
            }

            $field = CustomField::find($subProperty);
            $customFieldType = $field?->type;
            $type = match ($customFieldType) {
                CustomField::FIELD_TYPE_BOOLEAN => TransformFieldType::Boolean,
                CustomField::FIELD_TYPE_DOUBLE, CustomField::FIELD_TYPE_INTEGER => TransformFieldType::Float,
                CustomField::FIELD_TYPE_DATE => TransformFieldType::DateUnix,
                default => TransformFieldType::String,
            };
            $value = TransformerHelper::transformValue(new TransformField($fieldName, $value, $type), $value);

            $metadata->$subProperty = $value;
            $object->metadata = $metadata;
        } elseif ('join' === $fields[$fieldName]['type']) {
            $this->saveRelation($value, $fields[$fieldName], $object, $fieldName);
        } else {
            if (null === $value && isset($fields[$fieldName]['setter'])) {
                $object->{$fields[$fieldName]['unSetter']}();

                return;
            }
            $value = $this->marshalValue($fields[$fieldName]['type'], $value);
            if (isset($fields[$fieldName]['setter'])) {
                $object->{$fields[$fieldName]['setter']}($value);

                return;
            }

            $object->$fieldName = $value;
        }
    }

    /**
     * @throws AutomationException
     */
    private function saveRelation(mixed $value, array $field, MultitenantModel $object, string $fieldName): void
    {
        if ($prop = $object::definition()->get($fieldName) ?? (isset($field['parent_column']) ? $object::definition()->get($field['parent_column']) : null)) {
            $relation = $prop->relation;
            if (is_numeric($value)) {
                if ($prop->local_key === $prop->name) {
                    $object->{$prop->name} = $value;
                } elseif ($relation) {
                    $object->{$prop->name} = $relation::find($value);
                }

                return;
            }

            if (null === $value) {
                $object->{$prop->name} = null;

                return;
            }

            $value = json_decode($value);
            if (is_object($value) && $relation) {
                $item = new $relation();
                if ($item instanceof PaymentPlan) {
                    $installments = [];
                    foreach ($value->installments as $inputInstallment) {
                        $installment = new PaymentPlanInstallment();
                        foreach ($inputInstallment as $key => $val) {
                            $installment->$key = $val;
                        }
                        $installments[] = $installment;
                    }
                    $item->installments = $installments;
                    if (method_exists($object, 'attachPaymentPlan')) {
                        if (!$object->attachPaymentPlan($item, false, true)) {
                            throw new AutomationException('Failed to attach payment plan: '.$object->getErrors());
                        }
                    } else {
                        $object->$fieldName = $item;
                    }
                } else {
                    foreach ((array) $value as $key => $val) {
                        $item->$key = $val;
                    }
                    $object->$fieldName = $item;
                }
            }

            return;
        }

        $value = json_decode($value);
        if (is_array($value) && $field['has_many']) {
            $result = [];
            foreach ($value as $items) {
                $item = new $field['has_many']();
                foreach ($items as $key => $val) {
                    $item->$key = $val;
                }
                $result[] = $item;
            }
            // string comparation is not reliable here, so we compare class
            if ((new $field['has_many']() instanceof LineItem) && method_exists($object, 'setLineItems')) {
                $object->setLineItems($result);
            }
        }
    }

    /**
     * @throws AutomationException
     */
    protected function validate(string $subject, ?ObjectType $object = null): void
    {
        $automations = AutomationConfiguration::get()->all();

        if (!isset($automations[$subject])) {
            throw new AutomationException('Invalid target object');
        }

        if (!in_array($this->getAction(), $automations[$subject]['actions'])) {
            throw new AutomationException('Invalid action');
        }

        $object = $object?->typeName();
        if ($object && $object !== $subject && (!isset($automations[$object]['associatedActionObjects']) || !in_array($subject, $automations[$object]['associatedActionObjects']))) {
            throw new AutomationException('Target object is not supported for source object');
        }
    }

    public function validateSettings(object $settings, ObjectType $sourceObject): object
    {
        return (object) [];
    }
}
