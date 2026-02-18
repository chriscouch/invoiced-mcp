<?php

namespace App\Imports\Libs;

use App\Companies\Models\Company;
use App\Core\Utils\AppUrl;
use App\Core\Utils\ModelLock;
use Symfony\Component\Lock\LockFactory;

/**
 * This class manages locking for imports. The lock can be used
 * to prevent the same import job from running concurrently.
 */
final class ImportLock extends ModelLock
{
    public function __construct(LockFactory $lockFactory, Company $tenant, string $name, ?string $namespace = null)
    {
        $this->factory = $lockFactory;
        $namespace ??= AppUrl::get()->getHostname();
        $this->name = $namespace.':import_lock.'.$tenant->id().'_'.$name;
    }
}
