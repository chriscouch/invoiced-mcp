<?php

namespace App\Core\Multitenant;

use App\Core\Multitenant\Exception\MultitenantException;
use App\Core\Multitenant\Models\MultitenantModel;
use App\Core\Orm\Model;
use App\Core\Orm\Event\AbstractEvent;

class MultitenantModelListener
{
    const LISTENER_PRIORITY = 500;

    private static ?self $listener = null;

    /**
     * Handles the model creating event.
     *
     * @throws MultitenantException when the tenancy constraints are violated
     */
    public function onCreating(AbstractEvent $event): void
    {
        /** @var MultitenantModel $model */
        $model = $event->getModel();
        $tenant = TenantContextFacade::get();

        if ($tenant->has()) {
            // If a tenant was already specified on the model
            // and that does not match the current tenant then
            // there is either a coding mistake or something
            // malicious happening.
            $currentTenant = $tenant->get();
            if ($model->tenant_id && $currentTenant->id() != $model->tenant_id) {
                throw new MultitenantException('Tried to save '.$model::modelName().' for a tenant (# '.$model->tenant_id.') different than the current tenant (# '.$currentTenant->id().')!');
            }

            // Set the model's tenant to the current tenant
            $model->tenant_id = (int) $currentTenant->id();
        }

        // throw an exception if the tenant was not
        // set on the model, whether through the DI container
        // or explicitly given
        if (!$model->tenant_id) {
            throw new MultitenantException('Attempted to save '.$model::modelName().' without specifying a tenant or setting the current tenant on the DI container!');
        }
    }

    /**
     * Installs the event listeners on a model.
     */
    public static function add(Model $model): void
    {
        if (!self::$listener) {
            self::$listener = new self();
        }

        $model::creating([self::$listener, 'onCreating'], self::LISTENER_PRIORITY);
    }
}
