<?php

namespace App\Reports\Libs;

use App\Core\Files\Interfaces\FileCreatorInterface;
use App\Reports\Exceptions\ReportException;
use App\Reports\Models\Report as ReportModel;
use App\Reports\Output\Csv;
use App\Reports\Output\Html;
use App\Reports\Output\Json;
use App\Reports\Output\Pdf;
use App\Reports\ValueObjects\Report;
use Aws\S3\Exception\S3Exception;
use Carbon\CarbonImmutable;
use App\Core\Utils\InfuseUtility as Utility;
use mikehaertl\tmp\File;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;

final class ReportStorage implements LoggerAwareInterface
{
    use LoggerAwareTrait;

    public function __construct(
        private Csv $csv,
        private Html $html,
        private Json $json,
        private Pdf $pdf,
        private readonly FileCreatorInterface $s3FileCreator,
        readonly string $bucketRegion,
        readonly string $environment,
        private string $bucket
    ) {
    }

    /**
     * @throws ReportException when the report cannot be built or persisted
     */
    public function persist(Report $report, string $type): ReportModel
    {
        $savedReport = new ReportModel();
        $savedReport->title = $report->getTitle();
        $savedReport->filename = $report->getFilename();
        $savedReport->type = $type;
        $savedReport->timestamp = CarbonImmutable::now()->getTimestamp();
        $savedReport->data = $this->json->generate($report);
        $savedReport->definition = $report->getDefinition();
        $savedReport->parameters = $report->getParameters();
        $savedReport->save();

        return $savedReport;
    }

    /**
     * Generates and persists a CSV version of the report.
     *
     * @throws ReportException
     */
    public function saveCsv(Report $report): string
    {
        $temp = new File($this->csv->generate($report), 'csv');

        return $this->saveToS3($temp, $report->getFilename().'.csv');
    }

    /**
     * Generates and persists a PDF version of the report.
     *
     * @throws ReportException
     */
    public function savePdf(Report $report): string
    {
        $temp = new File($this->pdf->generate($report), 'pdf');

        return $this->saveToS3($temp, $report->getFilename().'.pdf');
    }

    /**
     * Persists data to S3 using a randomized filename.
     *
     * @throws ReportException when the file cannot be uploaded
     */
    private function saveToS3(File $tmpFile, string $filename): string
    {
        $key = strtolower(Utility::guid());

        try {
            $file = $this->s3FileCreator->create($this->bucket, $filename, $tmpFile->getFileName(), $key, [
                'Bucket' => $this->bucket,
                'Key' => $key,
                'SourceFile' => $tmpFile->getFileName(),
                'ContentDisposition' => 'attachment; filename="'.$filename.'"',
            ]);
        } catch (S3Exception $e) {
            $this->logger->error('Could not upload report', ['exception' => $e]);

            throw new ReportException('Could not upload report');
        }

        return $file->url;
    }

    public function getCsv(): Csv
    {
        return $this->csv;
    }

    public function getHtml(): Html
    {
        return $this->html;
    }

    public function getJson(): Json
    {
        return $this->json;
    }

    public function getPdf(): Pdf
    {
        return $this->pdf;
    }
}
