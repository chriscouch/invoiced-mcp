<?php

namespace App\CustomerPortal\Interfaces;

use App\CustomerPortal\Libs\CustomerPortal;

interface CardInterface
{
    public function getData(CustomerPortal $customerPortal): array;
}
