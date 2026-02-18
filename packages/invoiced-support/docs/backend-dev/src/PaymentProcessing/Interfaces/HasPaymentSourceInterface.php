<?php

namespace App\PaymentProcessing\Interfaces;

use App\PaymentProcessing\Models\PaymentSource;

interface HasPaymentSourceInterface
{
    /**
     * presets payment source value to existing object.
     */
    public function setPaymentSource(PaymentSource $source): void;

    public function getPaymentSourceType(): ?string;

    public function getPaymentSourceId(): ?int;
}
