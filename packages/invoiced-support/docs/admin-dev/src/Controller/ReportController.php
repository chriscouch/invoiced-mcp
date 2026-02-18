<?php

namespace App\Controller;

use App\Entity\Forms\MonthlyReportFilter;
use App\Form\MonthlyReportType;
use App\Service\MonthlyReport;
use App\Service\UsageReport;
use Doctrine\DBAL\DBALException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class ReportController extends AbstractController
{
    #[Route(path: '/admin/monthly_report', name: 'bi_report_form')]
    public function biReportForm(Request $request, MonthlyReport $report): Response
    {
        $form = $this->getMonthlyReportForm($request);
        if ($form->isSubmitted() && $form->isValid()) {
            /** @var MonthlyReportFilter $filter */
            $filter = $form->getData();

            try {
                $data = $report->generate($filter);
            } catch (DBALException $e) {
                return $this->render('reports/view_monthly_report.html.twig', [
                    'month' => $filter->toString(),
                    'error' => $e->getMessage(),
                ]);
            }

            return $this->render('reports/view_monthly_report.html.twig', [
                'month' => $filter->toString(),
                'report' => [
                    [
                        'type' => $filter->getMetric(),
                        'name' => $filter->getName(),
                        'value' => $data,
                    ],
                ],
            ]);
        }

        return $this->render('reports/new_monthly_report.html.twig', [
            'form' => $form->createView(),
        ]);
    }

    #[Route(path: '/admin/usage_report/{billingProfileId}', name: 'usage_report')]
    public function usageReportForm(int $billingProfileId, UsageReport $report): Response
    {
        try {
            $data = $report->generate($billingProfileId);
        } catch (DBALException $e) {
            return $this->render('reports/view_usage_report.html.twig', [
                'billingProfileId' => $billingProfileId,
                'error' => $e->getMessage(),
            ]);
        }

        return $this->render('reports/view_usage_report.html.twig', [
            'billingProfileId' => $billingProfileId,
            'report' => $data,
        ]);
    }

    private function getMonthlyReportForm(Request $request): FormInterface
    {
        $filter = new MonthlyReportFilter();

        $form = $this->createForm(MonthlyReportType::class, $filter, [
            'start_month' => '2013-05',
        ]);

        $form->handleRequest($request);

        return $form;
    }
}
