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
        $parent = (int) $this->argument('pid');

        if ($parent <= 0) {
            $this->error('Invalid PID provided to monitor command.');

            return self::FAILURE;
        }

        /** @var array<int, true> $children */
        $children = [];
        $lastCullTime = time();

        $this->info("Monitoring parent process PID: {$parent}");

        while (true) {
            $processes = ProcessTracker::getProcessList();

            // Discover new children
            $newChildren = ProcessTracker::children($parent, $processes);

            foreach ($newChildren as $childPid) {
                $children[(int) $childPid] = true;
            }

            // Time-based culling (more reliable than second % 10)
            $now = time();
            if (($now - $lastCullTime) >= static::CULL_INTERVAL_S) {
                $children = array_fill_keys(ProcessTracker::running(array_keys($children)), true);
                $lastCullTime = $now;
            }

            if (ProcessTracker::isRunning($parent, $processes)) {
                // Short sleep while parent is still alive for responsive detection.
                usleep(static::POLL_INTERVAL_US);
                continue;
            }

            $this->warn("Parent process {$parent} has died.");

            // Short grace period for processes to exit on their own
            usleep(static::GRACE_PERIOD_US);

            // Final child enumeration to catch any last spawns
            $finalChildren = ProcessTracker::children($parent, ProcessTracker::getProcessList());

            foreach ($finalChildren as $childPid) {
                $children[(int) $childPid] = true;
            }

            $trackedChildren = array_keys($children);

            // Don't kill ourselves
            $trackedChildren = array_values(array_diff($trackedChildren, [getmypid()]));
            $trackedChildren = ProcessTracker::running($trackedChildren);

            if (!empty($trackedChildren)) {
                ProcessTracker::kill($trackedChildren, graceful: true);
                $this->warn('Killed processes: ' . implode(', ', $trackedChildren));
            }

            $this->info('All tracked child processes cleaned up. Exiting.');

            return self::SUCCESS;
        }
    }
}
