<?php

namespace App\Integrations\Adyen\Libs;

use App\Core\Utils\AppUrl;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Lock\LockInterface;

/**
 * This class manages locking for payment forms. The lock can be used
 * to prevent double-submissions on payments. Works with any document,
 * like invoices and estimates.
 */
final class AdyenPaymentResultLock
{
    const int WRITE_ADYEN_TTL = 30;
    private string $name;
    private LockInterface $lock;

    public function __construct(string $reference, protected LockFactory $factory)
    {
        $namespace = AppUrl::get()->getHostname();
        $namespace .= ':adyen_payment_result.';
        $this->name = $namespace.'.'.$reference;
    }

    public function acquire(float $expires): bool
    {
        // do not lock if expiry time is 0
        if ($expires <= 0) {
            return true;
        }

        $k = $this->getName();
        $this->lock = $this->factory->createLock($k, $expires, false);

        return $this->lock->acquire();
    }

    public function release(): void
    {
        if (!isset($this->lock)) {
            return;
        }

        $this->lock->release();
    }

    private function getName(): string
    {
        return $this->name;
    }
}
