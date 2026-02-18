<?php

namespace App\EntryPoint\Command;

use App\CashApplication\Models\Payment;
use App\Companies\Models\Company;
use App\Core\Database\DatabaseHelper;
use App\Core\Files\Models\Attachment;
use App\Core\Files\Models\File;
use App\Core\Multitenant\TenantContext;
use App\Core\S3ProxyFactory;
use Doctrine\DBAL\Connection;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class ExtractCheckLockboxScansCommand extends Command
{
    private const BUCKET_NAME = 'invoiced-remittance-advice';

    private mixed $s3;
    private OutputInterface $output;

    public function __construct(
        private Connection $database,
        private TenantContext $tenant,
        S3ProxyFactory $s3Factory,
    ) {
        $this->s3 = $s3Factory->build();
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->setName('extract-check-lockbox-scans')
            ->setDescription('Uploads all email PDF attachments that could be remittance advice to S3.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->output = $output;

        $data = DatabaseHelper::bigSelect($this->database, 'Payments', 'source="'.Payment::SOURCE_CHECK_LOCKBOX.'"');

        foreach ($data as $row) {
            $company = new Company(['id' => $row['tenant_id']]);
            $this->tenant->set($company);

            $attachments = Attachment::queryWithoutMultitenancyUnsafe()
                ->where('parent_type', 'payment')
                ->where('parent_id', $row['id'])
                ->first(100);
            foreach ($attachments as $attachment) {
                $file = $attachment->file();
                if ('application/pdf' == $file->type) {
                    $this->uploadFileAttachment($file);
                }
            }
        }

        return 0;
    }

    private function uploadFileAttachment(File $file): void
    {
        $this->output->writeln('Uploading '.$file->name);
        $this->s3->putObject([
            'Bucket' => self::BUCKET_NAME,
            'Key' => 'check-lockbox-scans/'.$file->id.'-'.$file->name,
            'Body' => file_get_contents($file->url),
        ]);
    }
}
