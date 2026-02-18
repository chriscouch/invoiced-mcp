<?php

namespace App\Sending\Email\Models;

use App\Core\Authentication\Models\User;
use App\Core\Multitenant\Models\MultitenantModel;
use App\Core\Orm\Property;
use App\Core\Orm\Traits\AutoTimestamps;
use App\Core\Orm\Type;

/**
 * An EmailThreadNote represents a collection of InboxThreadNotes.
 *
 * @property int         $id
 * @property int         $tenant_id
 * @property EmailThread $thread
 * @property int         $thread_id
 * @property User        $user
 * @property int|null    $user_id
 * @property string      $note
 */
class EmailThreadNote extends MultitenantModel
{
    use AutoTimestamps;

    protected static function getProperties(): array
    {
        return [
            'thread' => new Property(
                required: true,
                belongs_to: EmailThread::class,
            ),
            'user' => new Property(
                null: true,
                belongs_to: User::class,
            ),
            'note' => new Property(
                type: Type::STRING,
                required: true,
            ),
        ];
    }
}
