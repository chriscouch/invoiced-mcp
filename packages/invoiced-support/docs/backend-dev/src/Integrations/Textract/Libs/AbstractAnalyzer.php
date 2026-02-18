<?php

namespace App\Integrations\Textract\Libs;

use App\AccountsPayable\Models\Vendor;
use App\Core\Files\Models\File;
use App\Core\Multitenant\TenantContext;
use App\Core\Statsd\Interfaces\StatsdAwareInterface;
use App\Core\Statsd\StatsdAwareTrait;
use App\Integrations\Textract\Enums\TextractProcessingStage;
use App\Integrations\Textract\Interfaces\ExpenseDocumentAnalyzerInterface;
use App\Integrations\Textract\Models\TextractImport;
use App\Integrations\Textract\ValueObjects\AnalyzedParameters;
use Aws\Sdk;
use Aws\Textract\TextractClient;
use Doctrine\DBAL\Connection;

abstract class AbstractAnalyzer implements ExpenseDocumentAnalyzerInterface, StatsdAwareInterface
{
    use StatsdAwareTrait;

    protected TextractClient $client;
    private ?ExpenseDocumentAnalyzerInterface $next = null;

    public function __construct(
        private readonly Connection $connection,
        private readonly TenantContext $tenantContext,
        string $bucketRegion,
        private readonly string $bucket,
        private readonly string $textractRole,
        private readonly string $textractTopic,
        Sdk $aws,
    ) {
        $this->client = $aws->createTextract([
            'region' => $bucketRegion,
        ]);
    }

    abstract protected function getJobSuccessStatus(): TextractProcessingStage;

    public function setNext(ExpenseDocumentAnalyzerInterface $next): void
    {
        $this->next = $next;
    }

    protected function getTextractParameters(File $file): array
    {
        return [
            'DocumentLocation' => [
                'S3Object' => [
                    'Bucket' => $this->bucket,
                    'Name' => basename($file->url),
                ],
            ],
            'NotificationChannel' => [
                'RoleArn' => $this->textractRole,
                'SNSTopicArn' => $this->textractTopic,
            ],
        ];
    }

    protected function determineVendor(AnalyzedParameters $parameters): ?Vendor
    {
        $name = $parameters->vendor;

        if (!$name) {
            return null;
        }

        $vendorCandidates = $this->connection->executeQuery("SELECT id, name FROM Vendors WHERE tenant_id = :tenant AND (name LIKE :nameLike OR :name LIKE CONCAT('%', name, '%'))", [
            'nameLike' => "%$name%",
            'name' => $name,
            'tenant' => $this->tenantContext->get()->id,
        ])->fetchAllAssociative();

        if (1 === count($vendorCandidates)) {
            return Vendor::find($vendorCandidates[0]['id']);
        }

        // we can't determine vendor
        return null;
    }

    protected function finalize(TextractImport $job, AnalyzedParameters $parameters): void
    {
        if (null === $job->vendor) {
            $job->vendor = $this->determineVendor($parameters);
        }

        $parameters->vendorObject = $job->vendor;
        $job->data = (object) $parameters->toArray();
        $job->status = $this->getJobSuccessStatus();

        if (!$parameters->isValid() && $jobId = $this->next?->send($job->file)) {
            $import = new TextractImport();
            $import->job_id = $jobId;
            $import->parent_job_id = $job->job_id;
            $import->file = $job->file;
            $import->vendor = $job->vendor;
            $import->saveOrFail();
        } else {
            $job->status = TextractProcessingStage::Succeed;
        }

        $job->saveOrFail();
    }

    protected function getInitialParameters(TextractImport $job): array
    {
        $data = [];
        if ($job->parent_job_id) {
            /** @var TextractImport|null $parent */
            $parent = TextractImport::where('job_id', $job->parent_job_id)->oneOrNull();
            $data = (array) $parent?->data;
        }
        if (isset($data['line_items'])) {
            $data['line_items'] = array_map(fn ($item) => (array) $item, $data['line_items']);
        }

        return $data;
    }

    protected function parsePrice(string $value): float
    {
        return (float) preg_replace("/[^\d.-]/", '', $value);
    }
}
