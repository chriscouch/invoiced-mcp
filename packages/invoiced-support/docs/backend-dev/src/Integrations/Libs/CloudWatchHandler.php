<?php

namespace App\Integrations\Libs;

use Maxbanton\Cwh\Handler\CloudWatch;
use Monolog\Formatter\FormatterInterface;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Throwable;

class CloudWatchHandler extends CloudWatch implements LoggerAwareInterface
{
    use LoggerAwareTrait;

    public function write(array $record): void
    {
        try {
            parent::write($record);
        } catch (Throwable $e) {
            // suppress exceptions to not disrupt application flow
            if (isset($this->logger)) {
                $this->logger->error('Could not write to CloudWatch', ['exception' => $e]);
            }
        }
    }

    protected function getDefaultFormatter(): FormatterInterface
    {
        return new CloudWatchFormatter();
    }
}
