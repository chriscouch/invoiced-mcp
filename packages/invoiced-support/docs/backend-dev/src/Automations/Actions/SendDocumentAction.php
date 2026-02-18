<?php

namespace App\Automations\Actions;

use App\AccountsReceivable\Models\Customer;
use App\AccountsReceivable\Models\Estimate;
use App\AccountsReceivable\Models\Invoice;
use App\Automations\Enums\AutomationResult;
use App\Automations\Exception\AutomationException;
use App\Automations\ValueObjects\AutomationContext;
use App\Automations\ValueObjects\AutomationOutcome;
use App\Automations\ValueObjects\SendDocumentActionSettings;
use App\Core\Utils\Enums\ObjectType;
use App\Sending\Email\EmailFactory\DocumentEmailFactory;
use App\Sending\Email\Exceptions\SendEmailException;
use App\Sending\Email\Libs\EmailSender;
use App\Sending\Email\Models\EmailTemplate;
use App\Statements\Libs\StatementBuilder;

class SendDocumentAction extends AbstractAutomationAction
{
    public function __construct(
        private readonly DocumentEmailFactory $factory,
        private readonly EmailSender $sender,
        private readonly StatementBuilder $builder
    ) {
    }

    public function perform(object $settings, AutomationContext $context): AutomationOutcome
    {
        $sourceObject = $context->sourceObject;
        // do not perform send for deleted documents
        if (!$sourceObject) {
            return new AutomationOutcome(AutomationResult::Failed, 'Could not save '.$context->objectType->typeName().': object deleted');
        }
        if (!$sourceObject instanceof Customer && !$sourceObject instanceof Invoice && !$sourceObject instanceof Estimate) {
            return new AutomationOutcome(AutomationResult::Failed, 'Invalid document');
        }
        $mappings = new SendDocumentActionSettings(
            $settings->template,
            $settings->type ?? null,
            $settings->period ?? null,
            $settings->openItemMode ?? null,
        );
        $document = $mappings->getSendableDocument($sourceObject, $this->builder);
        if (!$document) {
            return new AutomationOutcome(AutomationResult::Failed, 'Invalid document');
        }

        $emailTemplate = EmailTemplate::findOrFail([$sourceObject->tenant_id, $mappings->template]);

        try {
            $email = $this->factory->make(
                document: $document,
                emailTemplate: $emailTemplate,
                to: $document->getSendCustomer()->emailContacts(),
            );
        } catch (SendEmailException $e) {
            return new AutomationOutcome(AutomationResult::Failed, $e->getMessage());
        }
        $this->sender->send($email);

        return new AutomationOutcome(AutomationResult::Succeeded);
    }

    protected function getAction(): string
    {
        return 'SendDocument';
    }

    public function validateSettings(object $settings, ObjectType $sourceObject): object
    {
        if (!isset($settings->template) || !$settings->template) {
            throw new AutomationException('Missing `template`');
        }

        $this->validate($sourceObject->typeName());

        $mappings = new SendDocumentActionSettings(
            $settings->template,
            $settings->type ?? null,
            $settings->period ?? null,
            $settings->openItemMode ?? null,
        );
        $mappings->validate($sourceObject);

        return $mappings->serialize();
    }
}
