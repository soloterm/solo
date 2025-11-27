<?php

/**
 * @author Aaron Francis <aaron@tryhardstudios.com>
 *
 * @link https://aaronfrancis.com
 * @link https://x.com/aarondfrancis
 */

namespace SoloTerm\Solo\Console\Commands;

use Illuminate\Console\Command;
use SoloTerm\Solo\Support\ProcessTracker;

class Monitor extends Command
{
    /**
     * Polling interval in microseconds (250ms).
     * Faster polling catches new children more quickly.
     */
    protected const POLL_INTERVAL_US = 250_000;

    /**
     * Interval for culling dead children (10 seconds).
     */
    protected const CULL_INTERVAL_S = 10;

    /**
     * Grace period after parent death before killing children (500ms).
     */
    protected const GRACE_PERIOD_US = 500_000;

    protected $signature = 'solo:monitor {pid}';

    protected $description = 'Watch for the stray processes and clean them up.';

    public function handle()
    {
        $parent = $this->argument('pid');
        $children = [];
        $lastCullTime = time();

        $this->info("Monitoring parent process PID: {$parent}");

        while (true) {
            // Discover new children
            $newChildren = ProcessTracker::children($parent);
            $children = array_unique([...$children, ...$newChildren]);

            // Time-based culling (more reliable than second % 10)
            $now = time();
            if (($now - $lastCullTime) >= static::CULL_INTERVAL_S) {
                $children = ProcessTracker::running($children);
                $lastCullTime = $now;
            }

            // Shorter sleep for more responsive detection
            usleep(static::POLL_INTERVAL_US);

            if (ProcessTracker::isRunning($parent)) {
                continue;
            }

            $this->warn("Parent process {$parent} has died.");

            // Short grace period for processes to exit on their own
            usleep(static::GRACE_PERIOD_US);

            // Final child enumeration to catch any last spawns
            $finalChildren = ProcessTracker::children($parent);
            $children = array_unique([...$children, ...$finalChildren]);

            // Don't kill ourselves
            $children = array_diff($children, [getmypid()]);

            if (!empty($children)) {
                ProcessTracker::kill($children);
                $this->warn('Killed processes: ' . implode(', ', $children));
            }

            $this->info('All tracked child processes cleaned up. Exiting.');

            break;
        }
    }
}
