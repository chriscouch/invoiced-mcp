<?php

namespace App\Core\Entitlements;

use Doctrine\DBAL\Connection;

class FeatureManagement
{
    public function __construct(private Connection $database)
    {
    }

    /**
     * Checks if a feature flag is protected from rollouts.
     * The feature must start with "grad." in order to support
     * gradual rollout.
     */
    public function isProtected(string $feature): bool
    {
        return !str_starts_with($feature, 'grad.');
    }

    /**
     * Gets feature flag usage.
     */
    public function getFeatureUsage(string $feature): int
    {
        return (int) $this->database->fetchOne('SELECT COUNT(*) FROM Features WHERE feature=?', [$feature]);
    }

    /**
     * Enables a feature flag for N companies.
     */
    public function enableFeature(string $feature, int $n): void
    {
        // Get N random companies that do not have the feature flag
        $companies = $this->database->fetchFirstColumn('SELECT id FROM Companies WHERE NOT EXISTS (SELECT 1 FROM Features WHERE tenant_id=Companies.id AND feature=?) ORDER BY RAND() LIMIT 0,'.$n, [$feature]);

        // And add a feature flag
        foreach ($companies as $company) {
            $this->database->executeStatement('INSERT INTO Features (tenant_id, feature, enabled) VALUES (?, ?, ?)', [$company, $feature, true]);
        }

        // Clear feature cache
        FeatureCollection::clearCache();
    }

    /**
     * Disables a feature flag for N companies.
     */
    public function disableFeature(string $feature, int $n): void
    {
        // Get N random feature flags
        $features = $this->database->fetchFirstColumn('SELECT id FROM Features WHERE feature=? ORDER BY RAND() LIMIT 0,'.$n, [$feature]);

        // And delete
        if (count($features) > 0) {
            $this->database->executeStatement('DELETE FROM Features WHERE id IN ('.join(',', array_fill(0, count($features), '?')).')', $features);
        }

        // Clear feature cache
        FeatureCollection::clearCache();
    }
}
