<?php

namespace App\Core\Cron\ValueObjects;

use Symfony\Component\Console\Output\OutputInterface;

class Run
{
    const RESULT_SUCCEEDED = 'succeeded';

    const RESULT_LOCKED = 'locked';

    const RESULT_FAILED = 'failed';

    private array $output = [];
    private string $result = '';
    private OutputInterface $consoleOutput;

    /**
     * Writes output to the run.
     */
    public function writeOutput(string $str): void
    {
        if (empty($str)) {
            return;
        }

        $this->output[] = $str;

        if (isset($this->consoleOutput)) {
            $this->consoleOutput->writeln($str);
        }
    }

    /**
     * Gets the output from the run.
     */
    public function getOutput(): string
    {
        return trim(implode("\n", $this->output));
    }

    /**
     * Sets the result of the run.
     */
    public function setResult(string $result): void
    {
        $this->result = $result;
    }

    /**
     * Gets the result of the run.
     */
    public function getResult(): string
    {
        return $this->result;
    }

    /**
     * Checks if the run succeeded.
     */
    public function succeeded(): bool
    {
        return self::RESULT_SUCCEEDED == $this->result;
    }

    /**
     * Checks if the run failed.
     */
    public function failed(): bool
    {
        return self::RESULT_FAILED == $this->result;
    }

    /**
     * Sets the console output.
     */
    public function setConsoleOutput(OutputInterface $output): void
    {
        $this->consoleOutput = $output;
    }
}
