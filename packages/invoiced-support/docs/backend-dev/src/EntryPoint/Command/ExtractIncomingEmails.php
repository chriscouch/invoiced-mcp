<?php

namespace App\EntryPoint\Command;

use App\Companies\Models\Company;
use App\Core\Database\DatabaseHelper;
use App\Core\Files\Models\Attachment;
use App\Core\Multitenant\TenantContext;
use App\Core\S3ProxyFactory;
use App\Sending\Email\Interfaces\EmailBodyStorageInterface;
use App\Sending\Email\Models\InboxEmail;
use Doctrine\DBAL\Connection;
use EmailReplyParser\EmailReplyParser;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class ExtractIncomingEmails extends Command
{
    private const BUCKET_NAME = 'invoiced-remittance-advice';
    private const CHUNK_SIZE = 100;

    private mixed $s3;
    private OutputInterface $output;
    private int $fileIndex = 0;
    private array $jsonOutput;

    public function __construct(
        private Connection $database,
        private TenantContext $tenant,
        private EmailBodyStorageInterface $emailBodyStorage,
        S3ProxyFactory $s3Factory,
    ) {
        $this->s3 = $s3Factory->build();
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->setName('extract-incoming-emails')
            ->setDescription('Uploads metadata for all incoming emails to S3.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->output = $output;

        $data = DatabaseHelper::bigSelect($this->database, 'InboxEmails', 'incoming=1 and subject != "Delivery Status Notification (Failure)"');
        $this->startJson();
        $jsonRowIndex = 0;

        foreach ($data as $row) {
            $company = new Company(['id' => $row['tenant_id']]);
            $this->tenant->set($company);

            // Log all incoming emails
            $this->addEmailRow($row);
            ++$jsonRowIndex;

            // Chunk JSON file output
            if ($jsonRowIndex >= self::CHUNK_SIZE) {
                $this->closeJson();
                $this->startJson();
                $jsonRowIndex = 0;
            }
        }

        $this->closeJson();

        return 0;
    }

    private function startJson(): void
    {
        ++$this->fileIndex;
        $this->jsonOutput = [];
    }

    private function closeJson(): void
    {
        $newFilename = 'all-incoming-emails.'.$this->fileIndex.'.json';
        $this->output->writeln('Uploading '.$newFilename);
        $this->s3->putObject([
            'Bucket' => self::BUCKET_NAME,
            'Key' => $newFilename,
            'Content-Type' => 'application/json',
            'Body' => (string) json_encode($this->jsonOutput),
        ]);
    }

    private function addEmailRow(array $row): void
    {
        $email = new InboxEmail($row);
        $from = $email->from;
        $from = $from['email_address'] ?? '';

        // Exclude any emails sent via Invoiced
        if (str_ends_with($from, '@invoiced.com') || str_ends_with($from, 'invoicedmail.com')) {
            return;
        }

        // Get message body
        $html = $this->getHtml($email);
        $plainText = '';
        if (!$html) {
            $plainText = $this->getText($email);
        }

        // Load attachments list
        $attachments = Attachment::queryWithoutMultitenancyUnsafe()
            ->where('parent_type', 'email')
            ->where('parent_id', $row['id'])
            ->first(100);
        $attachmentNames = [];
        foreach ($attachments as $attachment) {
            $file = $attachment->file();

            if (str_starts_with($file->type, 'image/')) {
                continue;
            }

            $attachmentNames[] = $file->name;
        }

        $firstEmailInThread = !$row['reply_to_email_id'];

        $this->jsonOutput[] = [
            'id' => $row['id'],
            'subject' => $row['subject'],
            'from' => $from,
            'firstEmailInThread' => $firstEmailInThread ? '1' : '0',
            'attachments' => $attachmentNames,
            'html' => $html,
            'plainText' => $plainText,
        ];
    }

    private function getHtml(InboxEmail $email): string
    {
        $html = $this->emailBodyStorage->retrieve($email, EmailBodyStorageInterface::TYPE_HTML);

        return trim(EmailReplyParser::parseReply((string) $html));
    }

    private function getText(InboxEmail $email): string
    {
        $text = $this->emailBodyStorage->retrieve($email, EmailBodyStorageInterface::TYPE_PLAIN_TEXT);

        return trim(EmailReplyParser::parseReply((string) $text));
    }
}
