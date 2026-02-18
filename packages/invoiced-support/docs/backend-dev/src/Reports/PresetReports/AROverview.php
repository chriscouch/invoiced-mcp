<?php

namespace App\Reports\PresetReports;

use App\Core\I18n\ValueObjects\Money;
use App\Reports\ValueObjects\KeyValueGroup;
use App\Reports\ValueObjects\Section;

class AROverview extends AbstractReport
{
    public static function getId(): string
    {
        return 'a_r_overview';
    }

    protected function getName(): string
    {
        return 'A/R Overview';
    }

    protected function build(): void
    {
        $overview = new KeyValueGroup();

        /* Invoiced */

        $query = $this->database->createQueryBuilder()
            ->select('sum(total)')
            ->from('Invoices')
            ->andWhere('tenant_id = :tenantId')
            ->setParameter('tenantId', $this->company->id())
            ->andWhere('currency = :currency')
            ->setParameter('currency', $this->currency)
            ->andWhere('draft = 0')
            ->andWhere('voided = 0')
            ->andWhere('date BETWEEN '.$this->start.' AND '.$this->end);
        $this->addInvoiceMetadataQuery($query);
        $this->addInvoiceTagQuery($query);
        $invoicedD = (float) $query->fetchOne();
        $invoiced = Money::fromDecimal($this->currency, $invoicedD);
        $overview->addLine('Invoiced', $this->formatMoney($invoiced));

        /* Cash Collected */

        $collectedD = (float) $this->database->createQueryBuilder()
            ->select('sum(amount)')
            ->from('Payments')
            ->andWhere('tenant_id = :tenantId')
            ->setParameter('tenantId', $this->company->id())
            ->andWhere('currency = :currency')
            ->setParameter('currency', $this->currency)
            ->andWhere('voided = 0')
            ->andWhere('date BETWEEN '.$this->start.' AND '.$this->end)
            ->fetchOne();
        $collected = Money::fromDecimal($this->currency, $collectedD);

        /* Cash Collected (Legacy Transactions) */

        // NOTE the total received # includes balance charges
        $query = $this->database->createQueryBuilder()
            ->select('SUM(CASE WHEN `type`="refund" THEN -amount ELSE amount END)')
            ->from('Transactions')
            ->andWhere('tenant_id = :tenantId')
            ->setParameter('tenantId', $this->company->id())
            ->andWhere('currency = :currency')
            ->setParameter('currency', $this->currency)
            ->andWhere('status = "succeeded"')
            ->andWhere('type IN ("charge", "payment", "refund")')
            // Applied credits are not considered cash collected
            ->andWhere('(type <> "charge" OR method <> "balance")')
            ->andWhere('payment_id IS NULL')
            ->andWhere('date BETWEEN '.$this->start.' AND '.$this->end);
        $this->addInvoiceMetadataQuery($query, 'Transactions.invoice');
        $this->addInvoiceTagQuery($query, 'Transactions.invoice');
        $collectedLegacyD = (float) $query->fetchOne();
        $collectedLegacy = Money::fromDecimal($this->currency, $collectedLegacyD);

        $collected = $collected->add($collectedLegacy);
        $overview->addLine('Collected', $this->formatMoney($collected));

        /* Credited (Credit Notes) */

        $query = $this->database->createQueryBuilder()
            ->select('sum(total)')
            ->from('CreditNotes')
            ->andWhere('tenant_id = :tenantId')
            ->setParameter('tenantId', $this->company->id())
            ->andWhere('currency = :currency')
            ->setParameter('currency', $this->currency)
            ->andWhere('draft = 0')
            ->andWhere('voided = 0')
            ->andWhere('date BETWEEN '.$this->start.' AND '.$this->end);
        $this->addCreditNoteMetadataQuery($query, 'CreditNotes.id');
        $creditedD = (float) $query->fetchOne();

        if ($creditedD > 0) {
            $credited = Money::fromDecimal($this->currency, $creditedD)->negated();
            $overview->addLine('Credit Notes', $this->formatMoney($credited));
        }

        /* Bad Debt */

        $query = $this->database->createQueryBuilder()
            ->select('sum(amount_written_off)')
            ->from('Invoices')
            ->andWhere('tenant_id = :tenantId')
            ->setParameter('tenantId', $this->company->id())
            ->andWhere('currency = :currency')
            ->setParameter('currency', $this->currency)
            ->andWhere('draft = 0')
            ->andWhere('voided = 0')
            ->andWhere('paid = 0')
            ->andWhere('closed = 1')
            ->andWhere('date_bad_debt BETWEEN '.$this->start.' AND '.$this->end);
        $this->addInvoiceMetadataQuery($query);
        $this->addInvoiceTagQuery($query);
        $badDebt = (float) $query->fetchOne();
        $badDebt = Money::fromDecimal($this->currency, $badDebt);
        $overview->addLine('Bad Debt', $this->formatMoney($badDebt));

        /* # Invoices */

        $query = $this->database->createQueryBuilder()
            ->select('count(*)')
            ->from('Invoices')
            ->andWhere('tenant_id = :tenantId')
            ->setParameter('tenantId', $this->company->id())
            ->andWhere('currency = :currency')
            ->setParameter('currency', $this->currency)
            ->andWhere('draft = 0')
            ->andWhere('voided = 0')
            ->andWhere('date BETWEEN '.$this->start.' AND '.$this->end);
        $this->addInvoiceMetadataQuery($query);
        $this->addInvoiceTagQuery($query);
        $numInvoices = (int) $query->fetchOne();

        $query = $this->database->createQueryBuilder()
            ->select('count(DISTINCT customer)')
            ->from('Invoices')
            ->andWhere('tenant_id = :tenantId')
            ->setParameter('tenantId', $this->company->id())
            ->andWhere('currency = :currency')
            ->setParameter('currency', $this->currency)
            ->andWhere('draft = 0')
            ->andWhere('voided = 0')
            ->andWhere('date BETWEEN '.$this->start.' AND '.$this->end);
        $this->addInvoiceMetadataQuery($query);
        $this->addInvoiceTagQuery($query);
        $numCustomers = (int) $query->fetchOne();

        $numInvoices = number_format($numInvoices).' invoice'.((1 != $numInvoices) ? 's' : '');
        $numInvoices .= ' / '.number_format($numCustomers).' customer'.((1 != $numCustomers) ? 's' : '');
        $overview->addLine('# Invoices', $numInvoices);

        /* Days Sales Outstanding */

        $dso = 0;
        if ($invoicedD > 0) {
            // get the entire current A/R balance, not just for invoices issued during the period
            $query = $this->database->createQueryBuilder()
                ->select('sum(balance)')
                ->from('Invoices')
                ->andWhere('tenant_id = :tenantId')
                ->setParameter('tenantId', $this->company->id())
                ->andWhere('currency = :currency')
                ->setParameter('currency', $this->currency)
                ->andWhere('draft = 0')
                ->andWhere('paid = 0')
                ->andWhere('closed = 0')
                ->andWhere('voided = 0')
                ->andWhere('date <= '.$this->end);
            $this->addInvoiceMetadataQuery($query);
            $this->addInvoiceTagQuery($query);
            $currentOutstanding = (float) $query->fetchOne();

            // DSO = Accounts Receivable / Total Sales * Days In Period
            $daysInPeriod = round(($this->end - $this->start) / 86400);
            $dso = round($currentOutstanding / $invoicedD * $daysInPeriod);
        }
        $dso .= ' '.((1 == $dso) ? 'day' : 'days');
        $overview->addLine('Days Sales Outstanding', $dso);

        /* Collections Efficiency */

        $query = $this->database->createQueryBuilder()
            ->select('sum(balance)')
            ->from('Invoices')
            ->andWhere('tenant_id = :tenantId')
            ->setParameter('tenantId', $this->company->id())
            ->andWhere('currency = :currency')
            ->setParameter('currency', $this->currency)
            ->andWhere('draft = 0')
            ->andWhere('paid = 0')
            ->andWhere('closed = 0')
            ->andWhere('voided = 0')
            ->andWhere('date BETWEEN '.$this->start.' AND '.$this->end);
        $this->addInvoiceMetadataQuery($query);
        $this->addInvoiceTagQuery($query);
        $outstandingD = (float) $query->fetchOne();

        $collectionsEfficiency = 0;
        if ($invoicedD > 0) {
            $collectionsEfficiency = round((1 - $outstandingD / $invoicedD) * 100);
        }
        $collectionsEfficiency .= '%';
        $overview->addLine('Collections Efficiency', $collectionsEfficiency);

        $section = new Section('');
        $section->addGroup($overview);
        $this->report->addSection($section);
    }
}
