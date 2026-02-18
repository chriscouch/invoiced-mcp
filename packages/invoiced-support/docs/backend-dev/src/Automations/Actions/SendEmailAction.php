<?php

namespace App\Automations\Actions;

use App\Automations\Enums\AutomationResult;
use App\Automations\Exception\AutomationException;
use App\Automations\ValueObjects\AutomationContext;
use App\Automations\ValueObjects\AutomationOutcome;
use App\Automations\ValueObjects\SendEmailActionSettings;
use App\Core\Multitenant\TenantContext;
use App\Core\Utils\Enums\ObjectType;
use App\Sending\Email\EmailFactory\GenericEmailFactory;
use App\Sending\Email\Libs\EmailSender;
use Symfony\Contracts\Translation\TranslatorInterface;
use App\Sending\Email\ValueObjects\Email;

class SendEmailAction extends AbstractAutomationAction
{
    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly TranslatorInterface $translator,
        private readonly GenericEmailFactory $factory,
        private readonly EmailSender $sender)
    {
    }

    public function perform(object $settings, AutomationContext $context): AutomationOutcome
    {
        if (!isset($settings->to) || !isset($settings->subject) || !isset($settings->body)) {
            return new AutomationOutcome(AutomationResult::Failed, 'Missing required parameters');
        }
        $company = $this->tenantContext->get();
        $variables = $context->getVariables();
        $context = $context->getTwigContext($this->translator);

        $to = $this->factory->normalizeEmails($settings->to, $variables, $context);
        $cc = $this->factory->normalizeEmails($settings->cc ?? [], $variables, $context);
        $bcc = $this->factory->normalizeEmails($settings->bcc ?? [], $variables, $context);

        $result = $this->factory->make($company, $context, $variables, $to, $cc, $bcc, $settings->subject, $settings->body);
        if (!$result instanceof Email) {
            return new AutomationOutcome(AutomationResult::Failed, $result);
        }

        $this->sender->send($result);

        return new AutomationOutcome(AutomationResult::Succeeded);
    }

    protected function getAction(): string
    {
        return 'SendEmail';
    }

    public function validateSettings(object $settings, ObjectType $sourceObject): object
    {
        $this->validate($sourceObject->typeName());
        if (!isset($settings->subject) || !$settings->subject) {
            throw new AutomationException('Missing `subject`');
        }
        if (!isset($settings->body) || !$settings->body) {
            throw new AutomationException('Missing `body`');
        }

        if (!isset($settings->to) || !is_array($settings->to) || 0 === count($settings->to)) {
            throw new AutomationException('Missing `to` parameter');
        }

        $mapping = SendEmailActionSettings::fromSettings($settings);

        $mapping->validate($sourceObject);

        return $mapping->serialize();
    }
}
