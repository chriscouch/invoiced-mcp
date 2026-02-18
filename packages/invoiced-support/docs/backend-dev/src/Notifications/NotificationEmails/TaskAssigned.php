<?php

namespace App\Notifications\NotificationEmails;

use App\Chasing\Models\Task;

class TaskAssigned extends AbstractNotificationEmail
{
    const THRESHOLD = 3;

    protected function getSubject(): string
    {
        return 'Task was assigned to you';
    }

    public function getVariables(array $events): array
    {
        if (count($events) > static::THRESHOLD) {
            return [
                'count' => count($events),
            ];
        }

        $ids = $this->getObjectIds($events);
        $items = Task::where('id IN ('.implode(',', $ids).')')
            ->with('customer')
            ->sort('id')->execute();

        $items = array_map(function (Task $item) {
            $res = $item->toArray();
            $res['customer'] = $item->customer?->toArray();

            return $res;
        }, $items);

        return [
            'tasks' => $items,
        ];
    }

    public function getTemplate(array $events): string
    {
        if (count($events) > static::THRESHOLD) {
            return 'notifications/task-bulk';
        }

        return 'notifications/task';
    }
}
