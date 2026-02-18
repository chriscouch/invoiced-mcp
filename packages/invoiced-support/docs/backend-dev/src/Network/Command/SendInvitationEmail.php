<?php

namespace App\Network\Command;

use App\Companies\Models\Company;
use App\Core\Mailer\Mailer;
use App\Core\Multitenant\TenantContext;
use App\Network\Models\NetworkInvitation;
use App\Sending\Email\Models\InboxDecorator;

class SendInvitationEmail
{
    public function __construct(
        private Mailer $mailer,
        private TenantContext $tenant,
        private string $inboundEmailDomain,
    ) {
    }

    public function sendNetworkInvitation(NetworkInvitation $invitation): void
    {
        $message = [
            'from_name' => $invitation->from_company->name,
            'reply_to_name' => $invitation->from_company->name,
            'reply_to_email' => $this->getReplyTo($invitation->from_company, $invitation->is_customer),
            'subject' => $this->getSubject($invitation),
        ];

        $fromCompany = $invitation->from_company;
        $templateVars = [
            'invitation' => $invitation,
            'fromCompanyName' => $fromCompany->name,
            'fromCompanyEmail' => $fromCompany->email,
            'isCustomer' => $invitation->is_customer,
            'invitationId' => $invitation->uuid,
            'expiresAt' => $invitation->expires_at->format($fromCompany->date_format),
            'address' => $fromCompany->address(false, false),
        ];

        if (!$invitation->email) {
            if (!$invitation->to_company) {
                return;
            }

            // Send the email to the administrators with a different tenant context
            $toCompany = $invitation->to_company;
            $this->tenant->runAs($toCompany, function () use ($toCompany, $message, $templateVars) {
                $this->mailer->sendToAdministrators($toCompany, $message, 'network-invitation', $templateVars);
            });
        } else {
            $message['to'] = [['email' => $invitation->email, 'name' => '']];
            $this->mailer->send($message, 'network-invitation', $templateVars);
        }
    }

    /**
     * Generates any message headers.
     */
    private function getReplyTo(Company $company, bool $isCustomer): string
    {
        // Set reply to A/R Inbox
        if ($isCustomer && $inbox = $company->accounts_receivable_settings->reply_to_inbox) {
            $decorator = new InboxDecorator($inbox, $this->inboundEmailDomain);

            return $decorator->getEmailAddress();
        }

        // Set reply to A/P Inbox
        if (!$isCustomer && $inbox = $company->accounts_payable_settings->inbox) {
            $decorator = new InboxDecorator($inbox, $this->inboundEmailDomain);

            return $decorator->getEmailAddress();
        }

        // Default to company email address as reply to
        return (string) $company->email;
    }

    private function getSubject(NetworkInvitation $invitation): string
    {
        if ($invitation->is_customer) {
            return 'Approval needed for a new vendor: '.$invitation->from_company->name;
        }

        return 'Approval needed for a new customer: '.$invitation->from_company->name;
    }
}
