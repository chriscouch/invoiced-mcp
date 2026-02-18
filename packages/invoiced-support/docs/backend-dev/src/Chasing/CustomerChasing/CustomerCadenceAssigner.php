<?php

namespace App\Chasing\CustomerChasing;

use App\AccountsReceivable\Models\Customer;
use App\Chasing\Models\ChasingCadence;
use App\Companies\Models\Company;
use Symfony\Component\ExpressionLanguage\ExpressionLanguage;
use Throwable;

/**
 * Assigns a cadence to a customer
 * according to the assignment rules in the account.
 */
class CustomerCadenceAssigner
{
    /** @var ChasingCadence[] */
    private array $conditionalCadences;
    private ?ChasingCadence $defaultCadence;
    private ExpressionLanguage $expressionLanguage;

    public function __construct(private Company $company)
    {
    }

    /**
     * Gets the cadence that should be assigned to a customer.
     */
    public function assign(Customer $customer): ?ChasingCadence
    {
        // must have feature flag to get an assigned cadence
        if (!$this->company->features->has('smart_chasing')) {
            return null;
        }

        // run through all conditional cadences
        // to see if there is a match
        $cadences = $this->getConditionalCadences();
        foreach ($cadences as $cadence) {
            if ($this->cadenceMatches($cadence, $customer)) {
                return $cadence;
            }
        }

        // if there is no match then return the default
        return $this->getDefaultCadence();
    }

    /**
     * Checks if a cadence matches a customer based
     * on its assignment conditions.
     */
    public function cadenceMatches(ChasingCadence $cadence, Customer $customer): bool
    {
        if (!isset($this->expressionLanguage)) {
            $this->expressionLanguage = new ExpressionLanguage();
        }

        try {
            return (bool) @$this->expressionLanguage->evaluate($cadence->assignment_conditions, [
                'customer' => (object) $customer->toArray(),
            ]);
        } catch (Throwable) {
            return false;
        }
    }

    /**
     * Gets the chasing cadences that have conditional
     * assignment rules.
     */
    private function getConditionalCadences(): array
    {
        if (!isset($this->conditionalCadences)) {
            $this->conditionalCadences = ChasingCadence::queryWithTenant($this->company)
                ->where('assignment_mode', ChasingCadence::ASSIGNMENT_MODE_CONDITIONAL)
                ->first(1000);
        }

        return $this->conditionalCadences;
    }

    /**
     * Gets the default chasing cadence, if there is one.
     */
    private function getDefaultCadence(): ?ChasingCadence
    {
        if (!isset($this->defaultCadence)) {
            $this->defaultCadence = ChasingCadence::queryWithTenant($this->company)
                ->where('assignment_mode', ChasingCadence::ASSIGNMENT_MODE_DEFAULT)
                ->oneOrNull();
        }

        return $this->defaultCadence;
    }
}
