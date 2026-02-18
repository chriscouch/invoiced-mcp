<?php

namespace App\Core\Files\Api;

use App\Core\Files\Models\File;
use App\Core\RestApi\ValueObjects\ApiRouteDefinition;
use App\Core\S3ProxyFactory;
use Aws\S3\Exception\S3Exception;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\HttpFoundation\StreamedResponse;


class RetrieveFileFromS3Route
{
    private mixed $s3Client;

    public function __construct(S3ProxyFactory $s3Factory)
    {
        $this->s3Client = $s3Factory->build();
    }

    public function getDefinition(): ApiRouteDefinition
    {
        return new ApiRouteDefinition(
            queryParameters: null,
            requestParameters: null,
            requiredPermissions: [],
            modelClass: null
        );
    }

    public function getFile(string $key): StreamedResponse
    {
        $file = File::queryWithoutMultitenancyUnsafe()->where('key', $key)->oneOrNull();
        if (!$file) {
            return new StreamedResponse(function () {});
        }

        try {
            $result = $this->s3Client->getObject([
                'Bucket' => $file->getBucketName(),
                'Key'    => $file->getKey(),
            ]);

            $response = new StreamedResponse(function () use ($result) {
                $body = $result['Body'];
                while (!$body->eof()) {
                    echo $body->read(1024);
                }
            });

            $disposition = $response->headers->makeDisposition(
                ResponseHeaderBag::DISPOSITION_INLINE,
                str_replace(['/', '\\'], '-', basename($file->getName()))
            );

            $response->headers->set('Content-Type', $result['ContentType']);
            $response->headers->set('Content-Disposition', $disposition);
            $response->headers->set('Content-Length', $result['ContentLength']);

            return $response;

        } catch (S3Exception $e) {
            throw new \Exception('File not found on S3, $e: ' . $e->getMessage(), 500, $e);
        }
    }
}