<?php

namespace App\Automations\Actions;

use App\Automations\Enums\AutomationResult;
use App\Automations\ValueObjects\AutomationContext;
use App\Automations\ValueObjects\AutomationOutcome;
use Throwable;

class DeleteObjectAction extends AbstractAutomationAction
{
    protected function getAction(): string
    {
        return 'DeleteObject';
    }

    public function perform(object $settings, AutomationContext $context): AutomationOutcome
    {
        $object = $context->sourceObject;
        if (!$object) {
            return new AutomationOutcome(AutomationResult::Failed, 'Could not save '.$context->objectType->typeName().': Already deleted');
        }
        try {
            method_exists($object, 'void') ? $object->void() : $object->deleteOrFail();
        } catch (Throwable) {
            return new AutomationOutcome(AutomationResult::Failed, 'Could not delete '.$context->objectType->typeName());
        }

        return new AutomationOutcome(AutomationResult::Succeeded);
    }
}
