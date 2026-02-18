<?php

namespace App\Core\Utils;

use App\Core\Utils\Enums\ObjectType;
use App\Core\Orm\Model;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Lock\LockInterface;

/**
 * This class manages locking on a particular model. The semantics
 * of the lock depend on the usage. When this lock is obtained then
 * it ensures that the concurrent processes cannot perform the same
 * operation against the model.
 */
class ModelLock
{
    protected string $name;
    protected LockInterface $lock;

    public function __construct(Model $model, protected LockFactory $factory, ?string $namespace = null)
    {
        $namespace ??= AppUrl::get()->getHostname().':';
        $modelName = ObjectType::fromModel($model)->typeName();
        $this->name = $namespace.$modelName.'.'.$model->id();
    }

    /**
     * Checks if this instance has the lock.
     */
    public function hasLock(): bool
    {
        return isset($this->lock) && $this->lock->isAcquired();
    }

    /**
     * Attempts to acquire the global lock for this object.
     *
     * @param float $expires time in seconds after which the lock expires
     */
    public function acquire(float $expires): bool
    {
        // do not lock if expiry time is 0
        if ($expires <= 0) {
            return true;
        }

        $k = $this->getName();
        $this->lock = $this->factory->createLock($k, $expires);

        return $this->lock->acquire();
    }

    /**
     * Releases the lock.
     */
    public function release(): void
    {
        if (!isset($this->lock)) {
            return;
        }

        $this->lock->release();
    }

    /**
     * Gets the name of this lock.
     */
    public function getName(): string
    {
        return $this->name;
    }
}
