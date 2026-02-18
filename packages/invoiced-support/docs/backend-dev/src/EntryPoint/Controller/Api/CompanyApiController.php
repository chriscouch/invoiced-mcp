<?php

namespace App\EntryPoint\Controller\Api;

use App\Companies\Api\AddCustomerPortalAttachmentsApiRoute;
use App\Companies\Api\ClearDataRoute;
use App\Companies\Api\EditCompanyRoute;
use App\Companies\Api\ListCustomerPortalAttachmentsRoute;
use App\Companies\Api\RetrieveCompanyRoute;
use App\Companies\Api\SetupCustomDomainRoute;
use App\Companies\Api\UploadLogoRoute;
use App\Companies\Models\Member;
use App\Core\Multitenant\TenantContext;
use Doctrine\DBAL\Connection;
use App\Core\Orm\ACLModelRequester;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route(host: '%app.api_domain%', name: 'api_')]
class CompanyApiController extends AbstractApiController
{
    #[Route(path: '/companies/current', name: 'current_company', methods: ['GET'])]
    public function currentCompany(RetrieveCompanyRoute $route, Connection $database, TenantContext $tenant): Response
    {
        // update the last accessed date on the member
        // NOTE: the ORM is not used because it has other
        // side effects like removing the API key or
        // modifying the updated_at timestamp.
        $requester = ACLModelRequester::get();
        if ($requester instanceof Member) {
            $database->update('Members', [
                'last_accessed' => time(),
            ], [
                'id' => $requester->id(),
            ]);
        }

        return $this->runRoute($route->setModel($tenant->get()));
    }

    #[Route(path: '/customer_portal_attachments', name: 'list_attachments', methods: ['GET'])]
    public function attachments(ListCustomerPortalAttachmentsRoute $route): Response
    {
        return $this->runRoute($route);
    }

    #[Route(path: '/customer_portal_attachments', name: 'add_attachments', methods: ['POST'])]
    public function addAttachment(AddCustomerPortalAttachmentsApiRoute $route): Response
    {
        return $this->runRoute($route);
    }

    #[Route(path: '/companies/{model_id}', name: 'retrieve_company', methods: ['GET'])]
    public function retrieve(RetrieveCompanyRoute $route): Response
    {
        return $this->runRoute($route);
    }

    #[Route(path: '/companies/{model_id}', name: 'edit_company', methods: ['PATCH'])]
    public function edit(EditCompanyRoute $route): Response
    {
        return $this->runRoute($route);
    }

    #[Route(path: '/companies/{model_id}/logo', name: 'upload_logo', methods: ['POST'])]
    public function uploadLogo(UploadLogoRoute $route): Response
    {
        return $this->runRoute($route);
    }

    #[Route(path: '/companies/{model_id}/clear', name: 'clear_data', methods: ['POST'])]
    public function clearData(ClearDataRoute $route): Response
    {
        return $this->runRoute($route);
    }

    #[Route(path: '/companies/{model_id}/custom_domain', name: 'setup_custom_domain', methods: ['POST'], defaults: ['no_database_transaction' => true])]
    public function setupCustomDomain(SetupCustomDomainRoute $route): Response
    {
        return $this->runRoute($route);
    }
}
