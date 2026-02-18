<?php

namespace App\Statements\Libs;

use App\AccountsReceivable\Models\Customer;
use App\Companies\Models\Company;
use App\Core\Pdf\PdfDocumentInterface;
use App\Core\Utils\Enums\ObjectType;
use App\Reports\Libs\AgingReport;
use App\Reports\ValueObjects\AgingBreakdown;
use App\Sending\Email\Interfaces\EmailVariablesInterface;
use App\Sending\Email\Interfaces\SendableDocumentInterface;
use App\Sending\Email\Libs\EmailHtml;
use App\Sending\Email\Traits\SendableDocumentTrait;
use App\Statements\EmailVariables\StatementEmailVariables;
use App\Statements\Pdf\StatementPdf;
use App\Statements\Pdf\StatementPdfVariables;
use App\Themes\Interfaces\PdfBuilderInterface;
use App\Themes\Interfaces\PdfVariablesInterface;
use App\Themes\Interfaces\ThemeableInterface;
use App\Themes\Traits\ThemeableTrait;
use Carbon\CarbonImmutable;
use App\Core\Orm\Errors;

/**
 * @property string   $type
 * @property int|null $start
 * @property int|null $end
 * @property bool     $pastDueOnly
 * @property string   $currency
 * @property float    $previousBalance
 * @property float    $totalInvoiced
 * @property float    $totalPaid
 * @property float    $totalUnapplied
 * @property float    $balance
 * @property array    $accountDetail
 * @property array    $unifiedDetail
 * @property array    $aging
 * @property float    $previousCreditBalance
 * @property float    $totalCreditsIssued
 * @property float    $totalCreditsSpent
 * @property array    $creditDetail
 * @property float    $creditBalance
 * @property Customer $customer
 * @property int[]    $customerIds
 */
abstract class AbstractStatement implements PdfDocumentInterface, SendableDocumentInterface, ThemeableInterface
{
    use SendableDocumentTrait;
    use ThemeableTrait;

    protected Customer $customer;
    protected Company $company;
    protected string $type;
    protected ?int $start = null;
    protected ?int $end = null;
    protected bool $pastDueOnly = false;
    protected array $calculated;

    public function __construct(Customer $customer, protected ?string $currency = null)
    {
        $this->company = $customer->tenant();
        $this->customer = $customer;
    }

    //
    // Getters
    //

    public function __get(string $k): mixed
    {
        // ensure statement is calculated
        if (!isset($this->calculated)) {
            $this->calculated = $this->calculate();
        }

        // return calculated properties
        if (isset($this->calculated[$k])) {
            return $this->calculated[$k];
        }

        return $this->$k;
    }

    /**
     * Gets the start date.
     */
    public function getStartDate(): ?int
    {
        return $this->start;
    }

    /**
     * Gets the end date.
     */
    public function getEndDate(): ?int
    {
        return $this->end;
    }

    /**
     * Gets the statement currency.
     */
    public function getCurrency(): string
    {
        if (!$this->currency) {
            $this->currency = $this->calculatePrimaryCurrency();
        }

        return $this->currency;
    }

    /**
     * Gets the primary currency for the statement's customer.
     */
    protected function calculatePrimaryCurrency(): string
    {
        return $this->company->currency;
    }

    //
    // Statement Generation
    //

    /**
     * Gets the calculated statement.
     */
    public function getValues(): array
    {
        // ensure statement is calculated
        if (!isset($this->calculated)) {
            $this->calculated = $this->calculate();
        }

        return $this->calculated;
    }

    abstract protected function calculate(): array;

    /**
     * Builds the customer's aging section.
     */
    protected function buildAging(): array
    {
        $agingBreakdown = AgingBreakdown::fromSettings($this->company->accounts_receivable_settings);
        $aging = new AgingReport($agingBreakdown, $this->company, $this->company::getDriver()->getConnection(null));
        if ($this->end) {
            $aging->setDate(CarbonImmutable::createFromTimestamp($this->end));
        }
        $agingReport = $aging->buildForCustomer((int) $this->customer->id(), $this->getCurrency())[$this->customer->id()];

        $statementAging = [];
        $agingBuckets = $agingBreakdown->getBuckets();
        foreach ($agingBuckets as $i => $bucket) {
            $statementAging[] = [
                'lower' => $bucket['lower'],
                'amount' => $agingReport[$i]['amount']->toDecimal(),
                'count' => $agingReport[$i]['count'],
            ];
        }

        return $statementAging;
    }

    //
    // Model shim
    //

    public function getErrors(): Errors
    {
        return $this->customer->getErrors();
    }

    //
    // SendableDocumentInterface
    //

    public function getSendId(): int
    {
        return (int) $this->customer->id();
    }

    public function getSendObjectType(): ?ObjectType
    {
        return null;
    }

    public function getSendCustomer(): Customer
    {
        return $this->customer;
    }

    public function getSendCompany(): Company
    {
        return $this->company;
    }

    public function getEmailVariables(): EmailVariablesInterface
    {
        return new StatementEmailVariables($this);
    }

    public function schemaOrgActions(): ?string
    {
        $buttonText = 'View Statement';
        $url = $this->getSendClientUrl();
        $description = 'Please review your account statement';

        return EmailHtml::schemaOrgViewAction($buttonText, $url, $description);
    }

    public function getSendClientUrl(): string
    {
        $url = $this->customer->statement_url;
        $params = [
            'currency' => $this->getCurrency(),
            'type' => $this->type,
        ];
        if ($start = $this->start) {
            $params['start'] = $start;
        }
        if ($end = $this->end) {
            $params['end'] = $end;
        }
        if ($this->pastDueOnly) {
            $params['items'] = 'past_due';
        }
        $url .= '?'.http_build_query($params);

        return $url;
    }

    public function getPdfBuilder(): ?PdfBuilderInterface
    {
        return new StatementPdf($this);
    }

    //
    // ThemeableInterface
    //

    public function getThemeVariables(): PdfVariablesInterface
    {
        return new StatementPdfVariables($this);
    }

    public function getThemeCompany(): Company
    {
        return $this->company;
    }
}
