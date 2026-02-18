<?php

namespace App\Core\Authentication\Event;

use App\Core\Authentication\Models\User;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Contracts\EventDispatcher\Event;

class PostLogoutEvent extends Event
{
    public function __construct(
        public readonly User $user,
        public readonly Request $request,
    ) {
    }
}
