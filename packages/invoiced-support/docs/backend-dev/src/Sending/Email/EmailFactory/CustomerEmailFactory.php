<?php

namespace App\Sending\Email\EmailFactory;

use App\AccountsReceivable\Models\Customer;
use App\Companies\Models\Company;
use App\Companies\Models\Member;
use App\Sending\Email\Exceptions\SendEmailException;
use App\Sending\Email\ValueObjects\Email;
use App\Sending\Email\ValueObjects\EmailAttachment;
use App\Sending\Email\ValueObjects\NamedAddress;
use App\Sending\Email\ValueObjects\TemplatedPart;
use App\Core\Orm\ACLModelRequester;

/**
 * This class creates email messages that are sent to
 * an end customer, on behalf of a business. It creates a
 * message from one of the available client email templates.
 */
class CustomerEmailFactory extends AbstractEmailFactory
{
    /**
     * @param EmailAttachment[] $attachments
     *
     * @throws SendEmailException
     */
    public function make(Customer $customer, string $template, array $templateVars = [], array $to = [], string $subject = '', array $attachments = []): Email
    {
        if (0 === count($to)) {
            throw new SendEmailException('No email recipients given. At least one recipient must be provided.');
        }

        $company = $customer->tenant();
        $company->useTimezone();
        $templateVars = $this->getTemplateVars($company, $templateVars);

        $email = new Email();

        $requester = ACLModelRequester::get();
        if ($requester instanceof Member) {
            $email->sentBy($requester->user());
        }

        return $email->company($company)
            ->from(new NamedAddress((string) $company->email, $company->getDisplayName()))
            ->to($this->generateTo($to, $company, $customer))
            ->headers($this->generateHeaders($company, $email->getId(), $company->accounts_receivable_settings->reply_to_inbox))
            ->subject($subject)
            ->html(new TemplatedPart('emails/client/'.$template.'.twig', $templateVars))
            ->plainText(new TemplatedPart('emails/client/text/'.$template.'.twig', $templateVars))
            ->attachments($attachments);
    }

    public function getTemplateVars(Company $company, array $templateVars): array
    {
        return array_replace($this->buildDefaultTemplateVars($company), $templateVars);
    }
}
