<?php

namespace App\Automations\Actions;

use App\Automations\Enums\AutomationResult;
use App\Automations\Exception\AutomationException;
use App\Automations\ValueObjects\AutomationContext;
use App\Automations\ValueObjects\AutomationOutcome;
use App\Automations\ValueObjects\CopyPropertyValueActionSettings;
use App\Core\Utils\Enums\ObjectType;
use App\Metadata\Interfaces\MetadataModelInterface;

class CopyPropertyValueAction extends AbstractAutomationAction
{
    protected function getAction(): string
    {
        return 'CopyPropertyValue';
    }

    public function perform(object $settings, AutomationContext $context): AutomationOutcome
    {
        $mapping = new CopyPropertyValueActionSettings(
            $settings->object_type,
            $settings->name,
            $settings->value
        );
        $object = $mapping->getRelatedObject($context);
        if (!$object) {
            return new AutomationOutcome(AutomationResult::Failed, 'Object modification is not supported');
        }

        $value = $this->getPropertyValueFromObject($mapping->value, $context);

        $fields = $mapping->getSubjectFields();
        $this->setValue($object, $fields, $mapping->name, $value);

        if (!$object->save()) {
            return new AutomationOutcome(AutomationResult::Failed, 'Could not save '.$context->objectType->typeName().': '.$object->getErrors());
        }

        return new AutomationOutcome(AutomationResult::Succeeded);
    }

    public function validateSettings(object $settings, ObjectType $sourceObject): object
    {
        if (!isset($settings->object_type)) {
            throw new AutomationException('Missing target object');
        }

        if (!isset($settings->name)) {
            throw new AutomationException('Missing `name` parameter');
        }

        if (!isset($settings->value)) {
            throw new AutomationException('Missing `value` parameter');
        }
        $this->validate($settings->object_type, $sourceObject);

        $mapping = new CopyPropertyValueActionSettings(
            $settings->object_type,
            $settings->name,
            $settings->value
        );

        $mapping->validate($sourceObject);

        return $mapping->serialize();
    }

    private function getPropertyValueFromObject(string $fromProperty, AutomationContext $context): mixed
    {
        if (str_starts_with($fromProperty, 'metadata.') && $context->sourceObject instanceof MetadataModelInterface) {
            /** @var string $key */
            $key = str_replace('metadata.', '', $fromProperty);

            return $context->sourceObject->metadata->$key;
        }

        return $context->sourceObject->$fromProperty;
    }
}
