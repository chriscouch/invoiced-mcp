<?php

namespace App\Reports\PresetReports;

use App\CashApplication\Models\Transaction;
use App\Companies\Models\Company;
use App\Core\I18n\ValueObjects\Money;
use App\Reports\ValueObjects\KeyValueGroup;
use App\Reports\ValueObjects\Report;
use App\Reports\ValueObjects\Section;
use Doctrine\DBAL\Query\QueryBuilder;

/**
 * This report generates the taxes billed or collected,
 * broken down by tax rate. Generally this is used
 * to determine how much to pay to the tax agency.
 *
 * There are two ways that this report can be calculated:
 *   Accrual - based on the invoice date
 *   Cash - based on the payment date
 *
 * The cash basis is more challenging because there can
 * be partial payments. Each payment will only be responsible
 * for a percentage of the tax billed, relative to the total of invoice.
 *
 * Definitions:
 * Gross Sales - Total amount sold (includes taxes)
 * Taxed Sales - Sales that were taxed
 * Tax Amount - Amount that was billed or collected (depending on report basis)
 *
 * The Taxed Sales amount will use the invoice total
 * for a subtotal tax, or the line item amount (plus tax)
 * for a line item tax. The tax has to be added to the
 * line item amount because the line item amount is a subtotal
 * that excludes the tax billed.
 */
class TaxSummary extends AbstractReport
{
    private const ACCRUAL_BASIS = 'invoice';
    private const CASH_BASIS = 'payment';

    private const TABLE_HEADER = [
        ['name' => 'Tax Rate', 'type' => 'string'],
        ['name' => 'Taxed Sales', 'type' => 'money'],
        ['name' => 'Tax Amount', 'type' => 'money'],
    ];

    private const TAX_OBJECT_NAME = 'tax';

    private string $basis = self::ACCRUAL_BASIS;
    private array $rateNames = [];

    public static function getId(): string
    {
        return 'tax_summary';
    }

    protected function getName(): string
    {
        return 'Sales Tax Summary';
    }

    public function generate(Company $company, array $parameters): Report
    {
        if (isset($parameters['$taxRecognitionDate'])) {
            $this->basis = $parameters['$taxRecognitionDate'];
        }

        return parent::generate($company, $parameters);
    }

    protected function build(): void
    {
        /* Create a row for each matching tax rate */

        $rows = [];
        $grossSales = new Money($this->currency, 0);
        if (self::ACCRUAL_BASIS === $this->basis) {
            $rows = $this->buildRowsAccrual();
            $grossSales = $this->getGrossSalesAccrual();
        } elseif (self::CASH_BASIS === $this->basis) {
            $rows = $this->buildRowsCash();
            $grossSales = $this->getGrossSalesCash();
        }

        /* Sort */

        usort($rows, [$this, 'rowSort']);

        /* Build the sections */

        $this->buildSections($grossSales, $rows);
    }

    /**
     * Builds an empty row.
     */
    private function buildRow(string $internalId): array
    {
        return [
            $this->getRateName($internalId),
            new Money($this->currency, 0), // taxed sales
            new Money($this->currency, 0), // tax amount
        ];
    }

    private function getRateName(string $rateId): string
    {
        if (!$rateId || '*' === $rateId) {
            return 'Unclassified Tax';
        }

        if (!isset($this->rateNames[$rateId])) {
            $this->rateNames[$rateId] = $this->database->fetchOne(
                'SELECT name FROM TaxRates WHERE tenant_id = ? AND internal_id = ?',
                [$this->company->id(), $rateId]
            );
        }

        return $this->rateNames[$rateId];
    }

    /**
     * Sorts rows.
     */
    public function rowSort(array $a, array $b): int
    {
        // No ID type groups should always be at the bottom
        if ('Unclassified Tax' === $a[0]) {
            return 1;
        } elseif ('Unclassified Tax' === $b[0]) {
            return -1;
        }

        // sort by title
        return strcasecmp($a[0], $b[0]);
    }

    /**
     * Builds the total and detail sections.
     */
    private function buildSections(Money $grossSales, array $rows): void
    {
        // build total section
        $section = new Section('Total');
        $overview = new KeyValueGroup();
        $overview->addLine('Gross Sales', $this->formatMoney($grossSales));
        $section->addGroup($overview);

        $this->report->addSection($section);

        // build details section
        if (0 == count($rows)) {
            return;
        }

        $this->report->addSection(
            $this->buildTableSection('Breakdown', self::TABLE_HEADER, $rows)
        );
    }

    //
    // Accrual Basis
    //

    private function getGrossSalesAccrual(): Money
    {
        $query = $this->database->createQueryBuilder()
            ->select('SUM(i.total)')
            ->from('Invoices', 'i')
            ->andWhere('i.tenant_id = :tenantId')
            ->setParameter('tenantId', $this->company->id())
            ->andWhere('i.currency = :currency')
            ->setParameter('currency', $this->currency)
            ->andWhere('i.draft = 0')
            ->andWhere('i.voided = 0')
            ->andWhere('i.date BETWEEN '.$this->start.' AND '.$this->end);
        $this->addInvoiceMetadataQuery($query, 'i.id');
        $this->addInvoiceTagQuery($query, 'i.id');
        $totalSold = $query->fetchOne();
        $totalSold = Money::fromDecimal($this->currency, $totalSold ?? 0);

        $query = $this->database->createQueryBuilder()
            ->select('SUM(c.total)')
            ->from('CreditNotes', 'c')
            ->andWhere('c.tenant_id = :tenantId')
            ->setParameter('tenantId', $this->company->id())
            ->andWhere('c.currency = :currency')
            ->setParameter('currency', $this->currency)
            ->andWhere('c.draft = 0')
            ->andWhere('c.voided = 0')
            ->andWhere('c.date BETWEEN '.$this->start.' AND '.$this->end);
        $this->addCreditNoteMetadataQuery($query, 'c.id');
        $totalCredited = $query->fetchOne();
        $totalCredited = Money::fromDecimal($this->currency, $totalCredited ?? 0);

        return $totalSold->subtract($totalCredited);
    }

    /**
     * Fetches the tax that was applied to subtotals
     * on an accrual basis, grouped by tax rate.
     */
    private function getSubtotalTaxesAccrual(): array
    {
        $query = $this->database->createQueryBuilder()
            ->select('a.rate_id AS rate_id, SUM(a.amount) AS tax_amount')
            ->from('AppliedRates', 'a')
            ->join('a', 'Invoices', 'i', 'i.id = a.invoice_id')
            ->andWhere('a.tenant_id = :tenantId')
            ->setParameter('tenantId', $this->company->id())
            ->andWhere('a.type = "'.self::TAX_OBJECT_NAME.'"')
            ->andWhere('i.currency = :currency')
            ->setParameter('currency', $this->currency)
            ->andWhere('i.draft = 0')
            ->andWhere('i.voided = 0')
            ->andWhere('i.date BETWEEN '.$this->start.' AND '.$this->end)
            ->groupBy('a.rate_id');
        $this->addInvoiceMetadataQuery($query, 'i.id');
        $this->addInvoiceTagQuery($query, 'i.id');
        $invoiceRows = $query->fetchAllAssociative();

        $query = $this->database->createQueryBuilder()
            ->select('tr.internal_id AS rate_id, -SUM(a.amount) AS tax_amount')
            ->from('AppliedRates', 'a')
            ->join('a', 'TaxRates', 'tr', 'a.rate = tr.id')
            ->join('a', 'CreditNotes', 'c', 'c.id=a.credit_note_id')
            ->andWhere('a.tenant_id = :tenantId')
            ->andWhere('tr.tenant_id = :tenantId')
            ->andWhere('c.tenant_id = :tenantId')
            ->setParameter('tenantId', $this->company->id())
            ->andWhere('a.type = "'.self::TAX_OBJECT_NAME.'"')
            ->andWhere('c.currency = :currency')
            ->setParameter('currency', $this->currency)
            ->andWhere('c.draft = 0')
            ->andWhere('c.voided = 0')
            ->andWhere('c.date BETWEEN '.$this->start.' AND '.$this->end)
            ->groupBy('tr.internal_id')
            ->having('tax_amount < 0');
        $this->addCreditNoteMetadataQuery($query, 'c.id');
        $creditRows = $query->fetchAllAssociative();

        return [...$invoiceRows, ...$creditRows];
    }

    /**
     * Fetches the tax that was applied to line items
     * on an accrual basis, grouped by tax rate.
     */
    private function getLineItemTaxesAccrual(): array
    {
        $query = $this->database->createQueryBuilder()
            ->select('a.rate_id AS rate_id, SUM(a.amount) AS tax_amount')
            ->from('AppliedRates', 'a')
            ->join('a', 'LineItems', 'l', 'l.id = a.line_item_id')
            ->join('l', 'Invoices', 'i', 'i.id = l.invoice_id')
            ->andWhere('a.tenant_id = :tenantId')
            ->andWhere('l.tenant_id = :tenantId')
            ->andWhere('i.tenant_id = :tenantId')
            ->setParameter('tenantId', $this->company->id())
            ->andWhere('a.type = "'.self::TAX_OBJECT_NAME.'"')
            ->andWhere('i.currency = :currency')
            ->setParameter('currency', $this->currency)
            ->andWhere('i.draft = 0')
            ->andWhere('i.voided = 0')
            ->andWhere('i.date BETWEEN '.$this->start.' AND '.$this->end)
            ->groupBy('a.rate_id');
        $this->addInvoiceMetadataQuery($query, 'i.id');
        $this->addInvoiceTagQuery($query, 'i.id');
        $invoiceRows = $query->fetchAllAssociative();

        $query = $this->database->createQueryBuilder()
            ->select('tr.internal_id AS rate_id, -SUM(a.amount) AS tax_amount')
            ->from('AppliedRates', 'a')
            ->join('a', 'TaxRates', 'tr', 'a.rate = tr.id')
            ->join('a', 'LineItems', 'l', 'l.id=a.line_item_id')
            ->join('l', 'CreditNotes', 'c', 'c.id=l.credit_note_id')
            ->andWhere('a.tenant_id = :tenantId')
            ->andWhere('tr.tenant_id = :tenantId')
            ->andWhere('c.tenant_id = :tenantId')
            ->andWhere('l.tenant_id = :tenantId')
            ->setParameter('tenantId', $this->company->id())
            ->andWhere('a.type = "'.self::TAX_OBJECT_NAME.'"')
            ->andWhere('c.currency = :currency')
            ->setParameter('currency', $this->currency)
            ->andWhere('c.draft = 0')
            ->andWhere('c.voided = 0')
            ->andWhere('c.date BETWEEN '.$this->start.' AND '.$this->end)
            ->groupBy('tr.internal_id')
            ->having('tax_amount < 0');
        $this->addCreditNoteMetadataQuery($query, 'c.id');
        $creditRows = $query->fetchAllAssociative();

        return [...$invoiceRows, ...$creditRows];
    }

    /**
     * Gets the taxed sales on an accrual basis
     * for a given tax rate.
     */
    private function getTaxedSalesAccrual(string $rateId): Money
    {
        $grossSubtotal = $this->getTaxedSalesSubtotalAccrual($rateId);
        $grossLineItem = $this->getTaxedSalesLineItemAccrual($rateId);

        return $grossSubtotal->add($grossLineItem);
    }


    /**
     * Fetches the total sales that were included
     * a given tax rate at the subtotal level.
     */
    private function getTaxedSalesSubtotalAccrual(string $rateId): Money
    {
        $idCond = ('*' !== $rateId) ? "a.rate_id = '$rateId'" : 'a.rate_id IS NULL';

        $subselect1 = $this->database->createQueryBuilder()
            ->select('*')
            ->from('AppliedRates', 'a')
            ->andWhere('a.invoice_id = i.id')
            ->andWhere('a.type = "'.self::TAX_OBJECT_NAME.'"')
            ->andWhere($idCond);

        $subselect2 = $this->database->createQueryBuilder()
            ->select('*')
            ->from('AppliedRates', 'a')
            ->andWhere('a.credit_note_id = c.id')
            ->andWhere('a.type = "'.self::TAX_OBJECT_NAME.'"')
            ->andWhere($idCond);

        $query = $this->database->createQueryBuilder()
            ->select('SUM(i.total)')
            ->from('Invoices', 'i')
            ->andWhere('i.tenant_id = :tenantId')
            ->setParameter('tenantId', $this->company->id())
            ->andWhere('i.currency = :currency')
            ->setParameter('currency', $this->currency)
            ->andWhere('i.draft = 0')
            ->andWhere('i.voided = 0')
            ->andWhere('i.date BETWEEN '.$this->start.' AND '.$this->end)
            ->andWhere('EXISTS ('.$subselect1->getSQL().')');
        $this->addInvoiceMetadataQuery($query, 'i.id');
        $this->addInvoiceTagQuery($query, 'i.id');
        $taxedSales = Money::fromDecimal($this->currency, $query->fetchOne() ?? 0);

        $query = $this->database->createQueryBuilder()
            ->select('SUM(c.total)')
            ->from('CreditNotes', 'c')
            ->andWhere('c.tenant_id = :tenantId')
            ->setParameter('tenantId', $this->company->id())
            ->andWhere('c.currency = :currency')
            ->setParameter('currency', $this->currency)
            ->andWhere('c.draft = 0')
            ->andWhere('c.voided = 0')
            ->andWhere('c.date BETWEEN '.$this->start.' AND '.$this->end)
            ->andWhere('EXISTS ('.$subselect2->getSQL().')');
        $this->addCreditNoteMetadataQuery($query, 'c.id');
        $taxedSalesCredited = Money::fromDecimal($this->currency, $query->fetchOne() ?? 0);

        return $taxedSales->subtract($taxedSalesCredited);
    }

    /**
     * Fetches the total sales that were included
     * a given tax rate at the line item level.
     */
    private function getTaxedSalesLineItemAccrual(string $rateId): Money
    {
        $idCond = ('*' !== $rateId) ? "a.rate_id = '$rateId'" : 'a.rate_id IS NULL';

        $subselect1 = $this->database->createQueryBuilder()
            ->select('*')
            ->from('AppliedRates', 'a')
            ->join('a', 'LineItems', 'l', 'l.id = a.line_item_id')
            ->andWhere('l.invoice_id = i.id')
            ->andWhere('a.type = "'.self::TAX_OBJECT_NAME.'"')
            ->andWhere($idCond);

        $query = $this->database->createQueryBuilder()
            ->select('SUM(l.amount)')
            ->from('LineItems', 'l')
            ->join('l', 'Invoices', 'i', 'l.invoice_id = i.id')
            ->andWhere('l.tenant_id = :tenantId')
            ->setParameter('tenantId', $this->company->id())
            ->andWhere('i.currency = :currency')
            ->setParameter('currency', $this->currency)
            ->andWhere('i.draft = 0')
            ->andWhere('i.voided = 0')
            ->andWhere('i.date BETWEEN '.$this->start.' AND '.$this->end)
            ->andWhere('EXISTS ('.$subselect1->getSQL().')');
        $this->addInvoiceMetadataQuery($query, 'i.id');
        $this->addInvoiceTagQuery($query, 'i.id');
        $taxedSales = Money::fromDecimal($this->currency, $query->fetchOne() ?? 0);

        $subselect2 = $this->database->createQueryBuilder()
            ->select('*')
            ->from('AppliedRates', 'a')
            ->join('a', 'LineItems', 'l', 'l.id = a.line_item_id')
            ->andWhere('l.credit_note_id = c.id')
            ->andWhere('a.type = "'.self::TAX_OBJECT_NAME.'"')
            ->andWhere($idCond);

        $query = $this->database->createQueryBuilder()
            ->select('SUM(l.amount)')
            ->from('LineItems', 'l')
            ->join('l', 'CreditNotes', 'c', 'l.credit_note_id = c.id')
            ->andWhere('l.tenant_id = :tenantId')
            ->setParameter('tenantId', $this->company->id())
            ->andWhere('c.currency = :currency')
            ->setParameter('currency', $this->currency)
            ->andWhere('c.draft = 0')
            ->andWhere('c.voided = 0')
            ->andWhere('c.date BETWEEN '.$this->start.' AND '.$this->end)
            ->andWhere('EXISTS ('.$subselect2->getSQL().')');
        $this->addCreditNoteMetadataQuery($query, 'c.id');
        $taxedSalesCredited = Money::fromDecimal($this->currency, $query->fetchOne() ?? 0);

        return $taxedSales->subtract($taxedSalesCredited);
    }

    /**
     * Builds the tax rows for an accrual basis.
     */
    private function buildRowsAccrual(): array
    {
        $lines = array_merge(
            $this->getSubtotalTaxesAccrual(),
            $this->getLineItemTaxesAccrual()
        );

        $rows = [];
        foreach ($lines as $line) {
            $rateId = $line['rate_id'] ?? '*';

            if (!isset($rows[$rateId])) {
                $rows[$rateId] = $this->buildRow($rateId);
                $rows[$rateId][1] = $this->getTaxedSalesAccrual($rateId); // Uses internalId now
            }

            $taxAmount = Money::fromDecimal($this->currency, $line['tax_amount']);
            $rows[$rateId][2] = $rows[$rateId][2]->add($taxAmount);
        }

        return $rows;
    }

    //
    // Cash Basis
    //

    private function getGrossSalesCash(): Money
    {
        $subselect1 = $this->database->createQueryBuilder()
            ->select('*')
            ->from('AppliedRates', 'a')
            ->join('a', 'Invoices', 'i', 'i.id=a.invoice_id')
            ->andWhere('i.id=t.invoice')
            ->andWhere('a.type = "'.self::TAX_OBJECT_NAME.'"')
            ->andWhere('i.draft = 0');

        $subselect2 = $this->database->createQueryBuilder()
            ->select('*')
            ->from('AppliedRates', 'a')
            ->join('a', 'LineItems', 'l', 'l.id=a.line_item_id')
            ->join('l', 'Invoices', 'i', 'i.id=l.invoice_id')
            ->andWhere('i.id=t.invoice')
            ->andWhere('a.type = "'.self::TAX_OBJECT_NAME.'"')
            ->andWhere('i.draft = 0');

        if ($metadataCond = $this->getInvoiceMetadataQuery('i.id')) {
            $subselect1->andWhere($metadataCond);
            $subselect2->andWhere($metadataCond);
        }

        if ($tagCond = $this->getInvoiceTagsQuery('i.id')) {
            $subselect1->andWhere($tagCond);
            $subselect2->andWhere($tagCond);
        }

        $rows = $this->database->createQueryBuilder()
            ->select('t.type,SUM(t.amount) AS gross')
            ->from('Transactions t')
            ->andWhere('t.tenant_id = :tenantId')
            ->setParameter('tenantId', $this->company->id())
            ->andWhere('t.currency = :currency')
            ->setParameter('currency', $this->currency)
            ->andWhere('t.status = "'.Transaction::STATUS_SUCCEEDED.'"')
            ->andWhere('t.date BETWEEN '.$this->start.' AND '.$this->end)
            ->andWhere('EXISTS ('.$subselect1->getSQL().') OR EXISTS ('.$subselect2->getSQL().')')
            ->groupBy('t.type')
            ->fetchAllAssociative();

        $total = new Money($this->currency, 0);
        foreach ($rows as $row) {
            $amount = Money::fromDecimal($this->currency, $row['gross']);
            if (Transaction::TYPE_REFUND == $row['type']) {
                $total = $total->subtract($amount);
            } else {
                $total = $total->add($amount);
            }
        }

        return $total;
    }

    /**
     * Gets the query to fetch the tax due to invoices on a cash basis.
     */
    private function getQueryCashInvoicesTax(): QueryBuilder
    {
        $query = $this->database->createQueryBuilder()
            ->select('t.type, a.rate_id, SUM(a.amount * t.amount / i.total) AS tax_amount')
            ->from('AppliedRates', 'a')
            ->join('a', 'Invoices', 'i', 'i.id=a.invoice_id')
            ->join('i', 'Transactions', 't', 't.invoice=i.id')
            ->andWhere('a.tenant_id = :tenantId')
            ->setParameter('tenantId', $this->company->id())
            ->andWhere('a.type = "'.self::TAX_OBJECT_NAME.'"')
            ->andWhere('i.currency = :currency')
            ->setParameter('currency', $this->currency)
            ->andWhere('i.draft = 0')
            ->andWhere('t.status = "'.Transaction::STATUS_SUCCEEDED.'"')
            ->andWhere('t.date BETWEEN '.$this->start.' AND '.$this->end)
            ->andWhere('t.type IN ("'.Transaction::TYPE_CHARGE.'", "'.Transaction::TYPE_PAYMENT.'", "'.Transaction::TYPE_REFUND.'")')
            ->groupBy('a.rate_id, t.type');

        $this->addInvoiceMetadataQuery($query, 'i.id');
        $this->addInvoiceTagQuery($query, 'i.id');

        return $query;
    }

    /**
     * Gets the query to fetch the tax due to line items on a cash basis.
     */
    private function getQueryCashLineItemsTax(): QueryBuilder
    {
        $query = $this->database->createQueryBuilder()
            ->select('t.type, a.rate_id, SUM(a.amount * t.amount / i.total) AS tax_amount')
            ->from('AppliedRates', 'a')
            ->join('a', 'LineItems', 'l', 'l.id=a.line_item_id')
            ->join('l', 'Invoices', 'i', 'i.id=l.invoice_id')
            ->join('i', 'Transactions', 't', 't.invoice=i.id')
            ->andWhere('a.tenant_id = :tenantId')
            ->setParameter('tenantId', $this->company->id())
            ->andWhere('a.type = "'.self::TAX_OBJECT_NAME.'"')
            ->andWhere('i.currency = :currency')
            ->setParameter('currency', $this->currency)
            ->andWhere('i.draft = 0')
            ->andWhere('t.status = "'.Transaction::STATUS_SUCCEEDED.'"')
            ->andWhere('t.date BETWEEN '.$this->start.' AND '.$this->end)
            ->andWhere('t.type IN ("'.Transaction::TYPE_CHARGE.'", "'.Transaction::TYPE_PAYMENT.'", "'.Transaction::TYPE_REFUND.'")')
            ->groupBy('a.rate_id, t.type');

        $this->addInvoiceMetadataQuery($query, 'i.id');
        $this->addInvoiceTagQuery($query, 'i.id');

        return $query;
    }

    /**
     * Gets the query to fetch the gross for a rate on a cash basis.
     */
    private function getQueryCashGrossForRate(string $rateId): QueryBuilder
    {
        $idCond = ('*' !== $rateId) ? "a.rate_id = '$rateId'" : 'a.rate_id IS NULL';
        $subselect1 = $this->database->createQueryBuilder()
            ->select('*')
            ->from('AppliedRates', 'a')
            ->join('a', 'Invoices', 'i', 'i.id = a.invoice_id')
            ->andWhere('i.id = t.invoice')
            ->andWhere('a.type = "'.self::TAX_OBJECT_NAME.'"')
            ->andWhere('i.draft = 0')
            ->andWhere($idCond);

        $subselect2 = $this->database->createQueryBuilder()
            ->select('*')
            ->from('AppliedRates', 'a')
            ->join('a', 'LineItems', 'l', 'l.id = a.line_item_id')
            ->join('l', 'Invoices', 'i', 'i.id = l.invoice_id')
            ->andWhere('i.id = t.invoice')
            ->andWhere('a.type = "'.self::TAX_OBJECT_NAME.'"')
            ->andWhere('i.draft = 0')
            ->andWhere($idCond);

        if ($metadataCond = $this->getInvoiceMetadataQuery('i.id')) {
            $subselect1->andWhere($metadataCond);
            $subselect2->andWhere($metadataCond);
        }

        if ($tagCond = $this->getInvoiceTagsQuery('i.id')) {
            $subselect1->andWhere($tagCond);
            $subselect2->andWhere($tagCond);
        }

        return $this->database->createQueryBuilder()
            ->select('t.type, SUM(t.amount) AS gross')
            ->from('Transactions', 't')
            ->andWhere('t.tenant_id = :tenantId')
            ->setParameter('tenantId', $this->company->id())
            ->andWhere('t.currency = :currency')
            ->setParameter('currency', $this->currency)
            ->andWhere('t.status = "'.Transaction::STATUS_SUCCEEDED.'"')
            ->andWhere('t.date BETWEEN '.$this->start.' AND '.$this->end)
            ->andWhere('EXISTS ('.$subselect1->getSQL().') OR EXISTS ('.$subselect2->getSQL().')')
            ->groupBy('t.type');
    }

    /**
     * Builds the tax rows for a cash basis.
     */
    private function buildRowsCash(): array
    {
        // Merge invoice and line item tax data
        $lines = [
            ...$this->getQueryCashInvoicesTax()->fetchAllAssociative(),
            ...$this->getQueryCashLineItemsTax()->fetchAllAssociative()
        ];

        $rows = [];

        foreach ($lines as $line) {
            $rateId = $line['rate_id'] ?? '*';

            // build the row if it does not exist
            if (!isset($rows[$rateId])) {
                $rows[$rateId] = $this->buildRow($rateId);

                // get gross amount for tax rate
                $gross = $this->getQueryCashGrossForRate($rateId)->fetchAllAssociative();
                foreach ($gross as $line2) {
                    $grossAmount = $this->getGrossAmountCash($line2);
                    $rows[$rateId][1] = $rows[$rateId][1]->add($grossAmount);
                }
            }

            // increment row tax
            $rows[$rateId][2] = $rows[$rateId][2]->add($this->getTaxAmountCash($line));
        }

        return $rows;
    }

    /**
     * Gets the tax amount for a cash result.
     */
    private function getTaxAmountCash(array $row): Money
    {
        if (Transaction::TYPE_REFUND === $row['type']) {
            return Money::fromDecimal($this->currency, -$row['tax_amount']);
        }

        return Money::fromDecimal($this->currency, $row['tax_amount']);
    }

    /**
     * Gets the gross amount for a cash result.
     */
    private function getGrossAmountCash(array $row): Money
    {
        if (Transaction::TYPE_REFUND === $row['type']) {
            return Money::fromDecimal($this->currency, -$row['gross']);
        }

        return Money::fromDecimal($this->currency, $row['gross']);
    }
}
