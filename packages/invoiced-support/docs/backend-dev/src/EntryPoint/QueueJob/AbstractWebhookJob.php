<?php

namespace App\EntryPoint\QueueJob;

use App\Core\Multitenant\TenantContext;
use App\Core\Queue\AbstractResqueJob;
use App\Core\Queue\Interfaces\MaxConcurrencyInterface;
use App\Integrations\Interfaces\WebhookHandlerInterface;

abstract class AbstractWebhookJob extends AbstractResqueJob implements MaxConcurrencyInterface
{
    public function __construct(private TenantContext $tenant)
    {
    }

    public function perform(): void
    {
        // messy hack to convert an object to an array
        $event = json_decode((string) json_encode($this->args['event']), true);

        // 1. validate the webhook
        $handler = $this->getWebhookHandler();
        if (!$handler->shouldProcess($event)) {
            return;
        }

        // 2. find the companies that need to receive the webhook
        $companies = $handler->getCompanies($event);

        // 3. process the webhook for each company
        foreach ($companies as $company) {
            // check if the company is in good standing
            if (!$company->billingStatus()->isActive()) {
                continue;
            }

            // IMPORTANT: set the current tenant to enable multitenant operations
            $this->tenant->set($company);

            $handler->process($company, $event);

            // IMPORTANT: clear the current tenant after we are done
            $this->tenant->clear();
        }
    }

    /**
     * Gets the webhook handler.
     */
    abstract public function getWebhookHandler(): WebhookHandlerInterface;
}
