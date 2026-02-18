<?php

namespace App\Core\RestApi\Traits;

use App\Core\Utils\Enums\ObjectType;

/**
 * @property string $object
 */
trait ApiObjectTrait
{
    protected bool $_noArrayHook = false;

    /**
     * Gets the `object` property.
     */
    protected function getObjectValue(): string
    {
        return ObjectType::fromModel($this)->typeName();
    }

    /**
     * Tells the model to skip toArrayHook().
     */
    public function withoutArrayHook(): void
    {
        $this->_noArrayHook = true;
    }

    public function toArray(): array
    {
        $result = parent::toArray();
        $result['object'] = $this->object;

        return $result;
    }
}
