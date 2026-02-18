<?php

namespace App\Notifications\Libs;

use App\Core\Queue\Queue;
use App\EntryPoint\QueueJob\NotificationEventJob;
use App\Notifications\Enums\NotificationEventType;
use App\Notifications\Models\NotificationEvent;
use Carbon\Carbon;

class NotificationSpool
{
    private const DUPLICATE_THRESHOLD = 5; // minutes
    /** @var array[] */
    private array $spool = [];

    public function __construct(private Queue $queue)
    {
    }

    public function __destruct()
    {
        $this->flush();
    }

    /**
     * Spools a notification event to be processed.
     *
     * @param int      $tenantId  - tenant id
     * @param int      $objectId  - object id
     * @param int|null $contextId - id of customer (subject) or user (receiver) of notification
     *
     * @return $this
     */
    public function spool(NotificationEventType $type, int $tenantId, int $objectId, ?int $contextId = null): self
    {
        $numExisting = NotificationEvent::where('type', $type->toInteger())
            ->where('object_id', $objectId)
            ->where('created_at', Carbon::now()->subMinutes(self::DUPLICATE_THRESHOLD)->toDateTimeString(), '>=')
            ->count();

        if (!$numExisting) {
            $this->spool[] = [
                'tenant_id' => $tenantId,
                'type' => $type,
                'objectId' => $objectId,
                'contextId' => $contextId,
            ];
        }

        return $this;
    }

    /**
     * Emits all notifications in the spool.
     */
    public function flush(): void
    {
        foreach ($this->spool as $params) {
            $this->queue->enqueue(NotificationEventJob::class, $params);
        }

        $this->spool = [];
    }

    /**
     * Erases all notifications in the spool.
     */
    public function clear(): void
    {
        $this->spool = [];
    }

    public function size(): int
    {
        return count($this->spool);
    }
}
