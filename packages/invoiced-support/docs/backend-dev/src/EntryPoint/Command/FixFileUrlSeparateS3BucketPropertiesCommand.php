<?php

namespace App\EntryPoint\Command;

use Doctrine\DBAL\Connection;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class FixFileUrlSeparateS3BucketPropertiesCommand extends Command
{
    public function __construct(
        private readonly Connection $database,
        private string $filesUrl,
        private string $environment
    ){
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->setName('fix:file_url_separate_s3_bucket_properties')
            ->setDescription('This command will fetch S3 bucket properties from url column, update other columns and set new url into initial column.')
            ->addArgument(
                'chunk',
                InputArgument::OPTIONAL,
                'chunk size defaults to 1000'
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $regex = '/^https:\/\/(?<bucket>[\w.-]+)\.s3(?:\.(?<region>[\w-]+))?\.amazonaws\.com\/(?<key>.*)$/';
        $chunkSize = $input->getArgument('chunk') ?? 1000;
        $lastProcessedId = 0;

        while (true) {
            $files = $this->database->fetchAllAssociative(
                "SELECT * FROM Files WHERE id > :lastId AND (`key` = '' OR `key` IS NULL) LIMIT " . $chunkSize, ['lastId' => $lastProcessedId]);

            if (sizeof($files) == 0) {
                break;
            }

            $updateCaseParts = [];
            $updateIds = [];
            $urlParams = [];
            $bucketParams = [];
            $regionParams = [];
            $keyParams = [];
            $environmentParams = [];

            foreach ($files as $file) {
                if (preg_match($regex, $file['url'], $matches)) {
                    $id = $file['id'];
                    $bucket = $matches['bucket'];
                    $region = empty($matches['region']) ? 'us-east-2' : $matches['region'];
                    $key = $matches['key'];
                    $environment = $this->environment;

                    $urlParams[] = $id;
                    $urlParams[] = $this->filesUrl . '/' . $key;

                    $updateCaseParts[] = "WHEN ? THEN ?";
                    $updateIds[] = $id;

                    $bucketParams[] = $id;
                    $bucketParams[] = $bucket;

                    $regionParams[] = $id;
                    $regionParams[] = $region;

                    $keyParams[] = $id;
                    $keyParams[] = $key;

                    $environmentParams[] = $id;
                    $environmentParams[] = $environment;
                }
            }

            if (!empty($updateIds)) {
                $sql = "UPDATE Files SET
                     `url` = CASE `id` " . implode(" ", $updateCaseParts) . " END,
                    `bucket_name` = CASE `id` " . implode(" ", $updateCaseParts) . " END,
                    `bucket_region` = CASE `id` " . implode(" ", $updateCaseParts) . " END,
                    `key` = CASE `id` " . implode(" ", $updateCaseParts) . " END,
                    `s3_environment` = CASE `id` " . implode(" ", $updateCaseParts) . " END
                WHERE `id` IN (" . implode(",", array_fill(0, count($updateIds), '?')) . ")";

                $params = array_merge(
                    $urlParams,
                    $bucketParams,
                    $regionParams,
                    $keyParams,
                    $environmentParams,
                    $updateIds
                );

                $this->database->executeStatement($sql, $params);
            }

            $lastProcessedId = end($files)['id'];
        }

        return 0;
    }
}