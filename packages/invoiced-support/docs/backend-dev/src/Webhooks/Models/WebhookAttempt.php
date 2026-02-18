<?php

namespace App\Webhooks\Models;

use App\Core\Multitenant\Models\MultitenantModel;
use App\Core\Queue\Queue;
use App\EntryPoint\QueueJob\WebhookJob;
use App\ActivityLog\Models\Event;
use App\Core\Orm\Property;
use App\Core\Orm\Query;
use App\Core\Orm\Traits\AutoTimestamps;
use App\Core\Orm\Type;

/**
 * @property int      $id
 * @property string   $url
 * @property string   $payload
 * @property int      $event_id
 * @property array    $attempts
 * @property int|null $next_attempt
 */
class WebhookAttempt extends MultitenantModel
{
    use AutoTimestamps;

    protected static function getProperties(): array
    {
        return [
            'url' => new Property(
                required: true,
                validate: 'url',
            ),
            'payload' => new Property(
                in_array: false,
            ),
            'event_id' => new Property(
                type: Type::INTEGER,
                mutable: Property::MUTABLE_CREATE_ONLY,
                required: true,
                relation: Event::class,
            ),
            'attempts' => new Property(
                type: Type::ARRAY,
            ),
            'next_attempt' => new Property(
                type: Type::DATE_UNIX,
                null: true,
            ),
        ];
    }

    public static function customizeBlankQuery(Query $query): Query
    {
        $query = parent::customizeBlankQuery($query);
        $query->sort('id DESC');

        return $query;
    }

    /**
     * Checks if the attempt has succeeded.
     */
    public function succeeded(): bool
    {
        if (!is_array($this->attempts) || 0 === count($this->attempts)) {
            return false;
        }

        foreach ($this->attempts as $attempt) {
            $code = array_value($attempt, 'status_code');
            if ($code >= 200 && $code < 300) {
                return true;
            }
        }

        return false;
    }

    /**
     * Queues the webhook attempt.
     */
    public function queue(Queue $queue): void
    {
        $queue->enqueue(WebhookJob::class, [
            'id' => $this->id(),
            'queued_at' => microtime(true),
            'tenant_id' => $this->tenant_id,
        ]);
    }
}
