<?php

namespace App\Reports\Libs;

use App\Reports\Exceptions\ReportException;
use App\Reports\Interfaces\PresetReportInterface;
use Symfony\Component\DependencyInjection\ServiceLocator;

class PresetReportFactory
{
    public function __construct(
        private ServiceLocator $reportLocator,
    ) {
    }

    /**
     * Gets a preset report using the report identifier.
     *
     * @throws ReportException if the report does not exist
     */
    public function get(string $type): PresetReportInterface
    {
        if (!$this->reportLocator->has($type)) {
            throw new ReportException('No such report: '.$type);
        }

        return $this->reportLocator->get($type);
    }

    /**
     * Gets a list of all available preset reports.
     * For use in tests only.
     */
    public function all(): array
    {
        return $this->reportLocator->getProvidedServices();
    }
}
