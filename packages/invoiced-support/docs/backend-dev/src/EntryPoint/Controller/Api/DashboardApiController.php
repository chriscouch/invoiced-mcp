<?php

namespace App\EntryPoint\Controller\Api;

use App\Companies\Api\SetupProgressRoute;
use App\Reports\Api\ActivityChartRoute;
use App\Reports\Api\DashboardMetricRoute;
use App\Reports\Api\DashboardRoute;
use App\Reports\Api\ListDashboardsRoute;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route(host: '%app.api_domain%', name: 'api_')]
class DashboardApiController extends AbstractApiController
{
    /**
     * @deprecated
     */
    #[Route(path: '/announcements', name: 'announcements', methods: ['GET'])]
    public function announcements(): Response
    {
        return new JsonResponse([]);
    }

    #[Route(path: '/dashboard', name: 'dashboard', methods: ['GET'])]
    public function dashboard(DashboardRoute $route): Response
    {
        return $this->runRoute($route);
    }

    #[Route(path: '/dashboard/metrics/{metric}', name: 'dashboard_metric', methods: ['GET'])]
    public function dashboardMetric(DashboardMetricRoute $route): Response
    {
        return $this->runRoute($route);
    }

    #[Route(path: '/dashboard/activity_chart', name: 'dashboard_activity_chart', methods: ['GET'])]
    public function activityChart(ActivityChartRoute $route): Response
    {
        return $this->runRoute($route);
    }

    #[Route(path: '/_setup', name: 'dashboard_setup', methods: ['GET'])]
    public function setup(SetupProgressRoute $route): Response
    {
        return $this->runRoute($route);
    }

    #[Route(path: '/ui/dashboards', name: 'list_dashboards', methods: ['GET'])]
    public function listDashboards(ListDashboardsRoute $route): Response
    {
        return $this->runRoute($route);
    }
}
