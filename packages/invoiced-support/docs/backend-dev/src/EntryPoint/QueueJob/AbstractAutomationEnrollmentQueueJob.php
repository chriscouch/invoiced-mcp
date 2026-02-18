<?php

namespace App\EntryPoint\QueueJob;

use App\Automations\Models\AutomationWorkflow;
use App\Core\ListQueryBuilders\ListQueryBuilderFactory;
use App\Core\Multitenant\Interfaces\TenantAwareQueueJobInterface;
use App\Core\Multitenant\Models\MultitenantModel;
use App\Core\Orm\Iterator;
use App\Core\Queue\AbstractResqueJob;
use App\Core\Queue\Interfaces\MaxConcurrencyInterface;

abstract class AbstractAutomationEnrollmentQueueJob extends AbstractResqueJob implements TenantAwareQueueJobInterface, MaxConcurrencyInterface
{
    public function __construct(
        private readonly ListQueryBuilderFactory $factory
    ) {
    }

    /**
     * @return Iterator|MultitenantModel[]
     */
    public function getModels(AutomationWorkflow $workflow, array $options): Iterator|array
    {
        $modelClass = $workflow->object_type->modelClass();
        // Parse the advanced filter input if given.
        $listQueryBuilder = $this->factory->get($modelClass, $workflow->tenant(), $options);
        $listQueryBuilder->initialize();

        return $listQueryBuilder->getBuildQuery()->all();
    }

    public static function getMaxConcurrency(array $args): int
    {
        return 1;
    }

    public static function getConcurrencyTtl(array $args): int
    {
        return 3600;
    }

    public static function delayAtConcurrencyLimit(): bool
    {
        return true;
    }
}
