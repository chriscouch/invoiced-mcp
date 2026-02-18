<?php

namespace App\Integrations\NetSuite\Interfaces;

interface PathProviderInterface
{
    /**
     * Get url deployment id.
     */
    public function getDeploymentId(): string;

    /**
     * Gets url script id.
     */
    public function getScriptId(): string;
}
