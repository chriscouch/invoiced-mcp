<?php

namespace App\CustomerPortal\Libs;

use RuntimeException;

class CustomerPortalContext
{
    private ?CustomerPortal $portal = null;

    public function get(): ?CustomerPortal
    {
        return $this->portal;
    }

    public function getOrFail(): CustomerPortal
    {
        if (!$this->portal) {
            throw new RuntimeException('Customer portal context is not set');
        }

        return $this->portal;
    }

    public function set(CustomerPortal $customerPortal): void
    {
        $this->portal = $customerPortal;
    }
}
