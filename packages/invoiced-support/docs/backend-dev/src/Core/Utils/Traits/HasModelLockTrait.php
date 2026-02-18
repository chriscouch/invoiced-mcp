<?php

namespace App\Core\Utils\Traits;

use App\Core\LockFactoryFacade;
use App\Core\Utils\ModelLock;
use App\Core\Orm\Event\AbstractEvent;
use App\Core\Orm\Exception\ListenerException;

trait HasModelLockTrait
{
    private ModelLock $modelLock;

    /**
     * Installs the client_id property.
     */
    protected function autoInitializeModelLock(): void
    {
        // install the event listener for this model
        self::updating([static::class, 'lock'], 10000);
        self::updated([static::class, 'unlock'], -10000);
    }

    /**
     * Locks the model.
     */
    public static function lock(AbstractEvent $event): void
    {
        /* @var self $model */
        $model = $event->getModel();
        $model->modelLock = new ModelLock($model, LockFactoryFacade::get());
        // we lock model for 60 seconds
        if (!$model->modelLock->acquire(60)) {
            throw new ListenerException('This '.$model->getObjectName().' is currently being updated by another process. Please try again later.');
        }
    }

    /**
     * UnLocks the model.
     */
    public static function unlock(AbstractEvent $event): void
    {
        /* @var self $model */
        $model = $event->getModel();
        $model->modelLock->release();
    }
}
