<?php

namespace App\Automations\Actions;

use App\Automations\Enums\AutomationResult;
use App\Automations\Exception\AutomationException;
use App\Automations\ValueObjects\AutomationContext;
use App\Automations\ValueObjects\AutomationOutcome;
use App\Automations\ValueObjects\ClearPropertyValueActionSettings;
use App\Core\Utils\Enums\ObjectType;

class ClearPropertyValueAction extends AbstractAutomationAction
{
    protected function getAction(): string
    {
        return 'ClearPropertyValue';
    }

    public function perform(object $settings, AutomationContext $context): AutomationOutcome
    {
        $mapping = new ClearPropertyValueActionSettings($settings->object_type, $settings->name);
        $object = $mapping->getRelatedObject($context);
        if (!$object) {
            return new AutomationOutcome(AutomationResult::Failed, 'Object modification is not supported');
        }
        $fields = $mapping->getSubjectFields();
        $this->setValue($object, $fields, $mapping->name, null);

        if (!$object->save()) {
            return new AutomationOutcome(AutomationResult::Failed, 'Could not save '.$context->objectType->typeName().': '.$object->getErrors());
        }

        return new AutomationOutcome(AutomationResult::Succeeded);
    }

    public function validateSettings(object $settings, ObjectType $sourceObject): object
    {
        $this->validate($sourceObject->typeName());
        if (!isset($settings->name)) {
            throw new AutomationException('Missing `name` parameter');
        }
        if (!isset($settings->object_type)) {
            throw new AutomationException('Missing target object');
        }

        $mapping = new ClearPropertyValueActionSettings($settings->object_type, $settings->name);
        $mapping->validate($sourceObject);

        return $mapping->serialize();
    }
}
