<?php

namespace App\EntryPoint\Controller\Api;

use App\Sending\Mail\Api\ListLettersByDocumentRoute;
use App\Sending\Mail\Api\RetrieveLetterRoute;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route(host: '%app.api_domain%', name: 'api_')]
class LetterApiController extends AbstractApiController
{
    #[Route(path: '/letters/{model_id}', name: 'retrieve_letter', methods: ['GET'])]
    public function retrieve(RetrieveLetterRoute $route): Response
    {
        return $this->runRoute($route);
    }

    #[Route(path: '/letters/document/{document_type}/{document_id}', name: 'list_letters_by_document', methods: ['GET'])]
    public function listLettersByDocument(ListLettersByDocumentRoute $route): Response
    {
        return $this->runRoute($route);
    }
}
