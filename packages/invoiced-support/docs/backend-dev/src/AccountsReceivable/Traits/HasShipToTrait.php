<?php

namespace App\AccountsReceivable\Traits;

use App\AccountsReceivable\Models\ShippingDetail;
use App\Core\Orm\Event\AbstractEvent;
use App\Core\Orm\Event\ModelUpdated;
use App\Core\Orm\Exception\ListenerException;

/**
 * @property ShippingDetail|null $ship_to
 */
trait HasShipToTrait
{
    private ?ShippingDetail $shipTo = null;
    private bool $deleteShipTo = false;
    private bool $isPersistedShipTo = false;
    private ?ShippingDetail $persistedShipTo;

    protected function autoInitializeShipTo(): void
    {
        self::created([self::class, 'modelSavedHandlerShipTo']);
        self::updated([self::class, 'modelSavedHandlerShipTo']);
    }

    public static function modelSavedHandlerShipTo(AbstractEvent $modelEvent, string $eventName): void
    {
        $isUpdate = ModelUpdated::getName() == $eventName;
        $modelEvent->getModel()->saveShipTo($isUpdate);
    }

    /**
     * Saves the attached shipping detail.
     *
     * @throws ListenerException
     */
    private function saveShipTo(bool $isUpdate): void
    {
        $parentKey = $this->getShipToParentKey();

        if ($this->deleteShipTo) {
            self::getDriver()->getConnection(null)->delete('ShippingDetails', [
                'tenant_id' => $this->tenant_id,
                $parentKey => $this->id(),
            ]);

            return;
        }

        if (!$this->shipTo instanceof ShippingDetail) {
            return;
        }

        $this->shipTo->$parentKey = $this->id();

        if (!$this->shipTo->save()) {
            throw new ListenerException('Could not save ship_to: '.$this->shipTo->getErrors(), ['field' => 'ship_to']);
        }

        $this->shipTo = null;
    }

    protected function setShipToValue(mixed $shipTo): ?ShippingDetail
    {
        if (null === $shipTo && $this->persisted()) {
            $this->deleteShipTo = true;
        } elseif (is_array($shipTo)) {
            // look for an existing shipping address
            $shipTo2 = null;
            if ($this->persisted()) {
                $parentKey = $this->getShipToParentKey();
                $shipTo2 = ShippingDetail::where($parentKey, $this->id())->oneOrNull();
            }

            if (!$shipTo2) {
                $shipTo2 = new ShippingDetail();
            }

            // convert array input to an object
            foreach ($shipTo as $k => $v) {
                $shipTo2->$k = $v;
            }
            $shipTo = $shipTo2;
        }

        $this->shipTo = $shipTo;

        return $shipTo;
    }

    public function hydrateShipTo(?ShippingDetail $details): void
    {
        $this->persistedShipTo = $details;
        $this->isPersistedShipTo = true;
    }

    protected function getShipToValue(mixed $shipTo): ?ShippingDetail
    {
        if ($this->isPersistedShipTo) {
            return $this->persistedShipTo;
        }

        if (!$this->id() || $shipTo) {
            return $shipTo instanceof ShippingDetail ? $shipTo : null;
        }

        $parentKey = $this->getShipToParentKey();

        return ShippingDetail::where($parentKey, $this->id())->oneOrNull();
    }

    private function getShipToParentKey(): string
    {
        return $this->object.'_id';
    }
}
