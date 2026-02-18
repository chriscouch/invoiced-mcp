<?php

namespace App\Sending\Email\Libs;

use App\Companies\Models\Member;
use App\Core\Authentication\Libs\UserContext;
use App\Core\Multitenant\TenantContext;
use App\Sending\Email\Models\InboxEmail;
use App\Sending\Email\ValueObjects\TrackingPixel;
use Doctrine\DBAL\Connection;

class EmailOpenTracker
{
    public function __construct(private TenantContext $tenant, private Connection $database, private UserContext $userContext)
    {
    }

    public function recordOpen(TrackingPixel $pixel): void
    {
        // inbox email should always be set
        $email = InboxEmail::queryWithoutMultiTenancyUnsafe()
            ->where('tracking_id', $pixel->getId())
            ->oneOrNull();

        if (!$email instanceof InboxEmail) {
            return;
        }

        // IMPORTANT: set the current tenant to enable multitenant operations
        $this->tenant->set($email->tenant());

        // check if the user is signed in and a member of this business
        $user = $this->userContext->get();
        if ($user) {
            $n = Member::where('user_id', $user)->count();
            if ($n > 0) {
                $this->tenant->clear();

                return;
            }
        }

        $this->database->executeStatement('UPDATE InboxEmails SET opens = opens + 1 WHERE id=?', [$email->id]);
        $this->tenant->clear();
    }
}
