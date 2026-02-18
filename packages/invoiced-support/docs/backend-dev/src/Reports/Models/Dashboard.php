<?php

namespace App\Reports\Models;

use App\Companies\Models\Member;
use App\Core\Multitenant\Models\MultitenantModel;
use App\Core\Orm\ACLModelRequester;
use App\Core\Orm\Event\ModelCreating;
use App\Core\Orm\Property;
use App\Core\Orm\Traits\AutoTimestamps;
use App\Core\Orm\Type;

/**
 * @property int         $id
 * @property string      $name
 * @property object      $definition
 * @property bool        $private
 * @property Member|null $creator
 */
class Dashboard extends MultitenantModel
{
    use AutoTimestamps;

    protected static function getProperties(): array
    {
        return [
            'name' => new Property(
                required: true,
                validate: ['unique', 'column' => 'name'],
            ),
            'definition' => new Property(
                type: Type::OBJECT,
                required: true,
            ),
            'private' => new Property(
                type: Type::BOOLEAN,
                default: true,
            ),
            'creator' => new Property(
                null: true,
                belongs_to: Member::class,
            ),
            'creator_id' => new Property(
                type: Type::INTEGER,
                in_array: false,
            ),
        ];
    }

    protected function initialize(): void
    {
        parent::initialize();
        self::creating([self::class, 'setCreator']);
    }

    public static function setCreator(ModelCreating $event): void
    {
        /** @var self $dashboard */
        $dashboard = $event->getModel();
        $requester = ACLModelRequester::get();
        if ($requester instanceof Member) {
            $dashboard->creator = $requester;
        }
    }
}
