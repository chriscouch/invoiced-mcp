<?php

namespace App\Integrations\Textract\Libs;

use App\Core\Utils\AppUrl;
use App\Core\Utils\ModelLock;
use Symfony\Component\Lock\LockFactory;

/**
 * This class manages locking for Textract SNS. The lock can be used
 * to prevent duplicated requests.
 */
final class InvoiceCaptureLock extends ModelLock
{
    public function __construct(string $jobId, protected LockFactory $lockFactory)
    {
        $this->factory = $lockFactory;
        $namespace = AppUrl::get()->getHostname();
        $this->name = $namespace.':import_lock.'.$jobId;
    }
}
