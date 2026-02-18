<?php

namespace App\Integrations\NetSuite\ValueObjects;

use App\Integrations\NetSuite\Interfaces\PathProviderInterface;

class NetSuiteRetryPathProvider implements PathProviderInterface
{
    public function getDeploymentId(): string
    {
        return 'customdeploy_invd_retry_record';
    }

    public function getScriptId(): string
    {
        return 'customscript_invd_retry_record';
    }
}
