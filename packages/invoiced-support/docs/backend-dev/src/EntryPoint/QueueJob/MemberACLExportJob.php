<?php

namespace App\EntryPoint\QueueJob;

use App\Companies\Models\Member;
use App\Core\Authentication\Libs\UserContext;
use App\Core\Mailer\Mailer;
use App\Core\Orm\ACLModelRequester;
use App\Exports\Libs\ExporterFactory;

class MemberACLExportJob extends BasicExportJob
{
    public function __construct(
        private readonly UserContext $userContext,
        ExporterFactory $factory,
        Mailer $mailer,
    ) {
        parent::__construct($factory, $mailer);
    }

    public function perform(): void
    {
        $export = $this->getExport();
        if (!$export) {
            return;
        }

        // save the current user and set user who initiated export
        // this is used to filter data which current user has access to
        // needed to prevent user from seeing customers etc he doesn't have access to
        // DO NOT REMOVE
        $user = $export->user();
        $originalUser = $originalMember = null;
        if ($user) {
            $originalUser = $this->userContext->getOrFail();
            $this->userContext->set($user);

            // save the current member and set member associated with user for proper permissions
            $originalMember = ACLModelRequester::get();
            $member = Member::getForUser($user);
            ACLModelRequester::set($member);
        }

        $options = (array) $this->args['options'];
        $this->execute($export, $options);

        if ($user) {
            // set original member and user again
            ACLModelRequester::set($originalMember);
            $this->userContext->set($originalUser);
        }
    }
}
