<?php

namespace App\EntryPoint\Command;

use App\Companies\Models\Company;
use App\Core\Database\DatabaseHelper;
use App\Core\Files\Models\Attachment;
use App\Core\Files\Models\File;
use App\Core\Multitenant\TenantContext;
use App\Core\S3ProxyFactory;
use Doctrine\DBAL\Connection;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class ExtractRemittanceAdviceCommand extends Command
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
            ->setName('extract-remittance-advice')
            ->setDescription('Uploads all email PDF attachments that could be remittance advice to S3.')
            ->addOption(
                'ids',
                null,
                InputOption::VALUE_REQUIRED,
                'S3 file name of IDs to look up instead of query'
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->output = $output;

        $idFilename = $input->getOption('ids');
        if ($idFilename) {
            $csv = $this->getS3File($idFilename);
            $data = explode("\n", $csv);
            $ids = [];
            foreach ($data as $row) {
                $ids[] = trim($row);
            }
            $data = DatabaseHelper::bigSelect($this->database, 'InboxEmails', 'id IN ('.implode(',', $ids).')');
        } else {
            $data = DatabaseHelper::bigSelect($this->database, 'InboxEmails', '(subject LIKE "%remittance advice%" OR subject LIKE "%payment advice%")');
        }

        foreach ($data as $row) {
            $company = new Company(['id' => $row['tenant_id']]);
            $this->tenant->set($company);

            $attachments = Attachment::queryWithoutMultitenancyUnsafe()
                ->where('parent_type', 'email')
                ->where('parent_id', $row['id'])
                ->first(100);
            foreach ($attachments as $attachment) {
                $file = $attachment->file();
                $this->uploadFileAttachment($row['id'], $file);
            }
        }

        return 0;
    }

    private function getS3File(string $key): string
    {
        $this->output->writeln('Downloading '.$key);
        $object = $this->s3->getObject([
            'Bucket' => self::BUCKET_NAME,
            'Key' => $key,
        ]);

        return $object['Body'];
    }

    private function uploadFileAttachment(int $emailId, File $file): void
    {
        $this->output->writeln('Uploading '.$file->name);
        $this->s3->putObject([
            'Bucket' => self::BUCKET_NAME,
            'Key' => 'remittance-advice/'.$file->type.'/'.$emailId.'-'.$file->name,
            'Body' => file_get_contents($file->url),
        ]);
    }
}
