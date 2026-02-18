<?php

namespace App\EntryPoint\Controller\Api;

use App\Core\Files\Api\CreateAttachmentRoute;
use App\Core\Files\Api\DeleteFileRoute;
use App\Core\Files\Api\FileUploadRoute;
use App\Core\Files\Api\RetrieveFileFromS3Route;
use App\Core\Files\Api\RetrieveFileRoute;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\HttpFoundation\Request;

#[Route(host: '%app.api_domain%', name: 'api_')]
class FileApiController extends AbstractApiController
{
    #[Route(path: '/attachments', name: 'create_attachment', methods: ['POST'])]
    public function createAttachment(CreateAttachmentRoute $route): Response
    {
        return $this->runRoute($route);
    }

    #[Route(path: '/files', name: 'create_file', methods: ['POST'])]
    public function create(FileUploadRoute $route): Response
    {
        return $this->runRoute($route);
    }

    #[Route(path: '/files/{model_id}', name: 'retrieve_file', methods: ['GET'])]
    public function retrieve(RetrieveFileRoute $route): Response
    {
        return $this->runRoute($route);
    }

    #[Route(path: '/file/{key}', name: 'retrieve_s3_file', methods: ['GET'])]
    public function retrieveS3File(RetrieveFileFromS3Route $route, Request $request): mixed
    {
        $key = $request->get('key');
        if (empty($key)) {
            throw new \Exception('No hash provided');
        }

        return $route->getFile($key);
    }

    #[Route(path: '/files/{model_id}', name: 'delete_file', methods: ['DELETE'])]
    public function delete(DeleteFileRoute $route): Response
    {
        return $this->runRoute($route);
    }
}
