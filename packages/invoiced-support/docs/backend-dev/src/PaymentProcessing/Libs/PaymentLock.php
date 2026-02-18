<?php

namespace App\PaymentProcessing\Libs;

use App\Core\Utils\AppUrl;
use App\Core\Utils\ModelLock;
use App\AccountsReceivable\Models\ReceivableDocument;
use Symfony\Component\Lock\LockFactory;

/**
 * This class manages locking for payment forms. The lock can be used
 * to prevent double-submissions on payments. Works with any document,
 * like invoices and estimates.
 */
final class PaymentLock extends ModelLock
{
    public function __construct(ReceivableDocument $document, LockFactory $lockFactory, ?string $namespace = null)
    {
        $namespace ??= AppUrl::get()->getHostname();
        $namespace .= ':payment_lock.';
        parent::__construct($document, $lockFactory, $namespace);
    }
}
