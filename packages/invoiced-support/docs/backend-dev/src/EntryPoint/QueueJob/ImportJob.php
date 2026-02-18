<?php

namespace App\EntryPoint\QueueJob;

use App\Core\Mailer\Mailer;
use App\Core\Multitenant\Interfaces\TenantAwareQueueJobInterface;
use App\Core\Queue\AbstractResqueJob;
use App\Core\Queue\Interfaces\MaxConcurrencyInterface;
use App\Core\Queue\Queue;
use App\Core\Queue\QueueServiceLevel;
use App\Core\S3ProxyFactory;
use App\Core\Utils\Compression;
use App\Core\Utils\InfuseUtility as Utility;
use App\ActivityLog\Enums\EventType;
use App\ActivityLog\Libs\EventSpool;
use App\ActivityLog\ValueObjects\PendingEvent;
use App\Imports\Exceptions\ValidationException;
use App\Imports\Libs\ImporterFactory;
use App\Imports\Libs\ImportLock;
use App\Imports\Models\Import;
use Aws\S3\Exception\S3Exception;
use InvalidArgumentException;
use mikehaertl\tmp\File;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Sentry\State\HubInterface;
use Sentry\State\Scope;
use Symfony\Component\Lock\LockFactory;
use Throwable;

class ImportJob extends AbstractResqueJob implements LoggerAwareInterface, TenantAwareQueueJobInterface, MaxConcurrencyInterface
{
    use LoggerAwareTrait;

    private const LOCK_TIME = 600;

    private ImportLock $lock;
    private mixed $s3;

    public function __construct(
        private ImporterFactory $factory,
        private string $bucket,
        private EventSpool $eventSpool,
        private string $dashboardUrl,
        private LockFactory $lockFactory,
        private HubInterface $hub,
        private Queue $queueBackend,
        private Mailer $mailer,
        S3ProxyFactory $s3Factory,
    ) {
        $this->s3 = $s3Factory->build();
    }

    /**
     * Creates a new import job.
     *
     * @param string $type    import model type
     * @param array  $mapping import column mapping
     * @param array  $lines   lines from import file
     *
     * @throws InvalidArgumentException
     */
    public function create(string $type, array $mapping, array $lines, array $options = []): Import
    {
        $import = new Import();

        // persist data
        $import->source_file = $this->saveInput($import, $mapping, $lines);

        // create job
        $importer = $this->factory->get($type);
        $import->name = $importer->getName($type, $options);
        $import->type = $type;
        $import->status = Import::PENDING;
        $import->saveOrFail();

        // queue job
        $this->enqueue($import, $options);

        return $import;
    }

    /**
     * Queues the operation.
     */
    private function enqueue(Import $import, array $options = []): void
    {
        $this->queueBackend->enqueue(self::class, [
            'import' => $import->id(),
            'options' => $options,
        ], QueueServiceLevel::Batch);
    }

    /**
     * This will requeue an import job. Would be used if a
     * long-running import job has more records to process.
     *
     * @param array $mapping import column mapping
     * @param array $lines   lines from import file
     */
    public function requeue(Import $import, array $mapping, array $lines, array $options = []): Import
    {
        // persist data
        $import->source_file = $this->saveInput($import, $mapping, $lines);

        // update job status
        $import->status = Import::PENDING;
        $import->save();

        // queue job
        $this->enqueue($import, $options);

        return $import;
    }

    public function perform(): void
    {
        $import = $this->getImport();
        if (!$import) {
            return;
        }

        $options = (array) $this->args['options'];
        $this->execute($import, $options);
    }

    /**
     * Gets the import for this job.
     */
    public function getImport(): ?Import
    {
        $id = $this->args['import'];

        return Import::queryWithoutMultitenancyUnsafe()
            ->where('id', $id)
            ->oneOrNull();
    }

    /**
     * Executes the import job. Should be called once pulled
     * off the queue.
     */
    public function execute(Import $import, array $options = []): void
    {
        // Tag the active import on Sentry
        $this->hub->configureScope(function (Scope $scope) use ($import): void {
            $scope->setTag('importer', $import->type);
            $scope->setExtra('importId', (string) $import->id());
        });

        // obtain a lock
        $this->lock = new ImportLock($this->lockFactory, $import->tenant(), $import->type);

        if (!$this->lock->acquire(self::LOCK_TIME)) {
            $this->failed($import, 'Another import of the same type is already running. Please wait for it to finish before starting another import.', 0);

            return;
        }

        // use the company's time zone for date stuff
        $import->tenant()->useTimezone();

        // load persisted data
        [$mapping, $lines] = $this->loadPersistedData($import);

        // verify there is an available importer
        try {
            $importer = $this->factory->get($import->type);
        } catch (InvalidArgumentException $e) {
            $this->failed($import, $e->getMessage(), 0);

            return;
        }

        // no events
        EventSpool::disable();

        // build the records being imported
        try {
            $records = $importer->build($mapping, $lines, $options, $import);
        } catch (ValidationException $e) {
            $this->failed($import, $e->getMessage(), count($lines), $e);

            return;
        } catch (Throwable $e) {
            $this->logger->error('An uncaught exception occurred when building records for an import', ['exception' => $e]);
            $this->failed($import, 'Internal Server Error', count($lines));

            return;
        }

        // save some memory
        unset($mapping);
        unset($lines);

        // update total records being imported
        // if it was not set in the build() step
        if (!$import->total_records) {
            $import->total_records = count($records);
            $import->save();
        }

        // run import
        try {
            $result = $importer->run($records, $options, $import);
        } catch (Throwable $e) {
            $this->logger->error('An uncaught exception occurred when running an import', ['exception' => $e]);
            $this->failed($import, 'Internal Server Error', 0);

            return;
        }

        // allow events again
        EventSpool::enable();
        // update import with results from this batch
        $import->num_imported += $result->getNumCreated();
        $import->num_updated += $result->getNumUpdated();
        $import->num_failed += $result->getNumFailed();

        $failures = $result->getFailures();
        if (is_array($import->failure_detail)) {
            $failures = array_merge($import->failure_detail, $result->getFailures());
        }
        $import->failure_detail = $failures;

        // Save references to the objects imported.
        foreach ($result->getObjects() as $object) {
            $object->save();
        }

        // clean up
        $this->deletePersistedData($import);
        $this->lock->release();

        // if the importer has more data to import then requeue it
        if ($importer->hasMore()) {
            $options = $importer->hasMoreOptions();
            $this->requeue($import, [], [], $options);

            return;
        }

        // mark the import as succeeded / failed
        $import->status = (0 == $import->num_failed) ? Import::SUCCEEDED : Import::FAILED;
        $import->save();

        // notify user that the import is finished
        $this->notify($import);
    }

    //
    // Helpers
    //

    /**
     * Persists the import data into a location that is accessible
     * to another host so the job can be queued.
     *
     * @return string|null filename
     */
    public function saveInput(Import $import, array $mapping, array $lines): ?string
    {
        if (!$mapping && !$lines) {
            return null;
        }

        // save the input as JSON
        $data = (string) json_encode([
            'mapping' => $mapping,
            'lines' => $lines,
        ]);

        // save to temp file
        $tmpFile = new File($data, 'json');
        unset($data);

        // save to S3 and return filename
        $filename = Utility::guid().'.json';

        return $this->persist($tmpFile, $filename);
    }

    /**
     * Loads the previously persisted import data.
     *
     * @return array [mapping, lines]
     */
    public function loadPersistedData(Import $import): array
    {
        $filename = $import->source_file;
        if (!$filename) {
            return [[], []];
        }

        // load from S3
        $object = $this->s3->getObject([
            'Bucket' => $this->bucket,
            'Key' => $filename,
        ]);

        // parse JSON
        $data = json_decode(Compression::decompressIfNeeded($object['Body']), true);

        return [$data['mapping'], $data['lines']];
    }

    /**
     * Deletes the persisted import data.
     */
    public function deletePersistedData(Import $import): void
    {
        if ($sourceFile = $import->source_file) {
            $result = $this->s3->deleteObject([
                'Bucket' => $this->bucket,
                'Key' => $sourceFile, ]);

            if ($result['DeleteMarker']) {
                $import->source_file = null;
                $import->save();
            }
        }
    }

    /**
     * Marks an import job as failed and notifies the user.
     */
    private function failed(Import $import, string $reason, int $numFailures, ValidationException $e = null): void
    {
        // mark as failed
        $import->status = Import::FAILED;
        $import->num_failed += $numFailures;
        $failures = [
            [
                'reason' => $reason,
            ],
        ];

        if ($e) {
            if ($record = $e->getRecord()) {
                $failures[0]['data'] = $record;
            }
            if ($lineNumber = $e->getLineNumber()) {
                $failures[0]['line_number'] = $lineNumber;
            }
        }

        if (is_array($import->failure_detail)) {
            $failures = array_merge($import->failure_detail, $failures);
        }
        $import->failure_detail = $failures;
        $import->save();

        // notify user
        $this->notify($import);

        // release any locks
        if ($this->lock->hasLock()) {
            $this->lock->release();
        }
    }

    /**
     * Notifies the user that this import has finished.
     */
    public function notify(Import $import): void
    {
        // the user might not exist because it was not provided
        // or else the job has already been deleted
        $user = $import->user();
        if (!$user) {
            return;
        }

        // create an event for the import
        if ($import->num_imported > 0) {
            $pendingEvent = new PendingEvent(
                object: $import,
                type: EventType::ImportFinished,
                parameters: ['user' => $import->user_id],
            );
            $this->eventSpool->enqueue($pendingEvent);
        }

        // determine run time, in minutes
        $runtime = round(($import->updated_at - $import->created_at) / 60);

        $url = $this->dashboardUrl.'/imports/'.$import->id().'?account='.$import->tenant_id;

        // send an email to the user if the import has been running for awhile (30+ seconds)
        if ($import->updated_at - $import->created_at > 30) {
            $this->mailer->sendToUser($user, [
                    'subject' => 'Import Finished: '.$import->name,
                ], 'import-finished', [
                'company' => $import->tenant()->name,
                'name' => $user->first_name,
                'importName' => $import->name,
                'date' => date('M j, Y', $import->created_at),
                'status' => $import->status,
                'runtime' => $runtime,
                'num_imported' => $import->num_imported,
                'num_updated' => $import->num_updated,
                'num_failed' => $import->num_failed,
                'href' => $url,
            ]);
        }
    }

    /**
     * Persists data to S3 using a randomized filename.
     */
    private function persist(File $tmpFile, string $filename): ?string
    {
        $s3Filename = strtolower(Utility::guid());

        try {
            $this->s3->putObject([
                'Bucket' => $this->bucket,
                'Key' => $s3Filename,
                'Body' => Compression::compress((string) file_get_contents($tmpFile)),
                'ContentDisposition' => 'attachment; filename="'.$filename.'"',
            ]);
        } catch (S3Exception $e) {
            $this->logger->error('Could not upload import data', ['exception' => $e]);

            return null;
        }

        return $s3Filename;
    }

    public static function getMaxConcurrency(array $args): int
    {
        // Only 1 import per account can be running at a time.
        return 1;
    }

    public static function getConcurrencyKey(array $args): string
    {
        return 'import:'.$args['tenant_id'];
    }

    public static function getConcurrencyTtl(array $args): int
    {
        return 600; // 10 minutes
    }

    public static function delayAtConcurrencyLimit(): bool
    {
        return true;
    }
}
