<?php

namespace App\EntryPoint\QueueJob;

use App\Core\Mailer\Mailer;
use App\Core\Queue\AbstractResqueJob;
use App\Core\Queue\Interfaces\MaxConcurrencyInterface;
use App\Integrations\Adyen\Exception\AdyenReconciliationException;
use App\Integrations\Adyen\Models\AdyenReport;
use App\Integrations\Adyen\Reconciliation\AdyenReportExtractor;
use App\Integrations\Adyen\Reconciliation\AdyenReportStorage;
use App\Integrations\Adyen\ReportHandler\ReportHandlerFactory;

class ProcessAdyenReportJob extends AbstractResqueJob implements MaxConcurrencyInterface
{
    public function __construct(
        private AdyenReportExtractor $extractor,
        private ReportHandlerFactory $handlerFactory,
        private AdyenReportStorage $reportStorage,
        private Mailer $mailer,
    ) {
    }

    public function perform(): void
    {
        $report = AdyenReport::findOrFail($this->args['report']);
        // Do not process the same report twice
        if ($report->processed) {
            return;
        }

        // Get the report handler
        $handler = $this->handlerFactory->get($report->report_type);
        if (!$handler) {
            return;
        }

        try {
            // Extract the report
            $rows = $this->extractor->extract($report->filename);

            // Process the rows in the report handler
            foreach ($rows as $row) {
                $handler->handleRow($row);
            }
            $handler->finish();

            // Mark the report as processed
            $report->processed = true;
            $report->error = null;
            $report->saveOrFail();
        } catch (AdyenReconciliationException $e) {
            $report->error = $e->getMessage().' '.$e->identifier;
            $report->saveOrFail();

            // Report the error to Slack
            $message = [
                'from_email' => 'no-reply@invoiced.com',
                'to' => [['email' => 'b2b-payfac-notificati-aaaaqfagorxgbzwrnrb7unxgrq@flywire.slack.com', 'name' => 'Invoiced Payment Ops']],
                'subject' => "Processing Adyen Report Failed - {$report->filename}",
                'text' => "Adyen report failed to process.\nReport: {$report->filename}\nReport Type: {$report->report_type->value}\nFailing Record: {$e->identifier}\nError: {$e->getMessage()}",
            ];

            // Attempt to attach the report
            $tmpFile = $this->reportStorage->retrieve($report->filename);

            if ($tmpFile) {
                $message['attachments'] = [
                    [
                        'name' => $report->filename,
                        'type' => 'text/csv',
                        'content' => base64_encode((string) file_get_contents($tmpFile->getFileName())),
                    ],
                ];
            }

            $this->mailer->send($message);
        } catch (\Throwable $e) {
            $report->error = $e->getMessage();
            $report->saveOrFail();
        }
    }

    public static function getMaxConcurrency(array $args): int
    {
        return 1;
    }

    public static function getConcurrencyKey(array $args): string
    {
        return 'adyen_report:'.$args['report'];
    }

    public static function getConcurrencyTtl(array $args): int
    {
        return 3600; // 1 hour
    }

    public static function delayAtConcurrencyLimit(): bool
    {
        return false;
    }
}
