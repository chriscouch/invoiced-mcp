<?php

namespace App\Integrations\Adyen\EventSubscriber;

use App\Core\Queue\Queue;
use App\Core\Queue\QueueServiceLevel;
use App\EntryPoint\QueueJob\ProcessAdyenReportJob;
use App\Integrations\Adyen\AdyenClient;
use App\Integrations\Adyen\Enums\ReportType;
use App\Integrations\Adyen\Models\AdyenReport;
use App\Integrations\Adyen\Reconciliation\AdyenReportStorage;
use App\Integrations\Adyen\ValueObjects\AdyenPlatformWebhookEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class AdyenReportSubscriber implements EventSubscriberInterface
{
    private const array EVENT_TYPES = [
        'balancePlatform.report.created',
    ];

    public function __construct(
        private AdyenReportStorage $reportStorage,
        private AdyenClient $adyenClient,
        private Queue $queue,
    ) {
    }

    public function process(AdyenPlatformWebhookEvent $event): void
    {
        if (!in_array($event->data['type'], self::EVENT_TYPES)) {
            return;
        }

        $data = $event->data['data'];
        $reportType = ReportType::tryFrom($data['reportType']);

        // Ignore unrecognized report types
        if (!$reportType) {
            return;
        }

        // Download the report
        $file = $this->adyenClient->downloadPlatformReport($data['downloadUrl']);

        // Save the report to permanent storage
        $this->reportStorage->store($file, $data['fileName']);

        // Create the record in our database as long as it does not exist yet
        if (AdyenReport::where('filename', $data['fileName'])->count()) {
            return;
        }

        $report = new AdyenReport();
        $report->filename = $data['fileName'];
        $report->report_type = $reportType;
        $report->processed = false;
        $report->saveOrFail();

        // Process the report now
        $this->queue->enqueue(ProcessAdyenReportJob::class, [
            'report' => $report->id,
        ], QueueServiceLevel::Batch);
    }

    public static function getSubscribedEvents(): array
    {
        return [
            AdyenPlatformWebhookEvent::class => 'process',
        ];
    }
}
