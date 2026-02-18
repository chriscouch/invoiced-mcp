<?php

namespace App\AccountsPayable\Search;

use App\AccountsPayable\Models\Vendor;
use App\Core\Search\Interfaces\SearchDocumentInterface;

class VendorSearchDocument implements SearchDocumentInterface
{
    public function __construct(private Vendor $vendor)
    {
    }

    public function toSearchDocument(): array
    {
        return [
            'name' => $this->vendor->name,
            'number' => $this->vendor->number,
            '_vendor' => $this->vendor->id(),
        ];
    }
}
