<?php

namespace App\CustomerPortal\Libs\SessionHelpers;

use App\CustomerPortal\Models\CustomerPortalSession;
use Carbon\CarbonImmutable;
use App\Core\Orm\Query;

abstract class AbstractCustomerPortalSessionHelper
{
    /**
     * Get session based on input parameters.
     */
    abstract public function getSessionQuery(): Query;

    /**
     * Set session object based on input parameters.
     */
    abstract public function updateSession(CustomerPortalSession $session): void;

    public function upsertSession(): CustomerPortalSession
    {
        $session = $this->getSessionQuery()
            ->where('expires', CarbonImmutable::now()->toDateTimeString(), '>')
            ->oneOrNull();

        if (!$session) {
            $session = new CustomerPortalSession();
            $this->updateSession($session);
        }

        $session->expires = CarbonImmutable::now()->addDay();
        $session->saveOrFail();

        return $session;
    }
}
