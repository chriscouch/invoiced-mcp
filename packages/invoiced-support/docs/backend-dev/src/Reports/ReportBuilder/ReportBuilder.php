<?php

namespace App\Reports\ReportBuilder;

use App\Companies\Models\Company;
use App\Companies\Models\Member;
use App\Reports\Exceptions\ReportException;
use App\Reports\Libs\ReportHelper;
use App\Reports\ValueObjects\Report;

class ReportBuilder
{
    public function __construct(
        private DataService $dataService,
        private Formatter $formatter,
        private ReportHelper $helper
    ) {
    }

    /**
     * @throws ReportException
     */
    public function build(string $input, Company $company, ?Member $member, array $parameters): Report
    {
        $company->useTimezone();
        $this->helper->switchTimezone($company->time_zone);
        $definition = DefinitionDeserializer::deserialize($input, $company, $member);

        $sectionData = [];
        foreach ($definition->getSections() as $section) {
            $sectionData[] = $this->dataService->fetchData($section->getDataQuery(), $parameters);
        }

        return $this->formatter->format($definition, $parameters, $sectionData);
    }
}
