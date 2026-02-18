<?php

namespace App\Exports\Models;

use App\Core\Authentication\Libs\UserContextFacade;
use App\Core\Authentication\Models\User;
use App\Core\Mailer\Mailer;
use App\Core\Multitenant\Models\MultitenantModel;
use App\Core\Orm\Event\AbstractEvent;
use App\Core\Orm\Property;
use App\Core\Orm\Traits\AutoTimestamps;
use App\Core\Orm\Type;

/**
 * @property int         $id
 * @property string      $name
 * @property string      $status
 * @property int|null    $user_id
 * @property string      $type
 * @property int         $position
 * @property int         $total_records
 * @property string      $message
 * @property string|null $download_url
 */
class Export extends MultitenantModel
{
    use AutoTimestamps;

    const MAX_EXECUTION_TIME = 600; // 10 minutes

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
            'download_url' => new Property(
                null: true,
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

    /**
     * Notifies the user that this import has finished.
     */
    public function notify(Mailer $mailer): void
    {
        $user = $this->user();

        // the user might not exist because it was not provided
        // or else the job has already been deleted
        if (!$user) {
            return;
        }

        $succeeded = self::SUCCEEDED === $this->status;
        $subject = 'Your "'.$this->name.'" export '.(($succeeded) ? 'finished' : 'failed');

        $mailer->sendToUser($user, [
            'subject' => $subject,
            ], 'export-finished', [
            'company' => $this->tenant()->name,
            'name' => $user->first_name,
            'exportName' => $this->name,
            'date' => date('M j, Y', $this->created_at),
            'succeeded' => $succeeded,
            'downloadLink' => explode(';', (string) $this->download_url),
        ]);
    }
}
