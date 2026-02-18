<?php

namespace App\Companies\Api;

use App\Companies\Libs\LogoUploader;
use App\Companies\Models\Company;
use App\Core\RestApi\Exception\ApiError;
use App\Core\RestApi\Exception\InvalidRequest;
use App\Core\RestApi\Routes\AbstractRetrieveModelApiRoute;
use App\Core\RestApi\ValueObjects\ApiCallContext;
use App\Core\RestApi\ValueObjects\ApiRouteDefinition;
use App\Core\Multitenant\TenantContext;

class UploadLogoRoute extends AbstractRetrieveModelApiRoute
{
    public function __construct(
        private LogoUploader $logoUploader,
        private TenantContext $tenant,
    ) {
    }

    public function getDefinition(): ApiRouteDefinition
    {
        return new ApiRouteDefinition(
            queryParameters: null,
            requestParameters: null,
            requiredPermissions: ['settings.edit'],
            modelClass: Company::class,
        );
    }

    public function retrieveModel(ApiCallContext $context): Company
    {
        $company = parent::retrieveModel($context);

        // Validate tenant ID matches context
        if ($this->tenant->get()->id != $company->id) {
            throw $this->modelNotFoundError();
        }

        return $company;
    }

    public function buildResponse(ApiCallContext $context): mixed
    {
        $logo = $context->request->files->get('logo');
        if (!$logo) {
            throw new InvalidRequest('Missing `logo` parameter');
        }

        parent::buildResponse($context);

        $error = $logo->getError();
        $size = $logo->getSize();
        $upldTmpName = $logo->getPathname();

        if (0 !== $error || $size <= 0 || !$upldTmpName) {
            throw new InvalidRequest('We had trouble parsing your file upload.');
        }

        $temp = $this->logoUploader->moveUploadedFile($upldTmpName);
        if (!$temp) {
            throw new ApiError('We were unable to move your logo to a temporary file.');
        }

        if ($this->logoUploader->upload($this->model, $temp)) {
            return ['logo' => $this->model->logo];
        }

        // get the first error
        if ($error = $this->getFirstError()) {
            throw $this->modelValidationError($error);
        }

        // no specific errors available, throw a generic error
        throw new ApiError('There was an error saving your logo.');
    }
}
