<?php

namespace App\Service;

use App\Controller\Admin\AccountingSyncFieldMappingCrudController;
use App\Entity\Forms\MonthlyReportFilter;
use Carbon\CarbonImmutable;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DBALException;

class MonthlyReport
{
    private CarbonImmutable $start;
    private CarbonImmutable $end;

    public function __construct(
        private Connection $connection,
        private CurrencyConverter $currencyConverter
    ) {
    }

    /**
     * @throws DBALException
     */
    public function generate(MonthlyReportFilter $filter): mixed
    {
        $this->start = $filter->getStart();
        $this->end = $filter->getEnd();
        $metric = $filter->getMetric();

        return $this->$metric();
    }

    private function cancellationReasons(): array
    {
        return $this->connection->fetchAllAssociative('SELECT c.canceled_reason,COUNT(*) as n FROM Companies c JOIN BillingProfiles b ON b.id=c.billing_profile_id WHERE b.billing_system IS NOT NULL AND fraud=0 AND c.canceled_at BETWEEN ? AND ? GROUP BY canceled_reason', [$this->start->getTimestamp(), $this->end->getTimestamp()]);
    }

    private function totalInvoices(): int
    {
        $invoices = (int) $this->connection->fetchOne('SELECT COUNT(*) FROM Invoices WHERE draft=0 AND created_at BETWEEN ? AND ?', [$this->start->toDateTimeString(), $this->end->toDateTimeString()]);
        $creditNotes = (int) $this->connection->fetchOne('SELECT COUNT(*) FROM CreditNotes WHERE draft=0 AND created_at BETWEEN ? AND ?', [$this->start->toDateTimeString(), $this->end->toDateTimeString()]);

        return $invoices + $creditNotes;
    }

    private function timeToGoLive(): ?int
    {
        return $this->connection->fetchOne('SELECT ROUND(AVG(days_to_go_live)) AS ttgl
FROM (
    SELECT DATEDIFF(go_live_date, start_date) AS days_to_go_live
         FROM (
             SELECT MIN(start_date) AS start_date,MIN(go_live_date) AS go_live_date
                  FROM (
                      SELECT c.id,c.name,c.billing_profile_id,c.start_date,(
    SELECT created_at
                               FROM Invoices
                               WHERE tenant_id=c.id AND (sent=1 OR paid=1) AND voided=0 AND draft=0
                               ORDER BY id ASC
                               LIMIT 10, 1
                           ) AS go_live_date
                           FROM (
                               SELECT c1.id,c1.name,c1.billing_profile_id,c1.created_at AS start_date
                                    FROM Companies c1 JOIN BillingProfiles b ON b.id=c1.billing_profile_id
                                    WHERE c1.canceled=0 AND b.billing_system IS NOT NULL
                                ) c
                       ) c2
                  GROUP BY billing_profile_id
              ) c3
         WHERE DATEDIFF(NOW(), go_live_date) <= 365 AND go_live_date <= ?
     ) c4', [$this->end->toDateTimeString()]);
    }

    private function customersNotYetLive(): int
    {
        $end = $this->end->toDateTimeString();

        return $this->connection->fetchOne('SELECT COUNT(*) AS n
FROM (
    SELECT MIN(start_date) AS start_date,MIN(go_live_date) AS go_live_date
         FROM (
             SELECT c.id,c.name,c.billing_profile_id,c.start_date,(
    SELECT created_at
                      FROM Invoices
                      WHERE tenant_id=c.id AND (sent=1 OR paid=1) AND voided=0 AND draft=0
                      ORDER BY id ASC
                      LIMIT 10, 1
                  ) AS go_live_date
                  FROM (
                      SELECT c1.id,c1.name,c1.billing_profile_id,c1.created_at AS start_date
                                    FROM Companies c1 JOIN BillingProfiles b ON b.id=c1.billing_profile_id
                                    WHERE c1.canceled=0 AND b.billing_system IS NOT NULL
                       ) c
              ) c2
         GROUP BY billing_profile_id
     ) c3
WHERE (go_live_date IS NULL OR go_live_date > ?) AND start_date <= ?', [$end, $end]);
    }

    private function overageCharges(): array
    {
        $total = $this->connection->fetchAllAssociative('SELECT SUM(total) AS total,COUNT(*) AS count FROM OverageCharges WHERE `month` = ?', [$this->start->format('Ym')]);
        $byDimension = $this->connection->fetchAllAssociative('SELECT dimension,SUM(total) AS total,COUNT(*) AS count FROM OverageCharges WHERE `month` = ? GROUP BY dimension', [$this->start->format('Ym')]);

        return [
            'total' => $total[0]['total'],
            'count' => $total[0]['count'],
            'by_dimension' => $byDimension,
        ];
    }

    private function invoicedPaymentsVolume(): array
    {
        $results = $this->connection->fetchAllAssociative('SELECT currency,SUM(amount) AS total,SUM(IF(payment_source_type="bank_account", amount, 0)) AS total_ach,SUM(IF(payment_source_type="card", amount, 0)) AS total_card FROM Charges WHERE status<>"failed" AND gateway="invoiced" AND created_at BETWEEN ? and ? GROUP BY currency', [$this->start->toDateTimeString(), $this->end->toDateTimeString()]);

        $volume = 0;
        $achVolume = 0;
        $cardVolume = 0;
        foreach ($results as $row) {
            $volume += $this->currencyConverter->convert($row['currency'], $row['total'], 'USD');
            $achVolume += $this->currencyConverter->convert($row['currency'], $row['total_ach'], 'USD');
            $cardVolume += $this->currencyConverter->convert($row['currency'], $row['total_card'], 'USD');
        }

        return [
            'volume' => $volume,
            'ach_volume' => $achVolume,
            'card_volume' => $cardVolume,
            'new_merchants' => (int) $this->connection->fetchOne("SELECT COUNT(distinct tenant_id) AS created_merchants FROM MerchantAccounts WHERE gateway='invoiced' AND created_at BETWEEN ? AND ?", [$this->start->toDateTimeString(), $this->end->toDateTimeString()]),
            'active_merchants' => (int) $this->connection->fetchOne("SELECT COUNT(distinct tenant_id) AS active_merchants FROM Charges WHERE gateway='invoiced' AND status<>'failed' AND created_at BETWEEN ? AND ?", [$this->start->toDateTimeString(), $this->end->toDateTimeString()]),
        ];
    }

    private function totalPaymentsVolume(): array
    {
        $results = $this->connection->fetchAllAssociative("SELECT currency,SUM(amount) AS total,SUM(IF(payment_source_type='bank_account', amount, 0)) AS total_direct_debit,SUM(IF(payment_source_type='card', amount, 0)) AS total_card FROM Charges WHERE status<>'failed' AND created_at BETWEEN ? AND ? GROUP BY currency", [$this->start->toDateTimeString(), $this->end->toDateTimeString()]);

        $volume = 0;
        $directDebitVolume = 0;
        $cardVolume = 0;
        foreach ($results as $row) {
            $volume += $this->currencyConverter->convert($row['currency'], $row['total'], 'USD');
            $directDebitVolume += $this->currencyConverter->convert($row['currency'], $row['total_direct_debit'], 'USD');
            $cardVolume += $this->currencyConverter->convert($row['currency'], $row['total_card'], 'USD');
        }

        return [
            'volume' => $volume,
            'direct_debit_volume' => $directDebitVolume,
            'card_volume' => $cardVolume,
        ];
    }

    private function monthlyActiveUsers(): int
    {
        return $this->connection->fetchOne('SELECT COUNT(*) AS monthly_active_users FROM Members WHERE last_accessed >= ?', [$this->start->getTimestamp()]);
    }

    private function autoPayPayments(): int
    {
        return (int) $this->connection->fetchOne(
            "SELECT COUNT(*) AS autopay_payments FROM Payments WHERE `source`='autopay' AND `date` BETWEEN ? AND ?",
            [
                $this->start->getTimestamp(),
                $this->end->getTimestamp(),
            ]);
    }

    private function activePaymentPlans(): int
    {
        return (int) $this->connection->fetchOne(
            "SELECT COUNT(*) AS active_payment_plans FROM Invoices JOIN PaymentPlans PP on payment_plan_id = PP.id WHERE PP.status<>'canceled'"
        );
    }

    private function uniqueInvoiceViews(): int
    {
        return (int) $this->connection->fetchOne(
            'SELECT COUNT(*) FROM CustomerPortalEvents WHERE event IN (2,3,4) AND `timestamp` BETWEEN ? AND ?',
            [
                $this->start->toDateTimeString(),
                $this->end->toDateTimeString(),
            ]
        );
    }

    private function customerPortalLogins(): int
    {
        return (int) $this->connection->fetchOne(
            'SELECT COUNT(*) FROM CustomerPortalEvents WHERE event=1 AND `timestamp` BETWEEN ? AND ?',
            [
                $this->start->toDateTimeString(),
                $this->end->toDateTimeString(),
            ]
        );
    }

    private function paymentsApplied(): int
    {
        return (int) $this->connection->fetchOne(
            "SELECT COUNT(*) AS payments_applied FROM Payments WHERE applied=1 AND charge_id IS NULL AND source<>'accounting_system' AND `date` BETWEEN ? AND ?",
            [
                $this->start->getTimestamp(),
                $this->end->getTimestamp(),
            ]
        );
    }

    private function activeSubscriptions(): int
    {
        return (int) $this->connection->fetchOne(
            'SELECT COUNT(*) AS active_subscriptions FROM Subscriptions WHERE canceled=0 and finished=0'
        );
    }

    private function newNetworkConnections(): int
    {
        return (int) $this->connection->fetchOne(
            'SELECT COUNT(*) FROM NetworkConnections WHERE created_at BETWEEN ? AND ?',
            [
                $this->start->toDateTimeString(),
                $this->end->toDateTimeString(),
            ]
        );
    }

    private function networkDocumentsSent(): int
    {
        return (int) $this->connection->fetchOne(
            'SELECT COUNT(*) FROM NetworkDocuments WHERE created_at BETWEEN ? AND ?',
            [
                $this->start->toDateTimeString(),
                $this->end->toDateTimeString(),
            ]
        );
    }

    private function totalBills(): int
    {
        $bills = (int) $this->connection->fetchOne('SELECT COUNT(*) FROM Bills WHERE created_at BETWEEN ? AND ?', [$this->start->toDateTimeString(), $this->end->toDateTimeString()]);
        $vendorCredits = (int) $this->connection->fetchOne('SELECT COUNT(*) FROM VendorCredits WHERE created_at BETWEEN ? AND ?', [$this->start->toDateTimeString(), $this->end->toDateTimeString()]);

        return $bills + $vendorCredits;
    }

    private function billApprovals(): int
    {
        $bills = (int) $this->connection->fetchOne('SELECT COUNT(*) FROM BillApprovals WHERE created_at BETWEEN ? AND ?', [$this->start->toDateTimeString(), $this->end->toDateTimeString()]);
        $vendorCredits = (int) $this->connection->fetchOne('SELECT COUNT(*) FROM VendorCreditApprovals WHERE created_at BETWEEN ? AND ?', [$this->start->toDateTimeString(), $this->end->toDateTimeString()]);

        return $bills + $vendorCredits;
    }

    private function totalVendorPayments(): int
    {
        return (int) $this->connection->fetchOne('SELECT COUNT(*) FROM VendorPayments WHERE created_at BETWEEN ? AND ?', [$this->start->toDateTimeString(), $this->end->toDateTimeString()]);
    }

    private function totalNetworkSize(): int
    {
        return (int) $this->connection->fetchOne(
            'SELECT COUNT(*) FROM Companies WHERE canceled=0'
        );
    }

    private function trialFunnel(): array
    {
        $trialsStarted = (int) $this->connection->fetchOne(
            'SELECT COUNT(*) FROM Companies WHERE trial_started BETWEEN ? AND ?',
            [
                $this->start->getTimestamp(),
                $this->end->getTimestamp(),
            ]
        );

        $trialsActivated = (int) $this->connection->fetchOne(
            'SELECT COUNT(*) FROM Companies WHERE trial_started BETWEEN ? AND ? AND (EXISTS (SELECT 1 FROM Customers WHERE tenant_id=Companies.id) OR EXISTS (SELECT 1 FROM Vendors WHERE tenant_id=Companies.id))',
            [
                $this->start->getTimestamp(),
                $this->end->getTimestamp(),
            ]
        );

        $purchasePages = (int) $this->connection->fetchOne(
            'SELECT COUNT(*) FROM PurchasePageContexts WHERE reason = 2 AND sales_rep IS NULL AND created_at BETWEEN ? AND ?',
            [
                $this->start->toDateTimeString(),
                $this->end->toDateTimeString(),
            ]
        );

        $purchasesByProduct = $this->connection->fetchAllAssociative(
            'SELECT p.name, COUNT(DISTINCT tenant_id) AS num_companies, ROUND(SUM(IFNULL(IF(price.annual, price.price / 12, price), 0)), 2) AS mrr FROM Companies c JOIN ProductPricingPlans price ON price.tenant_id = c.id JOIN Products p ON p.id = price.product_id WHERE c.canceled = 0 AND c.trial_started IS NOT NULL AND EXISTS (SELECT 1 FROM PurchasePageContexts WHERE tenant_id = c.id AND reason = 2 AND completed_at BETWEEN ? AND ?) AND price.price > 0 GROUP BY p.id ORDER BY p.name',
            [
                $this->start->toDateTimeString(),
                $this->end->toDateTimeString(),
            ]
        );

        $purchasesBySource = $this->connection->fetchAllAssociative(
            'SELECT IFNULL(a.utm_source, "None") AS utm_source, COUNT(DISTINCT c.id) AS num_companies, ROUND(SUM(IFNULL(IF(price.annual, price.price / 12, price), 0)), 2) AS mrr FROM Companies c JOIN ProductPricingPlans price ON price.tenant_id = c.id LEFT JOIN MarketingAttributions a ON a.tenant_id = c.id WHERE c.canceled = 0 AND c.trial_started IS NOT NULL AND EXISTS (SELECT 1 FROM PurchasePageContexts WHERE tenant_id = c.id AND reason = 2 AND completed_at BETWEEN ? AND ?) AND price.price > 0 GROUP BY utm_source ORDER BY utm_source',
            [
                $this->start->toDateTimeString(),
                $this->end->toDateTimeString(),
            ]
        );

        $totalMrr = 0;
        $totalPurchases = 0;
        foreach ($purchasesByProduct as &$row) {
            $row['arr'] = $row['mrr'] * 12;
            $row['arpu'] = $row['num_companies'] > 0 ? $row['mrr'] / $row['num_companies'] : 0;
            $totalMrr += $row['mrr'];
            $totalPurchases += $row['num_companies'];
        }

        foreach ($purchasesBySource as &$row) {
            $row['arr'] = $row['mrr'] * 12;
            $row['arpu'] = $row['num_companies'] > 0 ? $row['mrr'] / $row['num_companies'] : 0;
        }

        return [
            'trialsStarted' => $trialsStarted,
            'trialsActivated' => $trialsActivated,
            'purchasePages' => $purchasePages,
            'purchasesByProduct' => $purchasesByProduct,
            'purchasesBySource' => $purchasesBySource,
            'totalPurchases' => $totalPurchases,
            'totalMrr' => $totalMrr,
            'totalArr' => $totalMrr * 12,
            'arpu' => $totalPurchases > 0 ? $totalMrr / $totalPurchases : 0,
        ];
    }

    private function integrationsInstalled(): array
    {
        $result = [];

        // New Style OAuth Integrations
        $oauthAccounts = $this->connection->fetchAllAssociative('SELECT integration,COUNT(*) AS total FROM OAuthAccounts GROUP BY integration');
        foreach ($oauthAccounts as $row) {
            $result[] = [
                'name' => array_search($row['integration'], AccountingSyncFieldMappingCrudController::INTEGRATION_CHOICES),
                'total' => $row['total'],
            ];
        }

        // Third-Party OAuth Applications
        $oauthAccessTokens = $this->connection->fetchAllAssociative('SELECT a.name,COUNT(*) AS total FROM OAuthApplicationAuthorizations t JOIN OAuthApplications a ON a.id=t.application_id GROUP BY t.application_id');
        foreach ($oauthAccessTokens as $row) {
            $result[] = [
                'name' => $row['name'],
                'total' => $row['total'],
            ];
        }

        // Every other integration
        $result[] = [
            'name' => 'Avalara',
            'total' => (int) $this->connection->fetchOne('SELECT COUNT(*) FROM AvalaraAccounts'),
        ];
        $result[] = [
            'name' => 'QuickBooks Online',
            'total' => (int) $this->connection->fetchOne('SELECT COUNT(*) FROM QuickBooksAccounts'),
        ];
        $result[] = [
            'name' => 'QuickBooks Desktop',
            'total' => (int) $this->connection->fetchOne('SELECT COUNT(*) FROM AccountingSyncProfiles WHERE integration=3'),
        ];
        $result[] = [
            'name' => 'Xero',
            'total' => (int) $this->connection->fetchOne('SELECT COUNT(*) FROM XeroAccounts'),
        ];
        $result[] = [
            'name' => 'NetSuite',
            'total' => (int) $this->connection->fetchOne('SELECT COUNT(*) FROM NetSuiteAccounts'),
        ];
        $result[] = [
            'name' => 'Sage Intacct',
            'total' => (int) $this->connection->fetchOne('SELECT COUNT(*) FROM IntacctAccounts'),
        ];
        $result[] = [
            'name' => 'ChartMogul',
            'total' => (int) $this->connection->fetchOne('SELECT COUNT(*) FROM ChartMogulAccounts'),
        ];
        $result[] = [
            'name' => 'Earth Class Mail',
            'total' => (int) $this->connection->fetchOne('SELECT COUNT(*) FROM EarthClassMailAccounts'),
        ];
        $result[] = [
            'name' => 'Lob',
            'total' => (int) $this->connection->fetchOne('SELECT COUNT(*) FROM LobAccounts'),
        ];
        $result[] = [
            'name' => 'Slack',
            'total' => (int) $this->connection->fetchOne('SELECT COUNT(*) FROM SlackAccounts'),
        ];
        $result[] = [
            'name' => 'Twilio',
            'total' => (int) $this->connection->fetchOne('SELECT COUNT(*) FROM TwilioAccounts'),
        ];
        $result[] = [
            'name' => 'Plaid',
            'total' => (int) $this->connection->fetchOne('SELECT COUNT(*) FROM CashApplicationBankAccounts'),
        ];
        $result[] = [
            'name' => 'Sign in with Google',
            'total' => (int) $this->connection->fetchOne('SELECT COUNT(*) FROM Users WHERE google_claimed_id IS NOT NULL'),
        ];
        $result[] = [
            'name' => 'Sign in with Intuit',
            'total' => (int) $this->connection->fetchOne('SELECT COUNT(*) FROM Users WHERE intuit_claimed_id IS NOT NULL'),
        ];
        $result[] = [
            'name' => 'Sign in with Microsoft',
            'total' => (int) $this->connection->fetchOne('SELECT COUNT(*) FROM Users WHERE microsoft_claimed_id IS NOT NULL'),
        ];
        $result[] = [
            'name' => 'Sign in with Xero',
            'total' => (int) $this->connection->fetchOne('SELECT COUNT(*) FROM Users WHERE xero_claimed_id IS NOT NULL'),
        ];

        // Payment Gateways
        $oauthAccessTokens = $this->connection->fetchAllAssociative('SELECT a.gateway,COUNT(distinct a.tenant_id) AS total FROM MerchantAccounts a WHERE a.deleted=0 AND a.gateway<>"test" GROUP BY a.gateway');
        foreach ($oauthAccessTokens as $row) {
            $result[] = [
                'name' => 'Payment Gateway: '.$row['gateway'],
                'total' => $row['total'],
            ];
        }

        usort($result, fn (array $a, array $b) => $a['name'] <=> $b['name']);

        $total = 0;
        foreach ($result as $row) {
            $total += $row['total'];
        }

        return [
            'total' => $total,
            'by_integration' => $result,
        ];
    }

    private function industries(): array
    {
        return $this->connection->fetchAllAssociative(
            'SELECT c.industry, COUNT(*) AS num_companies, ROUND(SUM(IFNULL(IF(price.annual, price.price / 12, price), 0))) AS mrr FROM Companies c JOIN ProductPricingPlans price ON price.tenant_id = c.id WHERE c.canceled = 0 AND c.industry IS NOT NULL AND c.created_at <= ? GROUP BY c.industry ORDER BY mrr DESC',
            [
                $this->end->toDateTimeString(),
            ]
        );
    }
}
