<?php

namespace App\Service;

use Doctrine\DBAL\Connection;

class UsageReport
{
    public function __construct(private Connection $database)
    {
    }

    public function generate(int $billingProfileId): array
    {
        $tenantIds = $this->getTenantIds($billingProfileId);
        $lastMonth = (int) strtotime('-1 month');
        $lastYear = (int) strtotime('-11 months', $lastMonth);
        $start = (int) date('Ym', (int) mktime(0, 0, 0, (int) date('n', $lastYear), 1, (int) date('Y', $lastYear)));
        $end = (int) date('Ym', (int) mktime(0, 0, 0, (int) date('n', $lastMonth), 1, (int) date('Y', $lastMonth)));

        return [
            'numUsers' => $this->getNumUsers($tenantIds),
            'numEntities' => $this->getNumEntities($tenantIds),
            'billedVolume' => $this->getBilledVolume($tenantIds, $start, $end),
            'invoiceVolume' => $this->getInvoiceVolume($tenantIds, $start, $end),
            'customersVolume' => $this->getCustomersVolume($tenantIds, $start, $end),
        ];
    }

    private function getTenantIds(int $billingProfileId): array
    {
        $sql = 'SELECT id FROM Companies WHERE billing_profile_id=?';

        return $this->database->executeQuery($sql, [$billingProfileId])
            ->fetchFirstColumn();
    }

    private function getNumUsers(array $tenantIds): int
    {
        $sql = 'SELECT COUNT(DISTINCT user_id) FROM Members WHERE expires=0 AND '.$this->getTenantIdCondition($tenantIds);

        return $this->database->fetchOne($sql) ?? 0;
    }

    private function getNumEntities(array $tenantIds): int
    {
        $sql = 'SELECT COUNT(*) FROM Companies WHERE canceled=0 AND '.$this->getTenantIdCondition($tenantIds, 'id');

        return $this->database->fetchOne($sql) ?? 0;
    }

    private function getBilledVolume(array $tenantIds, int $start, int $end): int
    {
        $sql = 'SELECT SUM(count) FROM BilledVolumes WHERE month BETWEEN ? AND ? AND '.$this->getTenantIdCondition($tenantIds);

        return $this->database->fetchOne($sql, [$start, $end]) ?? 0;
    }

    private function getInvoiceVolume(array $tenantIds, int $start, int $end): int
    {
        $sql = 'SELECT SUM(count) FROM InvoiceVolumes WHERE month BETWEEN ? AND ? AND '.$this->getTenantIdCondition($tenantIds);

        return $this->database->fetchOne($sql, [$start, $end]) ?? 0;
    }

    private function getCustomersVolume(array $tenantIds, int $start, int $end): int
    {
        $sql = 'SELECT MAX(count) FROM CustomerVolumes WHERE month BETWEEN ? AND ? AND '.$this->getTenantIdCondition($tenantIds);

        return $this->database->fetchOne($sql, [$start, $end]) ?? 0;
    }

    private function getTenantIdCondition(array $tenantIds, string $column = 'tenant_id'): string
    {
        return $column.' IN ('.implode(',', $tenantIds).')';
    }
}
