<?php

namespace App\Imports\Models;

use App\Core\Authentication\Libs\UserContextFacade;
use App\Core\Authentication\Models\User;
use App\Core\Multitenant\Models\MultitenantModel;
use App\Core\Orm\Event\AbstractEvent;
use App\Core\Orm\Property;
use App\Core\Orm\Query;
use App\Core\Orm\Traits\AutoTimestamps;
use App\Core\Orm\Type;
use App\Core\RestApi\Traits\ApiObjectTrait;
use App\ActivityLog\Interfaces\EventObjectInterface;
use App\ActivityLog\Traits\EventObjectTrait;

/**
 * @property int         $id
 * @property string      $name
 * @property string      $status
 * @property int|null    $user_id
 * @property string      $type
 * @property int         $position
 * @property int         $total_records
 * @property string      $message
 * @property int         $num_imported
 * @property int         $num_updated
 * @property int         $num_failed
 * @property array|null  $failure_detail
 * @property string|null $source_file
 */
class Import extends MultitenantModel implements EventObjectInterface
{
    use ApiObjectTrait;
    use EventObjectTrait;
    use AutoTimestamps;

    const MAX_INACTIVE_TIME = 300; // 5 minutes

    const SUCCEEDED = 'succeeded';
    const PENDING = 'pending';
    const FAILED = 'failed';

    private int $lastPositionUpdate = 0;

    protected static function getProperties(): array
    {
        return [
            'name' => new Property(
                required: true,
            ),
            'status' => new Property(
                required: true,
                validate: ['enum', 'choices' => ['succeeded', 'pending', 'failed']],
                default: self::PENDING,
            ),
            'user_id' => new Property(
                type: Type::INTEGER,
                mutable: Property::MUTABLE_CREATE_ONLY,
                null: true,
                relation: User::class,
            ),
            'user' => new Property(
                relation: User::class,
                local_key: 'user_id',
            ),
            'type' => new Property(
                required: true,
            ),
            'position' => new Property(
                type: Type::INTEGER,
            ),
            'total_records' => new Property(
                type: Type::INTEGER,
                null: true,
            ),
            'message' => new Property(),
            'num_imported' => new Property(
                type: Type::INTEGER,
            ),
            'num_updated' => new Property(
                type: Type::INTEGER,
            ),
            'num_failed' => new Property(
                type: Type::INTEGER,
            ),
            'failure_detail' => new Property(
                type: Type::ARRAY,
                null: true,
            ),
            'source_file' => new Property(
                null: true,
                in_array: false,
            ),
        ];
    }

    protected function initialize(): void
    {
        // when creating set the operation user to the current user
        self::creating(function (AbstractEvent $event): void {
            /** @var self $model */
            $model = $event->getModel();

            if (!$model->user_id) {
                $model->user_id = (int) UserContextFacade::get()->get()?->id;
            }
        });

        parent::initialize();
    }

    public static function customizeBlankQuery(Query $query): Query
    {
        return $query->sort('id DESC');
    }

    //
    // Accessors
    //

    protected function getUserValue(): mixed
    {
        return $this->user_id;
    }

    //
    // Relationships
    //

    /**
     * Gets the user.
     */
    public function user(): ?User
    {
        return $this->relation('user_id');
    }

    //
    // Helpers
    //

    /**
     * Increments the total # of records being synced.
     *
     * @throws \Exception if the update fails
     */
    public function incrementTotalRecords(int $n): void
    {
        $this->total_records += $n;

        if (!$this->save()) {
            throw new \Exception('Could not increment total records of '.static::modelName());
        }

        $this->lastPositionUpdate = time();
    }

    /**
     * Increments the position of the operation.
     *
     * @throws \Exception if the update fails
     */
    public function incrementPosition(int $n = 1): void
    {
        $this->position += $n;

        // only save periodically (every 1s)
        if (time() - $this->lastPositionUpdate < 1) {
            return;
        }

        if (!$this->save()) {
            throw new \Exception('Could not increment position of '.static::modelName());
        }

        $this->lastPositionUpdate = time();
    }

    /**
     * Increments the status of the operation.
     *
     * @throws \Exception if the update fails
     */
    public function updateMessage(string $message): void
    {
        $this->message = $message;

        // only save periodically (every 1s)
        if (time() - $this->lastPositionUpdate < 1) {
            return;
        }

        if (!$this->save()) {
            throw new \Exception('Could not update message of '.static::modelName());
        }

        $this->lastPositionUpdate = time();
    }
}
