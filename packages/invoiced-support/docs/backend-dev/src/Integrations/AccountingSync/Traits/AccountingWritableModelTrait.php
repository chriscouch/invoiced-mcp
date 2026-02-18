<?php

namespace App\Integrations\AccountingSync\Traits;

use App\Integrations\AccountingSync\Interfaces\AccountingWritableModelInterface;
use App\Integrations\AccountingSync\WriteSync\AccountingWriteSpoolFacade;
use App\Core\Orm\Event\AbstractEvent;

trait AccountingWritableModelTrait
{
    protected bool $skipReconciliation = false;

    /**
     * Initialize listeners.
     */
    private function initializeAccountingIntegration(): void
    {
        self::created([static::class, 'writeToAccountingSystem']);
        self::updated([static::class, 'writeToAccountingSystem']);
        self::deleted([static::class, 'writeToAccountingSystem']);
    }

    /**
     * Writes models to the accounting system.
     */
    public static function writeToAccountingSystem(AbstractEvent $event, string $eventName): void
    {
        /** @var AccountingWritableModelInterface $model */
        $model = $event->getModel();

        if ($model->isReconcilable()) {
            AccountingWriteSpoolFacade::get()->enqueue($model, $eventName, $model->tenant());
        }
    }

    public function skipReconciliation(): void
    {
        $this->skipReconciliation = true;
    }

    public function isReconcilable(): bool
    {
        return !$this->skipReconciliation;
    }
}
