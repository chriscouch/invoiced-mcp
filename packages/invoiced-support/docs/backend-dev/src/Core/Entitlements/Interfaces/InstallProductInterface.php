<?php

namespace App\Core\Entitlements\Interfaces;

use App\Companies\Models\Company;
use App\Core\Entitlements\Exception\InstallProductException;

/**
 * Classes that implement this interface are responsible for
 * activating a specific product on a company.
 */
interface InstallProductInterface
{
    /**
     * Installs a product on a company.
     *
     * @throws InstallProductException
     */
    public function install(Company $company): void;
}
