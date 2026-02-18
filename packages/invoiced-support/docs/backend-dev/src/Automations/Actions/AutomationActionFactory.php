<?php

namespace App\Automations\Actions;

use App\Automations\Enums\AutomationActionType;
use App\Automations\Interfaces\AutomationActionInterface;
use Symfony\Component\DependencyInjection\ServiceLocator;

class AutomationActionFactory
{
    public function __construct(
        private ServiceLocator $actions
    ) {
    }

    public function make(AutomationActionType $type): AutomationActionInterface
    {
        return match ($type) {
            AutomationActionType::ModifyPropertyValue => $this->actions->get(ModifyPropertyValueAction::class),
            AutomationActionType::CreateObject => $this->actions->get(CreateObjectAction::class),
            AutomationActionType::CopyPropertyValue => $this->actions->get(CopyPropertyValueAction::class),
            AutomationActionType::ClearPropertyValue => $this->actions->get(ClearPropertyValueAction::class),
            AutomationActionType::DeleteObject => $this->actions->get(DeleteObjectAction::class),
            AutomationActionType::SendEmail => $this->actions->get(SendEmailAction::class),
            AutomationActionType::SendInternalNotification => $this->actions->get(SendInternalNotificationAction::class),
            AutomationActionType::Webhook => $this->actions->get(WebhookAction::class),
            AutomationActionType::Condition => $this->actions->get(ConditionAction::class),
            AutomationActionType::SendDocument => $this->actions->get(SendDocumentAction::class),
            AutomationActionType::PostToSlack => $this->actions->get(PostToSlackAction::class),
        };
    }
}
