<?php

namespace App\Core\Queue\EventSubscriber;

use App\Companies\Models\Company;
use App\Core\Multitenant\TenantContext;
use App\Core\Queue\Events\AfterPerformEvent;
use App\Core\Queue\Events\BeforePerformEvent;
use App\Core\Queue\Events\OnFailureEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Sets the tenant context in resque jobs.
 */
class ResqueTenantSubscriber implements EventSubscriberInterface
{
    public function __construct(private TenantContext $tenant)
    {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            BeforePerformEvent::class => 'beforePerform',
            AfterPerformEvent::class => 'afterPerform',
            OnFailureEvent::class => 'onFailure',
        ];
    }

    public function beforePerform(BeforePerformEvent $event): void
    {
        // IMPORTANT: set the current tenant to enable multitenant operations
        $job = $event->job;
        $jobArgs = $job->getArguments();
        if (isset($jobArgs['tenant_id'])) {
            $company = Company::findOrFail($jobArgs['tenant_id']);
            $this->tenant->set($company);
        }
    }

    public function afterPerform(AfterPerformEvent $event): void
    {
        // IMPORTANT: clear the current tenant after we are done
        $jobArgs = $event->job->getArguments();
        if (isset($jobArgs['tenant_id'])) {
            $this->tenant->clear();
        }
    }

    /**
     * Submit metrics for a queue and job whenever a job fails to run.
     */
    public function onFailure(OnFailureEvent $event): void
    {
        // IMPORTANT: clear the current tenant after we are done
        $jobArgs = $event->getJob()->getArguments();
        if (isset($jobArgs['tenant_id'])) {
            $this->tenant->clear();
        }
    }
}
