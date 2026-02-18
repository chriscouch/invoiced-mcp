<?php

namespace App\Integrations\Intacct\Writers;

use App\Integrations\AccountingSync\Exceptions\SyncException;
use App\Integrations\AccountingSync\WriteSync\PaymentAccountMatcher;
use App\Integrations\AccountingSync\ValueObjects\PaymentRoute;
use App\Integrations\AccountingSync\ValueObjects\PaymentBankDecision;

/**
 * Decorator for PaymentAccountMatcher that adds entity ID filtering logic.
 * Filters mapping rules by entity ID before delegating to the matcher.
 *
 * This class is specific to Sage Intacct integration, allowing payment account
 * matching to consider entity-specific rules when routing payments.
 */
class EntityAwarePaymentAccountMatcher
{
    private array $rules;
    private ?string $entityId;

    public function __construct(array $rules, ?string $entityId = null)
    {
        $this->rules = $rules;
        $this->entityId = $entityId;
    }

    /**
     * Matches a payment to the appropriate account, filtering rules by entity ID.
     * If entity-specific rules exist, they are used; otherwise, generic rules are used.
     *
     * @param PaymentRoute $route Payment details for matching
     * @return PaymentBankDecision
     * @throws SyncException
     */
    public function match(PaymentRoute $route): PaymentBankDecision
    {
        $filteredRules = $this->filterByEntityId($this->rules, $this->entityId);
        $matcher = new PaymentAccountMatcher($filteredRules);
        return $matcher->match($route);
    }

    /**
     * Filters rules by entity ID.
     * If entity ID is provided and matching rules exist, returns those.
     * Otherwise, returns rules without entity ID.
     *
     * @param array $rules
     * @param string|null $entityId
     * @return array
     */
    private function filterByEntityId(array $rules, ?string $entityId): array
    {
        $entitySpecificRules = array_filter($rules, fn($rule) => isset($rule['entity_id']) && $rule['entity_id'] === $entityId);
        $genericRules = array_filter($rules, fn($rule) => !isset($rule['entity_id']));
        if ($entityId) {
            return !empty($entitySpecificRules) ? $entitySpecificRules : $genericRules;
        }
        return $genericRules;
    }
}