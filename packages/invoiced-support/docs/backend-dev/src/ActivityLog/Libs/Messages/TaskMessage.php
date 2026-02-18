<?php

namespace App\ActivityLog\Libs\Messages;

use App\ActivityLog\ValueObjects\AttributedString;

class TaskMessage extends BaseMessage
{
    protected function taskCreated(): array
    {
        $name = array_value($this->object, 'name');
        if (!$name) {
            $name = $this->object['action'] ?? '';
        }

        return [
            new AttributedString('Task for '),
            $this->customer('customerName'),
            new AttributedString(' was created: '.$name),
        ];
    }

    protected function taskUpdated(): array
    {
        $name = array_value($this->object, 'name');
        if (!$name) {
            $name = $this->object['action'] ?? '';
        }

        return [
            new AttributedString('Task for '),
            $this->customer('customerName'),
            new AttributedString(' was updated: '.$name),
        ];
    }

    protected function taskDeleted(): array
    {
        $name = array_value($this->object, 'name');
        if (!$name) {
            $name = $this->object['action'] ?? '';
        }

        return [
            new AttributedString('Task for '),
            $this->customer('customerName'),
            new AttributedString(' was deleted: '.$name),
        ];
    }

    protected function taskCompleted(): array
    {
        $name = array_value($this->object, 'name');
        if (!$name) {
            $name = $this->object['action'] ?? '';
        }

        return [
            new AttributedString('Task completed for '),
            $this->customer('customerName'),
            new AttributedString(': '.$name),
        ];
    }
}
