<?php

namespace App\Core\Queue\Interfaces;

interface ResqueJobInterface
{
    /**
     * Executes a queued job.
     */
    public function perform(): void;
}
