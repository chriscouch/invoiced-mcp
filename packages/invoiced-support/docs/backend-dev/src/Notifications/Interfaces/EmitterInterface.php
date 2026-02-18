<?php

namespace App\Notifications\Interfaces;

use App\Core\Authentication\Models\User;
use App\ActivityLog\Models\Event;

interface EmitterInterface
{
    /**
     * Emits an event using this transport medium.
     *
     * @return bool whether the event was emitted
     */
    public function emit(Event $event, User $user = null): bool;
}
