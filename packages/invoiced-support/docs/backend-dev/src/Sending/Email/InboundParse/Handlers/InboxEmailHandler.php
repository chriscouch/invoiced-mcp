<?php

namespace App\Sending\Email\InboundParse\Handlers;

use App\AccountsReceivable\Models\Customer;
use App\Companies\Models\Company;
use App\Core\Authentication\Libs\UserContext;
use App\Core\Authentication\Models\User;
use App\Core\Files\Exception\UploadException;
use App\Core\Files\Libs\AttachmentUploader;
use App\Core\Multitenant\TenantContext;
use App\Core\Orm\ACLModelRequester;
use App\Core\Orm\Exception\ModelException;
use App\Core\Statsd\Interfaces\StatsdAwareInterface;
use App\Core\Statsd\StatsdAwareTrait;
use App\Core\Utils\InfuseUtility as Utility;
use App\Notifications\Enums\NotificationEventType;
use App\Notifications\Libs\NotificationSpool;
use App\Sending\Email\Exceptions\InboundParseException;
use App\Sending\Email\Exceptions\SendEmailException;
use App\Sending\Email\InboundParse\Router;
use App\Sending\Email\Interfaces\EmailBodyStorageInterface;
use App\Sending\Email\Models\EmailParticipant;
use App\Sending\Email\Models\EmailThread;
use App\Sending\Email\Models\Inbox;
use App\Sending\Email\Models\InboxEmail;
use App\Sending\Email\ValueObjects\NamedAddress;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;
use PhpMimeMailParser\Parser;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Symfony\Component\HttpFoundation\Request;

/**
 * This email handler takes an email sent to the company inbox and saves it as an InboxEmail.
 */
class InboxEmailHandler implements HandlerInterface, LoggerAwareInterface, StatsdAwareInterface
{
    use LoggerAwareTrait;
    use StatsdAwareTrait;

    private Inbox $inbox;

    public function __construct(
        private UserContext $userContext,
        private TenantContext $tenant,
        private AttachmentUploader $attachmentUploader,
        private EmailBodyStorageInterface $emailBodyStorage,
        private Connection $database,
        private string $projectDir,
        private NotificationSpool $notificationSpool,
        private string $inboundEmailDomain,
    ) {
    }

    public function setInbox(Inbox $inbox): void
    {
        $this->inbox = $inbox;
    }

    public function getInbox(): Inbox
    {
        return $this->inbox;
    }

    public function supports(string $to): bool
    {
        // This handles invoice imports in the format:
        // {inbox-id}@$this->inboundEmailDomain
        $parsed = $this->parsePossibleInboxEmail($to);
        if (!$parsed) {
            return false;
        }

        $inboxId = $parsed[1];
        $inbox = Inbox::queryWithoutMultitenancyUnsafe()
            ->where('external_id', $inboxId)
            ->oneOrNull();

        if ($inbox instanceof Inbox) {
            $this->setInbox($inbox);

            return true;
        }

        return false;
    }

    public function processEmail(Request $request): void
    {
        // IMPORTANT: set the current tenant to enable multitenant operations
        /** @var Company $company */
        $company = $this->inbox->tenant();
        $this->tenant->set($company);
        ACLModelRequester::set($company);

        // try to find the user this is from
        $from = Router::getAddressRfc822((string) $request->request->get('from'));
        $user = User::where('email', $from)->oneOrNull();
        if (!$user) {
            $user = new User(['id' => User::INVOICED_USER]);
        }
        $this->userContext->set($user);

        // collect the attachments
        $files = [];
        if ($request->request->has('attachment-info')) {
            $tempDir = $this->projectDir.'/var/uploads';
            if (!is_dir($tempDir)) {
                @mkdir($tempDir, 0774);
            }
            $attachmentInfo = json_decode((string) $request->request->get('attachment-info'));

            foreach ($attachmentInfo as $key => $entry) {
                $file = $request->files->get($key);
                $tmpName = Utility::guid(false);
                $newFile = $file->move($tempDir, $tmpName);
                $files[$file->getClientOriginalName()] = $newFile;
            }
        }

        try {
            $this->saveEmail($request, $files);
        } catch (ModelException|Exception) {
            throw new InboundParseException('There was an error saving the email.');
        }
    }

    /**
     * Saves the email as an InboxEmail and associates it with a thread. Also creates the associated participant
     * and attachment objects.
     *
     * @throws InboundParseException
     * @throws ModelException
     * @throws Exception
     */
    private function saveEmail(Request $request, array $files): void
    {
        // process the files
        $fileObjects = [];
        foreach ($files as $filename => $file) {
            try {
                $fileObjects[] = $this->attachmentUploader->upload($file->getPathname(), $filename);
            } catch (UploadException $e) {
                throw new InboundParseException($e->getMessage(), $e->getCode(), $e);
            }
        }

        $headers = (string) $request->request->get('headers');
        $parser = new Parser();
        $parser->setText($headers);

        // save the email
        $email = new InboxEmail();
        $email->incoming = true;
        $email->subject = (string) $request->request->get('subject');
        $email->message_id = (string) $parser->getHeader('Message-ID');

        // associate email with thread
        if ($replyToEmail = $this->getReplyToEmail($parser)) {
            $email->reply_to_email = $replyToEmail;

            // reopen the thread if it was not open previously
            $thread = $replyToEmail->thread;
            if (EmailThread::STATUS_PENDING === $thread->status || EmailThread::STATUS_CLOSED === $thread->status) {
                $thread->status = EmailThread::STATUS_OPEN;
                $thread->save();
            }
            $email->thread = $thread;
        } else {
            $email->thread = $this->createThread($request);
        }
        $email->saveOrFail();

        $this->statsd->increment('inbound_parse.inbox_email');

        // notify the recipient of the email
        $this->notificationSpool->spool(NotificationEventType::EmailReceived, $email->tenant_id, $email->id, $email->thread->customer_id);

        // save participant associations
        $this->saveEmailParticipants($request, (int) $email->id());

        // save the body text in S3
        if ($emailText = (string) $request->request->get('text')) {
            try {
                $this->emailBodyStorage->store($email, $emailText, EmailBodyStorageInterface::TYPE_PLAIN_TEXT);
            } catch (SendEmailException $e) {
                throw new InboundParseException($e->getMessage(), $e->getCode(), $e);
            }
        }

        // save the html text in S3
        if ($html = (string) $request->request->get('html')) {
            try {
                $this->emailBodyStorage->store($email, $html, EmailBodyStorageInterface::TYPE_HTML);
            } catch (SendEmailException $e) {
                throw new InboundParseException($e->getMessage(), $e->getCode(), $e);
            }
        }

        // save the header text in S3
        try {
            $this->emailBodyStorage->store($email, $headers, EmailBodyStorageInterface::TYPE_HEADER);
        } catch (SendEmailException $e) {
            throw new InboundParseException($e->getMessage(), $e->getCode(), $e);
        }

        // associate the files
        foreach ($fileObjects as $fileObject) {
            $this->attachmentUploader->attachToObject($email, $fileObject);
        }
    }

    /**
     * Searches the email we reply to.
     */
    private function getReplyToEmail(Parser $parser): ?InboxEmail
    {
        $references = (string) $parser->getHeader('References');
        $messageIds = array_map('trim', explode(' ', $references));
        $replyTo = (string) $parser->getHeader('In-Reply-To');
        $messageIds[] = trim($replyTo);
        $messageIds = array_filter($messageIds);

        if (0 == count($messageIds)) {
            return null;
        }

        $ids = array_map(fn ($reference) => $this->database->quote($reference), $messageIds);
        /** @var InboxEmail|null $email */
        $email = InboxEmail::where('message_id IN ('.implode(',', $ids).')')
            ->sort('date DESC')
            ->oneOrNull();

        return $email;
    }

    /**
     * Associates an InboxEmail with an EmailThread by creating a new one.
     *
     * @return EmailThread The email with a thread ID
     */
    private function createThread(Request $request): EmailThread
    {
        $thread = new EmailThread();
        $thread->name = (string) $request->request->get('subject');
        $thread->inbox = $this->inbox;

        $from = Router::getAddressRfc822((string) $request->request->get('from'));
        if ($customers = Customer::where('email', $from)->first(2)) {
            if (1 === count($customers)) {
                $thread->customer = $customers[0];
            }
        }

        return $thread;
    }

    /**
     * Save the email participants and associates them with the email.
     *
     * @param Request $request The email request object
     * @param int     $emailId The email ID
     *
     * @throws Exception
     * @throws ModelException
     */
    private function saveEmailParticipants(Request $request, int $emailId): void
    {
        $associations = [];
        $rfc822Association = Router::getItemRfc822((string) $request->request->get('from'));
        foreach ($rfc822Association as $participant) {
            $associations[] = $this->saveEmailParticipant(
                $emailId,
                EmailParticipant::FROM,
                $participant
            );
        }

        $to = Router::getItemRfc822((string) $request->request->get('to'));
        foreach ($to as $p) {
            $associations[] = $this->saveEmailParticipant($emailId, EmailParticipant::TO, $p);
        }

        if ($cc = (string) $request->request->get('cc')) {
            $cc = Router::getItemRfc822($cc);
            foreach ($cc as $p) {
                $associations[] = $this->saveEmailParticipant($emailId, EmailParticipant::CC, $p);
            }
        }

        // save the participant associations
        foreach ($associations as $association) {
            $this->database->insert('EmailParticipantAssociations', $association);
        }
    }

    /**
     * Saves an email participant and creates an association structure.
     *
     * @param int    $emailId The email ID
     * @param string $type    The type of the participant
     *
     * @return array The association structure containing `email_id`, `participant_id`, and `type`
     */
    private function saveEmailParticipant(int $emailId, string $type, NamedAddress $participant): array
    {
        $emailParticipant = EmailParticipant::getOrCreate(
            $this->tenant->get(),
            $participant->getAddress(),
            $participant->getName() ?? ''
        );

        return [
            'email_id' => $emailId,
            'participant_id' => $emailParticipant->id(),
            'type' => $type,
        ];
    }

    private function parsePossibleInboxEmail(string $to): ?array
    {
        // Figure out which invoice the message is associated with...
        // The format is: "inboxId"
        if (!preg_match('/^([A-Za-z0-9]+)\@'.str_replace('.', '\\.', $this->inboundEmailDomain).'$/', $to, $matches)) {
            return null;
        }

        return $matches;
    }
}
