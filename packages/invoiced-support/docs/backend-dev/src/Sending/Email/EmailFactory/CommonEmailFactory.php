<?php

namespace App\Sending\Email\EmailFactory;

use App\AccountsPayable\Models\Vendor;
use App\AccountsReceivable\Models\Contact;
use App\AccountsReceivable\Models\Customer;
use App\Companies\Models\Company;
use App\Companies\Models\Member;
use App\Core\Files\Models\File;
use App\Core\Orm\ACLModelRequester;
use App\Core\Utils\Enums\ObjectType;
use App\Network\Models\NetworkConnection;
use App\Sending\Email\Exceptions\SendEmailException;
use App\Sending\Email\Models\EmailThread;
use App\Sending\Email\Models\Inbox;
use App\Sending\Email\ValueObjects\Email;
use App\Sending\Email\ValueObjects\EmailAttachment;
use App\Sending\Email\ValueObjects\NamedAddress;

class CommonEmailFactory extends AbstractEmailFactory
{
    /**
     * @throws SendEmailException
     */
    public function make(Inbox $inbox, array $to, array $cc, array $bcc, string $subject, string $message, string $status, ?EmailThread $thread, ?int $replyToId, ?ObjectType $relatedToType, ?int $relatedToId, array $attachments): Email
    {
        $email = new Email();
        $company = $inbox->tenant();

        $headers = [];
        if ($replyToId) {
            $email->setReplyToEmailId($replyToId);
        }

        foreach ($to as &$value) {
            $value['email'] = trim($value['email_address'] ?? '');
            $value['name'] = trim($value['name'] ?? '');
        }

        foreach ($cc as &$value) {
            $value['email'] = trim($value['email_address'] ?? '');
            $value['name'] = trim($value['name'] ?? '');
        }

        foreach ($bcc as &$value) {
            $value['email'] = trim($value['email_address'] ?? '');
            $value['name'] = trim($value['name'] ?? '');
        }

        $to = $this->generateTo($to, $company);

        if ($thread) {
            $references = [];
            $inReplyToId = null;
            foreach ($thread->emails as $inboxEmail) {
                $references[] = $inboxEmail->message_id;
                if (!$inReplyToId) {
                    $inReplyToId = $inboxEmail->message_id;
                }
            }
            $headers['References'] = implode(' ', $references);
            if ($inReplyToId) {
                $headers['In-Reply-To'] = $inReplyToId;
            }
        } else {
            $thread = new EmailThread();
            $thread->inbox = $inbox;
            $thread->name = $subject;
            $thread->status = $status;
            $thread->related_to_type = $relatedToType;
            $thread->related_to_id = $relatedToId;

            // attempt to associate a customer with the thread
            if ($customer = $this->determineCustomer($to)) {
                $thread->customer = $customer;
            }

            // attempt to associate a vendor with the thread
            if ($customer = $this->determineVendor($to)) {
                $thread->vendor = $customer;
            }

            $thread->saveOrFail();
        }

        $emailAttachments = [];
        $filesArray = [];
        if ($attachments) {
            $in = implode(',', array_map(fn ($attachment) => $attachment['id'], $attachments));
            $files = File::where("id IN ($in)")->all();
            foreach ($files as $file) {
                if ($content = $file->getContent()) {
                    $filesArray[] = $file;
                    $emailAttachments[] = new EmailAttachment($file->name, $file->type, $content);
                }
            }
        }

        $requester = ACLModelRequester::get();
        if ($requester instanceof Member) {
            $email->sentBy($requester->user());
        }

        $email->emailThread($thread)
            ->setFiles($filesArray)
            ->company($company)
            ->from(new NamedAddress((string) $company->email, $company->getDisplayName()))
            ->to($to)
            ->cc($this->generateCc($email->getTo(), $cc))
            ->bcc($this->generateCc(array_merge($email->getTo(), $email->getCc()), $bcc))
            ->subject($subject)
            ->plainText($message)
            ->attachments($emailAttachments);

        $headers = array_merge(
            $this->generateHeaders($company, $email->getId(), $inbox),
            $headers
        );
        $email->headers($headers);

        return $email;
    }

    /**
     * Determines the customer associated with an email address. This
     * will only return a customer if there is an exact match. If there
     * are multiple matching customers then none will be selected.
     *
     * @param NamedAddress[] $to
     */
    private function determineCustomer(array $to): ?Customer
    {
        foreach ($to as $toItem) {
            // first check for a unique customer
            /** @var Customer[] $customers */
            $customers = Customer::where('email', $toItem->getAddress())->first(2);
            if (1 == count($customers)) {
                return $customers[0];
            }

            // then check for a unique contact
            /** @var Contact[] $contacts */
            $contacts = Contact::where('email', $toItem->getAddress())->first(2);
            if (1 == count($contacts)) {
                return $contacts[0]->customer;
            }
        }

        return null;
    }

    /**
     * Determines the vendor associated with an email address. This
     * will only return a vendor if there is an exact match. If there
     * are multiple matching vendors then none will be selected.
     *
     * @param NamedAddress[] $to
     */
    private function determineVendor(array $to): ?Vendor
    {
        foreach ($to as $toItem) {
            /** @var Vendor[] $vendors */
            $vendors = Vendor::join(NetworkConnection::class, 'network_connection_id', 'id')
                ->join(Company::class, 'NetworkConnections.vendor_id', 'id')
                ->where('Companies.email', $toItem->getAddress())
                ->first(2);
            if (1 == count($vendors)) {
                return $vendors[0];
            }
        }

        return null;
    }
}
