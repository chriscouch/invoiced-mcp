<?php

namespace App\EntryPoint\QueueJob;

use App\Core\Mailer\Mailer;
use App\Core\Multitenant\Interfaces\TenantAwareQueueJobInterface;
use App\Core\Queue\AbstractResqueJob;
use App\Core\Queue\Interfaces\MaxConcurrencyInterface;
use App\Core\Queue\Queue;
use App\Core\Queue\QueueServiceLevel;
use App\Exports\Libs\ExporterFactory;
use App\Exports\Models\Export;
use ICanBoogie\Inflector;
use InvalidArgumentException;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;

class BasicExportJob extends AbstractResqueJob implements LoggerAwareInterface, TenantAwareQueueJobInterface, MaxConcurrencyInterface
{
    use LoggerAwareTrait;

    public function __construct(
        private readonly ExporterFactory $factory,
        private readonly Mailer $mailer,
    ) {
    }

    public function perform(): void
    {
        $export = $this->getExport();
        if (!$export) {
            return;
        }
        $options = (array) $this->args['options'];

        $this->execute($export, $options);
    }

    /**
     * Gets the export for this job.
     */
    public function getExport(): ?Export
    {
        return Export::queryWithoutMultitenancyUnsafe()
            ->where('id', $this->args['export'])
            ->oneOrNull();
    }

    /**
     * Sets up the export job.
     */
    public static function create(Queue $queue, string $type, ?int $userId = null, array $options = []): Export
    {
        // create an export job
        $export = new Export();
        $export->user_id = $userId;
        $inflector = Inflector::get();
        $export->name = $inflector->titleize($inflector->pluralize($type));
        $export->status = Export::PENDING;
        $export->type = $type;
        $export->saveOrFail();

        // queue the job
        $queue->enqueue(static::class, [
            'export' => $export->id(),
            'type' => $type,
            'options' => $options,
        ], QueueServiceLevel::Batch);

        return $export;
    }

    /**
     * Executes the import job. Should be called once pulled
     * off the queue.
     */
    public function execute(Export $export, array $options = []): void
    {
        // can only run pending jobs
        if (Export::PENDING != $export->status) {
            return;
        }

        // use the company's time zone for date stuff
        $export->tenant()->useTimezone();

        // verify there is an available exporter
        try {
            $type = $export->type.'_'.($options['type'] ?? 'csv');
            $exporter = $this->factory->get($type);
        } catch (InvalidArgumentException) {
            $export->status = Export::FAILED;
            $export->save();

            return;
        }

        $export->position = 0;
        $export->total_records = 0;
        $export->save();

        // the exporter should output the result
        // to a temp file
        $exporter->build($export, $options);

        // notify user
        $export->notify($this->mailer);
    }

    public static function getMaxConcurrency(array $args): int
    {
        // Only 1 export per account can be generated at a time.
        return 1;
    }

    public static function getConcurrencyKey(array $args): string
    {
        return ($args['type'] ?? '').':export:'.$args['tenant_id'];
    }

    public static function getConcurrencyTtl(array $args): int
    {
        return $args['ttl'] ?? 600; // 10 minutes
    }

    public static function delayAtConcurrencyLimit(): bool
    {
        return true;
    }
}
