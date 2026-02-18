<?php

namespace App\Notifications\Emitters;

use App\Core\Authentication\Models\User;
use App\ActivityLog\Models\Event;
use App\Notifications\Interfaces\EmitterInterface;

class NullEmitter implements EmitterInterface
{
    public function emit(Event $event, User $user = null): bool
    {
        // do nothing
        return true;
    }
}
