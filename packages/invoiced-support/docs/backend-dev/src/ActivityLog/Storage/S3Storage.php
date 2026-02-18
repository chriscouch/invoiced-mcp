<?php

namespace App\ActivityLog\Storage;

use App\Core\S3ProxyFactory;
use App\Core\Statsd\Interfaces\StatsdAwareInterface;
use App\Core\Statsd\StatsdAwareTrait;
use App\Core\Utils\Compression;
use App\Core\Utils\Exception\CompressionException;
use App\ActivityLog\Interfaces\EventStorageInterface;
use App\ActivityLog\Models\Event;
use App\ActivityLog\ValueObjects\EventData;
use Aws\Exception\AwsException;
use Aws\ResultInterface;
use Aws\S3\Exception\S3Exception;
use GuzzleHttp\Promise\Each;
use GuzzleHttp\Promise\PromiseInterface;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use stdClass;
use Symfony\Component\Stopwatch\Stopwatch;
use Throwable;

class S3Storage implements EventStorageInterface, LoggerAwareInterface, StatsdAwareInterface
{
    use LoggerAwareTrait;
    use StatsdAwareTrait;

    private mixed $s3;
    private bool $retried = false;

    public function __construct(
        private readonly string $environment,
        private readonly string $bucket,
        private readonly Stopwatch $stopwatch,
        S3ProxyFactory $s3Factory,
    )
    {
        $this->s3 = $s3Factory->build();
    }

    public function store(int $tenantId, int $eventId, EventData $data): void
    {
        try {
            $this->s3->putObject([
                'Bucket' => $this->bucket,
                'Key' => $this->generateKey($tenantId, $eventId),
                'Body' => Compression::compress((string) json_encode($data)),
            ]);
        } catch (CompressionException|S3Exception $e) {
            // Retry one more time if this fails
            if (!$this->retried) {
                $this->retried = true;
                $this->store($tenantId, $eventId, $data);
            } else {
                $this->logger->error('Could not save event data', ['exception' => $e]);
            }
        }
    }

    public function hydrateEvents(array $events): void
    {
        // Build a promise for the events that must be hydrated
        // in order to concurrently load data from S3.
        $promises = [];

        $this->stopwatch->start('s3.event.read.bulk');

        foreach ($events as $event) {
            $promises[] = $this->makeRetrievePromise($event);
        }

        // now resolve any promises before leaving the function
        if (count($promises) > 0) {
            Each::ofLimit($promises, 10)->wait();
        }

        $this->statsd->timing('s3.event.read.bulk', $this->stopwatch->getEvent('s3.event.read.bulk')->getDuration(), 1, [
            'count' => count($events),
        ]);
        $this->stopwatch->stop('s3.event.read.bulk');
    }

    private function generateKey(int $tenantId, int $eventId): string
    {
        return $this->environment.'/'.$tenantId.'/'.$eventId;
    }

    private function makeRetrievePromise(Event $event): PromiseInterface
    {
        return $this->s3->getObjectAsync([
            'Bucket' => $this->bucket,
            'Key' => $this->generateKey($event->tenant_id, $event->id),
        ])->then(
            function (ResultInterface $object) use ($event) {
                $data = EventData::fromJson(Compression::decompressIfNeeded($object['Body']));
                $event->hydrateEventData($data);
            }, function (Throwable $e) use ($event) {
                // Log any error unless the object does not exist
                if (!($e instanceof AwsException) || !(
                    404 == $e->getStatusCode() ||
                    str_contains($e->getMessage(), 'Connection died')) ||
                    str_contains($e->getMessage(), 'The provided token has expired') ||
                    str_contains($e->getMessage(), 'SSL_ERROR_SYSCALL')
                ) {
                    $this->logger->error('Could not retrieve event data', ['exception' => $e]);
                }
                $event->hydrateEventData(new EventData(new stdClass()));
            }
        );
    }
}
