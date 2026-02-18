<?php

namespace App\Core\RestApi\SavedFilters\Models;

use App\Companies\Models\Member;
use App\Core\Multitenant\Models\MultitenantModel;
use App\Core\Orm\Event\AbstractEvent;
use App\Core\Orm\Exception\ListenerException;
use App\Core\Orm\Property;
use App\Core\Orm\Type;
use App\Core\Utils\Enums\ObjectType;

/**
 * @property int    $id
 * @property string $name
 * @property object $settings
 * @property int    $creator
 * @property int    $type
 * @property bool   $private
 */
class Filter extends MultitenantModel
{
    protected static function getProperties(): array
    {
        return [
            'name' => new Property(
                required: true,
                validate: ['string', 'min' => 1, 'max' => 255],
            ),
            'settings' => new Property(
                type: Type::OBJECT,
                required: true,
            ),
            'creator' => new Property(
                type: Type::INTEGER,
                mutable: Property::MUTABLE_CREATE_ONLY,
                required: true,
                relation: Member::class,
            ),
            'type' => new Property(
                type: Type::INTEGER,
                required: true,
            ),
            'private' => new Property(
                type: Type::BOOLEAN,
                default: false,
            ),
        ];
    }

    protected function initialize(): void
    {
        parent::initialize();

        self::creating([self::class, 'userLimit']);
        self::creating([self::class, 'companyLimit']);
        self::updating([self::class, 'companyLimitUpdate']);
    }

    public static function userLimit(AbstractEvent $event): void
    {
        /** @var self $model */
        $model = $event->getModel();
        if (Filter::where('creator', $model->creator)->count() > 50) {
            throw new ListenerException('You can not create more than 50 Saved Filters per user.');
        }
    }

    public static function companyLimit(): void
    {
        if (Filter::where('private', false)->count() > 50) {
            throw new ListenerException('You can not create more than 50 public Saved Filters per company.');
        }
    }

    public static function companyLimitUpdate(AbstractEvent $event): void
    {
        /** @var self $model */
        $model = $event->getModel();
        if ($model->ignoreUnsaved()->private && !$model->private) {
            self::companyLimit();
        }
    }

    public function toArray(): array
    {
        $result = parent::toArray();
        $result['type'] = ObjectType::from($this->type)->typeName();

        return $result;
    }
}
