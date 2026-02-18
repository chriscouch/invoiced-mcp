<?php

namespace App\CustomerPortal\Libs\SessionHelpers;

use App\Core\Authentication\Models\User;
use App\Core\Orm\Query;
use App\CustomerPortal\Models\CustomerPortalSession;

class CustomerPortalUserSessionHelper extends AbstractCustomerPortalSessionHelper
{
    public function __construct(private readonly User $user)
    {
    }

    public function getSessionQuery(): Query
    {
        return CustomerPortalSession::where('user_id', $this->user);
    }

    public function updateSession(CustomerPortalSession $session): void
    {
        $session->user = $this->user;
    }
}
