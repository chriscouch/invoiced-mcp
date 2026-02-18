<?php

namespace App\Core\Multitenant;

use App\Companies\Models\Company;
use App\Core\Multitenant\Exception\MultitenantException;
use App\ActivityLog\Libs\EventSpool;
use App\Sending\Email\Libs\EmailSpool;

class TenantContext
{
    private ?Company $currentTenant = null;

    public function __construct(
        private EventSpool $eventSpool,
        private EmailSpool $emailSpool
    ) {
    }

    /**
     * Sets the current tenant.
     */
    public function set(Company $tenant): void
    {
        // Before changing the tenant context we want to flush
        // any spools that rely on the tenant context.
        if ($this->currentTenant && $tenant->id() != $this->currentTenant->id()) {
            // Email should be flushed before events because emails
            // can create events.
            $this->emailSpool->flush();
            $this->eventSpool->flush();
        }

        $this->currentTenant = $tenant;
    }

    /**
     * Runs a given function temporarily as the given tenant. This is
     * a helper method when it is necessary to temporarily jump into a
     * different tenant. The context will be reset to the previous value
     * after the function runs.
     */
    public function runAs(Company $tenant, callable $fn): void
    {
        $original = $this->currentTenant;

        // Run the callable with the temporary context
        $this->set($tenant);
        $fn();

        // Reset to original tenant
        if ($original) {
            $this->set($original);
        } else {
            $this->clear();
        }
    }

    /**
     * Checks if there is a current tenant.
     */
    public function has(): bool
    {
        return null !== $this->currentTenant;
    }

    /**
     * Gets the current tenant.
     *
     * @throws MultitenantException when there is no current tenant
     */
    public function get(): Company
    {
        if (null === $this->currentTenant) {
            throw new MultitenantException('Tried to retrieve the current tenant when none has been set.');
        }

        return $this->currentTenant;
    }

    /**
     * Clears the current tenant.
     */
    public function clear(): void
    {
        // Before clearing out the tenant context we want to flush
        // any spools that rely on the tenant context.
        if ($this->currentTenant) {
            // Email should be flushed before events because emails
            // can create events.
            $this->emailSpool->flush();
            $this->eventSpool->flush();
        }

        $this->currentTenant = null;
    }
}
