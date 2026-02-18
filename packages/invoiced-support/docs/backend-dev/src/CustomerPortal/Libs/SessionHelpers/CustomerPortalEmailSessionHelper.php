<?php

namespace App\CustomerPortal\Libs\SessionHelpers;

use App\CustomerPortal\Models\CustomerPortalSession;
use App\Core\Orm\Query;

class CustomerPortalEmailSessionHelper extends AbstractCustomerPortalSessionHelper
{
    public function __construct(private readonly string $email)
    {
    }

    public function getSessionQuery(): Query
    {
        return CustomerPortalSession::where('email', $this->email);
    }

    public function updateSession(CustomerPortalSession $session): void
    {
        $session->email = $this->email;
    }
}
