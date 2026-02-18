<?php

namespace App\Automations\Actions;

use App\Automations\Enums\AutomationResult;
use App\Automations\Exception\AutomationException;
use App\Automations\ValueObjects\AutomationContext;
use App\Automations\ValueObjects\AutomationOutcome;
use App\Automations\ValueObjects\ModifyPropertyValueActionSettings;
use App\Core\Utils\Enums\ObjectType;

class ModifyPropertyValueAction extends AbstractAutomationAction
{
    protected function getAction(): string
    {
        return 'ModifyPropertyValue';
    }

    public function perform(object $settings, AutomationContext $context): AutomationOutcome
    {
        $mapping = new ModifyPropertyValueActionSettings(
            $settings->object_type,
            $settings->name,
            $settings->value
        );
        $object = $mapping->getRelatedObject($context);
        if (!$object) {
            return new AutomationOutcome(AutomationResult::Failed, 'Object modification is not supported');
        }
        $fields = $mapping->getSubjectFields();
        $this->setValue($object, $fields, $mapping->name, $mapping->value);
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
            throw new AutomationException('Missing `name` property');
        }
        if (!isset($settings->value)) {
            throw new AutomationException('Missing `value` property');
        }

        $this->validate($sourceObject->typeName());

        $mapping = new ModifyPropertyValueActionSettings(
            $settings->object_type,
            $settings->name,
            $settings->value
        );

        $mapping->validate($sourceObject);

        return $mapping->serialize();
    }
}
