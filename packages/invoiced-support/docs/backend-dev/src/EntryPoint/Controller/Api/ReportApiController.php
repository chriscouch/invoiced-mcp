<?php

namespace App\EntryPoint\Controller\Api;

use App\Reports\Api\BuildReportRoute;
use App\Reports\Api\CreateSavedReportRoute;
use App\Reports\Api\CreateScheduledReportRoute;
use App\Reports\Api\DeleteSavedReportRoute;
use App\Reports\Api\DeleteScheduledReportRoute;
use App\Reports\Api\DownloadReportRoute;
use App\Reports\Api\EditSavedReportRoute;
use App\Reports\Api\EditScheduledReportRoute;
use App\Reports\Api\ListSavedReportsRoute;
use App\Reports\Api\ListScheduledReportsRoute;
use App\Reports\Api\RefreshReportRoute;
use App\Reports\Api\RetrieveReportRoute;
use App\Reports\Api\RetrieveSavedReportRoute;
use App\Reports\Api\RetrieveScheduledReportRoute;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route(host: '%app.api_domain%', name: 'api_')]
class ReportApiController extends AbstractApiController
{
    #[Route(path: '/reports', name: 'create_report', methods: ['POST'], defaults: ['no_database_transaction' => true])]
    public function createReport(BuildReportRoute $route): Response
    {
        return $this->runRoute($route);
    }

    #[Route(path: '/reports/{model_id}', name: 'retrieve_report', methods: ['GET'])]
    public function retrieveReport(RetrieveReportRoute $route): Response
    {
        return $this->runRoute($route);
    }

    #[Route(path: '/reports/{model_id}/download', name: 'download_report', methods: ['POST'], defaults: ['no_database_transaction' => true])]
    public function downloadReport(DownloadReportRoute $route): Response
    {
        return $this->runRoute($route);
    }

    #[Route(path: '/reports/{model_id}/refresh', name: 'refresh_report', methods: ['POST'], defaults: ['no_database_transaction' => true])]
    public function refreshReport(RefreshReportRoute $route): Response
    {
        return $this->runRoute($route);
    }

    /*
     * =========
     * Saved Reports API
     * =========
     */
    #[Route(path: '/saved_reports', name: 'list_saved_reports', methods: ['GET'])]
    public function listSavedReports(ListSavedReportsRoute $route): Response
    {
        return $this->runRoute($route);
    }

    #[Route(path: '/saved_reports', name: 'create_saved_report', methods: ['POST'])]
    public function createSavedReport(CreateSavedReportRoute $route): Response
    {
        return $this->runRoute($route);
    }

    #[Route(path: '/saved_reports/{model_id}', name: 'retrieve_saved_report', methods: ['GET'])]
    public function retrieveSavedReport(RetrieveSavedReportRoute $route): Response
    {
        return $this->runRoute($route);
    }

    #[Route(path: '/saved_reports/{model_id}', name: 'edit_saved_report', methods: ['PATCH'])]
    public function editSavedReport(EditSavedReportRoute $route): Response
    {
        return $this->runRoute($route);
    }

    #[Route(path: '/saved_reports/{model_id}', name: 'delete_saved_report', methods: ['DELETE'])]
    public function deleteSavedReport(DeleteSavedReportRoute $route): Response
    {
        return $this->runRoute($route);
    }

    /*
     * =========
     * Scheduled Reports API
     * =========
     */
    #[Route(path: '/scheduled_reports', name: 'list_scheduled_reports', methods: ['GET'])]
    public function listScheduledReports(ListScheduledReportsRoute $route): Response
    {
        return $this->runRoute($route);
    }

    #[Route(path: '/scheduled_reports', name: 'create_scheduled_report', methods: ['POST'])]
    public function createScheduledReport(CreateScheduledReportRoute $route): Response
    {
        return $this->runRoute($route);
    }

    #[Route(path: '/scheduled_reports/{model_id}', name: 'retrieve_scheduled_report', methods: ['GET'])]
    public function retrieveScheduledReport(RetrieveScheduledReportRoute $route): Response
    {
        return $this->runRoute($route);
    }

    #[Route(path: '/scheduled_reports/{model_id}', name: 'edit_scheduled_report', methods: ['PATCH'])]
    public function editScheduledReport(EditScheduledReportRoute $route): Response
    {
        return $this->runRoute($route);
    }

    #[Route(path: '/scheduled_reports/{model_id}', name: 'delete_scheduled_report', methods: ['DELETE'])]
    public function deleteScheduledReport(DeleteScheduledReportRoute $route): Response
    {
        return $this->runRoute($route);
    }
}
