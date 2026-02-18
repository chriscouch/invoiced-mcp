<?php

namespace App\Core\Queue;

/**
 * Represents the queue that a job should be put into.
 *
 * Explanation of Levels
 * =====================
 * Normal: For quick-running jobs (< 120 seconds worst case).
 *         If the job exceeds this time window then it has
 *         a chance of being killed by the autoscaler.
 *
 * Batch: For long-running jobs (> 120 seconds worst case).
 *        These are protected from the autoscaler, but likely
 *        wait longer in the queue than the Normal level.
 */
enum QueueServiceLevel: string
{
    case Normal = 'normal';
    case Batch = 'batch';
}
