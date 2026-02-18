<?php

namespace App\Core\Utils;

use Monolog\Processor\ProcessorInterface;
use Resque_Job;

class DebugProcessor implements ProcessorInterface
{
    public function __construct(private DebugContext $debugContext)
    {
    }

    public function __invoke(array $record): array
    {
        $record['extra']['correlation_id'] = $this->debugContext->getCorrelationId();

        // Override the correlation ID using the queue job correlation ID
        // when provided as an argument. The reason for that is the
        // correlation ID might not have been set yet on the debug context.
        if (isset($record['context']['job']) && $record['context']['job'] instanceof Resque_Job) {
            $job = $record['context']['job'];
            if (isset($job->payload['args'][0]['_correlation_id'])) {
                $record['extra']['correlation_id'] = $job->payload['args'][0]['_correlation_id'];
            }
        }

        return $record;
    }
}
