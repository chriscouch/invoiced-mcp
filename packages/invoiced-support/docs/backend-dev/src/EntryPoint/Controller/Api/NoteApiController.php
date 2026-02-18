<?php

namespace App\EntryPoint\Controller\Api;

use App\AccountsReceivable\Api\CreateNoteRoute;
use App\AccountsReceivable\Api\DeleteNoteRoute;
use App\AccountsReceivable\Api\EditNoteRoute;
use App\AccountsReceivable\Api\ListNotesRoute;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route(host: '%app.api_domain%', name: 'api_')]
class NoteApiController extends AbstractApiController
{
    #[Route(path: '/notes', name: 'list_notes', methods: ['GET'])]
    public function listAll(ListNotesRoute $route): Response
    {
        return $this->runRoute($route);
    }

    #[Route(path: '/notes', name: 'create_note', methods: ['POST'])]
    public function create(CreateNoteRoute $route): Response
    {
        return $this->runRoute($route);
    }

    #[Route(path: '/notes/{model_id}', name: 'edit_note', methods: ['PATCH'])]
    public function edit(EditNoteRoute $route): Response
    {
        return $this->runRoute($route);
    }

    #[Route(path: '/notes/{model_id}', name: 'delete_note', methods: ['DELETE'])]
    public function delete(DeleteNoteRoute $route): Response
    {
        return $this->runRoute($route);
    }
}
