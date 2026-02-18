<?php

namespace App\Form;

use App\Entity\Forms\MonthlyReportFilter;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class MonthlyReportType extends AbstractType
{
    const REPORT_TYPES = [
        'Active Payment Plans' => 'activePaymentPlans',
        'Active Subscriptions' => 'activeSubscriptions',
        'AutoPay Payments' => 'autoPayPayments',
        'Bill Approvals' => 'billApprovals',
        'Bill Payments' => 'totalVendorPayments',
        'Bills Created' => 'totalBills',
        'Cancellation Reasons' => 'cancellationReasons',
        'Customer Portal Logins' => 'customerPortalLogins',
        'Customers Not Yet Live' => 'customersNotYetLive',
        'Industries' => 'industries',
        'Integrations Installed' => 'integrationsInstalled',
        'Invoiced Payments Volume' => 'invoicedPaymentsVolume',
        'Invoices Issued' => 'totalInvoices',
        'Monthly Active Users' => 'monthlyActiveUsers',
        'Network Connections Added' => 'newNetworkConnections',
        'Network Documents Sent' => 'networkDocumentsSent',
        'Network Size' => 'totalNetworkSize',
        'Overage Charges' => 'overageCharges',
        'Payment Processing Volume' => 'totalPaymentsVolume',
        'Payments Applied' => 'paymentsApplied',
        'Time to Go Live' => 'timeToGoLive',
        'Trial Funnel' => 'trialFunnel',
        'Unique Invoice Views' => 'uniqueInvoiceViews',
    ];

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        // Produces a range of Start Month to This Month
        [$startYear, $startMonth] = explode('-', $options['start_month']);
        $numMonths = ((int) date('Y') - (int) $startYear) * 12 + (int) date('m') - (int) $startMonth;
        $months = [];
        for ($i = 0; $i <= $numMonths; ++$i) {
            $month = (int) strtotime('-'.$i.' months');
            $months[date('F Y', $month)] = date('Y-m', $month);
        }

        $builder
            ->add('month', ChoiceType::class, [
                'label' => 'Month',
                'choices' => $months,
                'required' => true,
            ])
            ->add('metric', ChoiceType::class, [
                'label' => 'Metric',
                'choices' => self::REPORT_TYPES,
                'required' => true,
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => MonthlyReportFilter::class,
            'start_month' => '2013-05',
        ]);
    }
}
