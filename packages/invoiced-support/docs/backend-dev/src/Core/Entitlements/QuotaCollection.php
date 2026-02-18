<?php

namespace App\Core\Entitlements;

use App\Companies\Models\Company;
use App\Core\Entitlements\Enums\QuotaType;
use App\Core\Entitlements\Models\Quota;
use JsonSerializable;

class QuotaCollection implements JsonSerializable
{
    private static array $cache = [];

    public function __construct(private Company $company)
    {
    }

    /**
     * Gets the limit for a quota type.
     */
    public function get(QuotaType $type): ?int
    {
        $this->loadQuota();

        $result = self::$cache[$this->company->id][$type->value] ?? null;

        if (null !== $result) {
            return $result;
        }

        // Use default limit if a quota is not set on the company
        return $type->defaultLimit();
    }

    /**
     * Sets the limit for a quota type.
     */
    public function set(QuotaType $type, int $limit): void
    {
        $quota = Quota::queryWithTenant($this->company)
            ->where('quota_type', $type->value)
            ->oneOrNull();

        if (!$quota) {
            $quota = new Quota();
            $quota->quota_type = $type;
            $quota->tenant_id = (int) $this->company->id();
        }

        $quota->limit = $limit;
        $quota->saveOrFail();
        self::clearCache();
    }

    public function remove(QuotaType $type): void
    {
        $quota = Quota::queryWithTenant($this->company)
            ->where('quota_type', $type->value)
            ->oneOrNull();

        if ($quota) {
            $quota->delete();
            self::clearCache();
        }
    }

    /**
     * Gets all the quotas for an account.
     */
    public function all(): array
    {
        $quota = [];
        foreach (QuotaType::cases() as $type) {
            $quota[$type->value] = $this->get($type);
        }

        return $quota;
    }

    public function jsonSerialize(): array
    {
        return $this->all();
    }

    /**
     * Clears the local cache.
     */
    public static function clearCache(): void
    {
        self::$cache = [];
    }

    private function loadQuota(): void
    {
        $k = $this->company->id;
        if (!isset(self::$cache[$k])) {
            self::$cache[$k] = [];
            foreach (Quota::queryWithTenant($this->company)->all() as $quota) {
                self::$cache[$k][$quota->quota_type->value] = $quota->limit;
            }
        }
    }
}
