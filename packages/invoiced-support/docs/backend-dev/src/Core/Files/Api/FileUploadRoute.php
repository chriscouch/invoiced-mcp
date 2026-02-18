<?php

namespace App\Core\Files\Api;

use App\Core\Files\Exception\UploadException;
use App\Core\Files\Libs\AttachmentUploader;
use App\Core\Files\Libs\FileValidator;
use App\Core\Files\Models\File;
use App\Core\RestApi\Exception\InvalidRequest;
use App\Core\RestApi\Routes\AbstractCreateModelApiRoute;
use App\Core\RestApi\ValueObjects\ApiCallContext;
use App\Core\RestApi\ValueObjects\ApiRouteDefinition;
use App\Core\RestApi\ValueObjects\QueryParameter;
use GuzzleHttp\Client;
use mikehaertl\tmp\File as TmpFile;

/**
 * API endpoint to upload file.
 */
class FileUploadRoute extends AbstractCreateModelApiRoute
{
    public function __construct(private AttachmentUploader $uploader)
    {
    }

    public function getDefinition(): ApiRouteDefinition
    {
        return new ApiRouteDefinition(
            queryParameters: array_merge(
                $this->getBaseQueryParameters(),
                [
                    'base64' => new QueryParameter(
                        types: ['numeric'],
                        default: 0,
                    ),
                ],
            ),
            requestParameters: null,
            requiredPermissions: [],
            modelClass: File::class,
        );
    }

    public function buildResponse(ApiCallContext $context): mixed
    {
        if (isset($context->requestParameters['url'])) {
            $url = $context->requestParameters['url'];
            $name = isset($context->requestParameters['name'])
                ? (string) $context->requestParameters['name']
                : '';

            // download file
            $tmpFile = new TmpFile('');

            $tmpFileResource = null;
            try {
                $tmpFileResource = fopen($tmpFile->getFileName(), 'w');

                // guzzle closes resource on success
                (new Client())->get($url, ['sink' => $tmpFileResource]);
            } catch (\Exception $e) {
                throw new InvalidRequest('Uploaded file exceeds maximum allowed size');
            } finally {
                // close file if Guzzle client failed to do so
                if (is_resource($tmpFileResource)) {
                    fclose($tmpFileResource);
                }
            }

            // validate file size
            if (!FileValidator::validateFileSize($tmpFile->getFileName())) {
                throw new InvalidRequest('Uploaded file exceeds maximum allowed size');
            }

            // validate file strict
            if (!FileValidator::validateFileStrict($tmpFile->getFileName(), $name)) {
                throw new InvalidRequest('File type is not allowed (strict validation)');
            }

            // validate file signature
            if (!FileValidator::validateFileSignature($tmpFile->getFileName(), $name)) {
                throw new UploadException('File signature does not match the expected type');
            }

            return parent::buildResponse($context);
        }

        if ($file = $context->request->files->get('file')) {
            $tmpFilename = $file->getPathName();
            $filename = $context->request->request->getString('filename', $file->getClientOriginalName());
            if ($context->queryParameters['base64']) {
                $file = (string) file_get_contents($tmpFilename);
                $file = base64_decode($file);
                file_put_contents($tmpFilename, $file);
            }

            try {
                return $this->uploader->upload($tmpFilename, $filename);
            } catch (UploadException $e) {
                throw new InvalidRequest($e->getMessage());
            }
        }

        throw new InvalidRequest('Upload request body must contain url or file.');
    }
}
