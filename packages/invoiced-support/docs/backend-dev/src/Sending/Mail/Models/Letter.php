<?php

namespace App\Sending\Mail\Models;

use App\Core\Authentication\Models\User;
use App\Core\Multitenant\Models\MultitenantModel;
use App\Core\Orm\Property;
use App\Core\Orm\Traits\AutoTimestamps;
use App\Core\Orm\Type;
use App\Core\RestApi\Traits\ApiObjectTrait;
use App\ActivityLog\Interfaces\EventObjectInterface;
use App\ActivityLog\Traits\EventObjectTrait;
use App\Sending\Mail\Adapter\LobAdapter;

/**
 * @property string      $id
 * @property string      $state
 * @property string      $to
 * @property int         $num_pages
 * @property User|null   $sent_by
 * @property int|null    $expected_delivery_date
 * @property string|null $lob_id
 * @property int|null    $related_to_type
 * @property int|null    $related_to_id
 */
class Letter extends MultitenantModel implements EventObjectInterface
{
    use ApiObjectTrait;
    use AutoTimestamps;
    use EventObjectTrait;

    protected static function getProperties(): array
    {
        return [
            'id' => new Property(
                mutable: Property::MUTABLE_CREATE_ONLY,
                required: true,
            ),
            'state' => new Property(
                required: true,
            ),
            'to' => new Property(
                required: true,
            ),
            'num_pages' => new Property(
                type: Type::INTEGER,
                required: true,
            ),
            'sent_by' => new Property(
                null: true,
                belongs_to: User::class,
            ),
            'expected_delivery_date' => new Property(
                type: Type::DATE_UNIX,
                null: true,
            ),
            'lob_id' => new Property(
                null: true,
                in_array: false,
            ),
            'related_to_type' => new Property(
                type: Type::INTEGER,
                null: true,
                in_array: false,
            ),
            'related_to_id' => new Property(
                type: Type::INTEGER,
                null: true,
                in_array: false,
            ),
        ];
    }

    protected function getDetailValue(mixed $detail): array
    {
        if (is_array($detail)) {
            return $detail;
        }

        if ($this->lob_id) {
            $adapter = new LobAdapter();

            return $adapter->getDetail($this->lob_id);
        }

        return [];
    }
}
