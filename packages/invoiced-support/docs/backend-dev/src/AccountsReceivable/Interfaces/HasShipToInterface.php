<?php

namespace App\AccountsReceivable\Interfaces;

use App\AccountsReceivable\Models\ShippingDetail;

interface HasShipToInterface
{
    /**
     * presets ship to value to existing object.
     */
    public function hydrateShipTo(?ShippingDetail $details): void;

    /**
     * from Model.
     */
    public function id(): string|int|false;
}
