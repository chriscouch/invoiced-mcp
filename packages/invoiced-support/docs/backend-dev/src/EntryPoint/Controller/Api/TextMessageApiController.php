<?php

namespace App\EntryPoint\Controller\Api;

use App\Sending\Sms\Api\ListTextsByDocumentRoute;
use App\Sending\Sms\Api\RetrieveTextMessageRoute;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route(host: '%app.api_domain%', name: 'api_')]
class TextMessageApiController extends AbstractApiController
{
    #[Route(path: '/text_messages/{model_id}', name: 'retrieve_text_message', methods: ['GET'])]
    public function retrieve(RetrieveTextMessageRoute $route): Response
    {
        return $this->runRoute($route);
    }

    #[Route(path: '/text_messages/document/{document_type}/{document_id}', name: 'list_texts_by_document', methods: ['GET'])]
    public function listTextsByDocument(ListTextsByDocumentRoute $route): Response
    {
        return $this->runRoute($route);
    }
}
