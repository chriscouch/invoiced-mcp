<?php

namespace App\Integrations\Libs;

use App\Companies\Models\Company;
use App\Core\Utils\DebugContext;

class IntegrationLogProcessor
{
    public function __construct(private Company $company, private DebugContext $debugContext)
    {
    }

    public function __invoke(array $record): array
    {
        $record['context']['channel'] = $record['channel'];
        $record['context']['tenant_id'] = $this->company->id();
        $record['context']['correlation_id'] = $this->debugContext->getCorrelationId();
        $record['context']['request_id'] = $this->debugContext->getRequestId();
        $record['context']['environment'] = $this->debugContext->getEnvironment();

        return $record;
    }
}
