<?php

namespace App\EntryPoint\Controller\Api;

use App\Companies\Api\CreateMemberRoute;
use App\Companies\Api\DeleteMemberRoute;
use App\Companies\Api\EditMemberRoute;
use App\Companies\Api\ListMembersRoute;
use App\Companies\Api\MemberFrequencyUpdateApiRoute;
use App\Companies\Api\ResendInviteRoute;
use App\Companies\Api\RetrieveMemberRoute;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route(host: '%app.api_domain%', name: 'api_')]
class MemberApiController extends AbstractApiController
{
    #[Route(path: '/members', name: 'list_members', methods: ['GET'])]
    public function listAll(ListMembersRoute $route): Response
    {
        return $this->runRoute($route);
    }

    #[Route(path: '/members', name: 'create_member', methods: ['POST'])]
    public function create(CreateMemberRoute $route): Response
    {
        return $this->runRoute($route);
    }

    #[Route(path: '/members/{model_id}', name: 'retrieve_member', methods: ['GET'])]
    public function retrieve(RetrieveMemberRoute $route): Response
    {
        return $this->runRoute($route);
    }

    #[Route(path: '/members/{model_id}', name: 'edit_member', methods: ['PATCH'])]
    public function edit(EditMemberRoute $route): Response
    {
        return $this->runRoute($route);
    }

    #[Route(path: '/members/{model_id}/frequency', name: 'edit_member_update_frequency', methods: ['PATCH'])]
    public function editUpdateFrequency(MemberFrequencyUpdateApiRoute $route): Response
    {
        return $this->runRoute($route);
    }

    #[Route(path: '/members/{model_id}', name: 'delete_member', methods: ['DELETE'])]
    public function delete(DeleteMemberRoute $route): Response
    {
        return $this->runRoute($route);
    }

    #[Route(path: '/members/{model_id}/invites', name: 'resend_member_invite', methods: ['POST'], defaults: ['no_database_transaction' => true])]
    public function resendInvite(ResendInviteRoute $route): Response
    {
        return $this->runRoute($route);
    }
}
