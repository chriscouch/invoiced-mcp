<?php

namespace App\Reports\PresetReports;

use App\Companies\Models\Company;
use App\AccountsReceivable\Models\CreditNote;
use App\AccountsReceivable\Models\Invoice;
use App\Metadata\ValueObjects\MetadataQueryCondition;
use App\Reports\Exceptions\ReportException;
use App\Reports\Interfaces\PresetReportInterface;
use App\Reports\Libs\ReportHelper;
use App\Reports\Traits\ReportFormattingTrait;
use App\Reports\ValueObjects\Report;
use Carbon\CarbonImmutable;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Query\QueryBuilder;
use Exception;

abstract class AbstractReport implements PresetReportInterface
{
    use ReportFormattingTrait;

    protected Company $company;
    protected string $dateFormat;
    protected string $dateTimeFormat;
    protected array $parameters;
    protected string $currency;
    protected int $start;
    protected int $end;
    protected CarbonImmutable $startDate;
    protected CarbonImmutable $endDate;
    private array $invoiceMetadata;
    private array $invoiceTags;
    protected Report $report;

    public function __construct(
        protected Connection $database,
        protected ReportHelper $helper,
    ) {
    }

    //
    // Getters
    //

    protected function addInvoiceMetadataQuery(QueryBuilder $query, string $idColumn = 'Invoices.id'): QueryBuilder
    {
        if ($condition = $this->getInvoiceMetadataQuery($idColumn)) {
            $query->andWhere($condition);
        }

        return $query;
    }

    /**
     * Gets the SQL filtering condition for metadata.
     */
    public function getInvoiceMetadataQuery(string $idColumn = 'Invoices.id'): ?string
    {
        if (0 == count($this->invoiceMetadata)) {
            return null;
        }

        $model = new Invoice();
        $companyId = (int) $this->company->id();

        $conditions = [];
        foreach ($this->invoiceMetadata as $key => $value) {
            $conditions[] = new MetadataQueryCondition($key, (string) $value);
        }

        $storage = $model->getMetadataReader();
        $result = $storage->buildSqlConditions($conditions, $model, $companyId, $idColumn);

        return implode(' AND ', $result);
    }

    protected function addCreditNoteMetadataQuery(QueryBuilder $query, string $idColumn): QueryBuilder
    {
        if ($condition = $this->getCreditNoteMetadataQuery($idColumn)) {
            $query->andWhere($condition);
        }

        return $query;
    }

    /**
     * Gets the SQL filtering condition for metadata.
     */
    protected function getCreditNoteMetadataQuery(string $idColumn): ?string
    {
        if (0 == count($this->invoiceMetadata)) {
            return null;
        }

        $model = new CreditNote();
        $storage = $model->getMetadataReader();
        $companyId = (int) $this->company->id();

        $conditions = [];
        foreach ($this->invoiceMetadata as $key => $value) {
            $conditions[] = new MetadataQueryCondition($key, (string) $value);
        }

        $result = $storage->buildSqlConditions($conditions, $model, $companyId, $idColumn);

        return implode(' AND ', $result);
    }

    protected function addInvoiceTagQuery(QueryBuilder $query, string $idColumn = 'Invoices.id'): QueryBuilder
    {
        if ($condition = $this->getInvoiceTagsQuery($idColumn)) {
            $query->andWhere($condition);
        }

        return $query;
    }

    /**
     * Gets the SQL filtering condition for tags.
     */
    public function getInvoiceTagsQuery(string $id = 'Invoices.id'): ?string
    {
        if (0 == count($this->invoiceTags)) {
            return null;
        }

        // build IN query
        $in = $this->invoiceTags;
        foreach ($in as &$tag) {
            $tag = $this->database->quote($tag);
        }
        $in = implode(',', $in);

        return "(SELECT COUNT(*) FROM InvoiceTags WHERE invoice_id=$id AND tag IN ($in)) > 0";
    }

    //
    // Report Generation
    //

    /**
     * Gets the filename for this report without the extension.
     */
    protected function getFilename(): string
    {
        $name = $this->company->name.' '.$this->getName().' '.date($this->dateFormat, $this->start).'|'.date($this->dateFormat, $this->end);

        return str_replace([' ', '/'], ['-', '-'], $name);
    }

    public function generate(Company $company, array $parameters): Report
    {
        $company->useTimezone();
        $this->helper->switchTimezone($company->time_zone);

        $this->company = $company;
        $this->dateFormat = $company->date_format;
        $this->dateTimeFormat = $company->date_format.' g:i a';
        $this->moneyFormat = $company->moneyFormat();
        $this->parameters = $parameters;

        $this->currency = $company->currency;
        if (isset($parameters['$currency'])) {
            $this->currency = $parameters['$currency'];
        }

        $this->start = 0;
        $this->end = time();
        if (isset($parameters['$dateRange'])) {
            try {
                $this->startDate = CarbonImmutable::createFromFormat('Y-m-d', $parameters['$dateRange']['start'])->setTime(0, 0); /* @phpstan-ignore-line */
                $this->start = $this->startDate->getTimestamp();
                $this->endDate = CarbonImmutable::createFromFormat('Y-m-d', $parameters['$dateRange']['end'])->setTime(23, 59, 59); /* @phpstan-ignore-line */
                $this->end = $this->endDate->getTimestamp();
            } catch (Exception) {
                // Intentionally not throwing an exception here
            }
        }
        $this->invoiceMetadata = $parameters['$invoiceMetadata'] ?? [];
        $this->validateInvoiceMetadata();
        $this->invoiceTags = $parameters['$invoiceTags'] ?? [];
        $this->validateInvoiceTags();

        $this->report = new Report($company);
        $this->report->setFilename($this->getFilename());
        $this->report->setTitle($this->getName());
        $this->report->setParameters($parameters);

        // build body of report
        $this->build();

        return $this->report;
    }

    /**
     * @throws ReportException
     */
    private function validateInvoiceMetadata(): void
    {
        foreach ($this->invoiceMetadata as $key => $value) {
            // Allowed characters: a-z, A-Z, 0-9, _, -
            // Min length: 1
            if (is_numeric($key) || !preg_match('/^[A-Za-z0-9_-]*$/', $key)) {
                throw new ReportException('Invalid invoice metadata condition: '.$key);
            }
        }
    }

    /**
     * @throws ReportException
     */
    private function validateInvoiceTags(): void
    {
        foreach ($this->invoiceTags as $tag) {
            // Allowed characters: a-z, A-Z, 0-9, _, -
            // Min length: 1
            // Max length: 50
            if (!preg_match('/^[a-z0-9_-]{1,50}$/i', $tag)) {
                throw new ReportException('Invalid invoice tag condition: '.$tag);
            }
        }
    }

    /**
     * Gets the name of this report.
     */
    abstract protected function getName(): string;

    /**
     * Builds the report body.
     *
     * @throws ReportException when the report cannot be built
     */
    abstract protected function build(): void;
}
