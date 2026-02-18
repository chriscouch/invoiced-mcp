<?php

namespace App\EntryPoint\Controller;

use App\Core\Files\Api\RetrieveFileFromS3Route;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\Routing\Attribute\Route;

#[Route(
    name: 'files_',
    host: 'files.%app.domain%',
    schemes: '%app.protocol%',
)]
class FilePublicController extends AbstractController implements LoggerAwareInterface
{
    use LoggerAwareTrait;

    #[Route(path: '/{key}', name: 'retrieve_s3_file', methods: ['GET'])]
    public function retrieveS3File(RetrieveFileFromS3Route $route, Request $request): mixed
    {
        $key = $request->get('key');
        if (empty($key)) {
            return new StreamedResponse(function () {});
        }

        return $route->getFile($key);
    }
}
