<?php

/**
 * @author Aaron Francis <aaron@tryhardstudios.com>
 *
 * @link https://aaronfrancis.com
 * @link https://x.com/aarondfrancis
 */

namespace SoloTerm\Solo\Support;

use RuntimeException;

class ProcessTracker
{
    /**
     * Maximum recursion depth for child process discovery.
     * Prevents stack overflow on malformed process trees.
     */
    protected const MAX_RECURSION_DEPTH = 50;

    /**
     * Cache TTL in milliseconds.
     */
    protected const CACHE_TTL_MS = 100;

    /**
     * Cached process list.
     */
    protected static ?array $processListCache = null;

    /**
     * Timestamp when cache was last updated.
     */
    protected static ?float $cacheTime = null;

    /**
     * Recursively find all child processes of a given PID.
     *
     * @param  int|string  $pid  Parent process ID
     * @param  array|null  $processes  Pre-fetched process list (for recursion)
     * @param  int  $depth  Current recursion depth
     * @return array List of child PIDs
     */
    public static function children($pid, $processes = null, int $depth = 0): array
    {
        // Prevent runaway recursion on malformed process trees
        if ($depth >= static::MAX_RECURSION_DEPTH) {
            return [];
        }

        if (is_null($processes)) {
            $processes = static::getProcessList();
        }

        $pid = (int) $pid;
        $children = [];
        $seen = [];

        foreach ($processes as $process) {
            $childPid = (int) $process['pid'];
            $parentPid = (int) $process['ppid'];

            // Use strict comparison and track seen PIDs to prevent circular references
            if ($parentPid === $pid && !isset($seen[$childPid])) {
                $seen[$childPid] = true;
                $children[] = $childPid;
                $children = array_merge(
                    $children,
                    static::children($childPid, $processes, $depth + 1)
                );
            }
        }

        return $children;
    }

    /**
     * Kill processes by PID.
     *
     * @param  array  $pids  PIDs to kill
     * @param  bool  $graceful  If true, try SIGTERM first before SIGKILL
     */
    public static function kill(array $pids, bool $graceful = false): void
    {
        if (empty($pids)) {
            return;
        }

        // Sanitize PIDs
        $pids = array_filter($pids, 'is_numeric');
        $pids = array_map('intval', $pids);

        if (empty($pids)) {
            return;
        }

        $pidList = implode(' ', $pids);

        if ($graceful) {
            // Try SIGTERM first for graceful shutdown
            exec("kill -15 {$pidList} > /dev/null 2>&1");
            usleep(100_000); // 100ms grace period
        }

        // SIGKILL to ensure termination
        exec("kill -9 {$pidList} > /dev/null 2>&1");
    }

    /**
     * Check if a single process is running.
     *
     * @param  int|string  $pid  Process ID to check
     * @return bool True if process is running
     *
     * @throws RuntimeException If PID is invalid
     */
    public static function isRunning($pid): bool
    {
        if (!is_numeric($pid)) {
            throw new RuntimeException("Invalid PID: {$pid}");
        }

        $pid = (int) $pid;
        $output = [];
        exec("ps -p {$pid} 2>/dev/null | grep {$pid}", $output);

        return count($output) > 0;
    }

    /**
     * Return all the PIDs that are running from a given list.
     *
     * @param  array  $pids  Array of PIDs to check
     * @return array Array of PIDs that are still running
     */
    public static function running(array $pids): array
    {
        $pids = array_filter($pids, 'is_numeric');

        if (empty($pids)) {
            return [];
        }

        $pids = array_unique($pids);
        $pids = array_map('intval', $pids);

        $output = [];
        // Construct the ps command to check multiple PIDs at once
        // -p specifies the PIDs to check
        // -o pid= outputs only the PID without headers
        exec('ps -p ' . implode(',', $pids) . ' -o pid= 2>/dev/null', $output, $returnCode);

        // Handle potential errors in executing the ps command
        if ($returnCode !== 0 && !empty($output)) {
            throw new RuntimeException('Error executing ps command: ' . implode("\n", $output));
        }

        // Trim whitespace and filter out any non-numeric entries from the output
        $runningPids = array_filter(array_map('trim', $output), 'is_numeric');

        // Convert running PIDs to integers for accurate comparison
        return array_map('intval', $runningPids);
    }

    /**
     * Get the system process list with caching.
     *
     * @return array Array of ['pid' => int, 'ppid' => int] entries
     *
     * @throws RuntimeException On unsupported OS
     */
    public static function getProcessList(): array
    {
        $now = microtime(true) * 1000;

        // Return cached list if still valid
        if (static::$processListCache !== null &&
            static::$cacheTime !== null &&
            ($now - static::$cacheTime) < static::CACHE_TTL_MS) {
            return static::$processListCache;
        }

        $output = [];
        $os = PHP_OS_FAMILY;

        if ($os === 'Darwin') {
            exec('ps -eo pid,ppid | tail -n +2', $output);
        } elseif ($os === 'Linux') {
            exec('ps -eo pid,ppid --no-headers', $output);
        } else {
            throw new RuntimeException("Unsupported operating system: $os");
        }

        $processes = [];
        foreach ($output as $line) {
            $parts = preg_split('/\s+/', trim($line), 2);
            if (count($parts) === 2) {
                $processes[] = [
                    'pid' => (int) $parts[0],
                    'ppid' => (int) $parts[1],
                ];
            }
        }

        // Update cache
        static::$processListCache = $processes;
        static::$cacheTime = $now;

        return $processes;
    }

    /**
     * Clear the process list cache.
     * Useful for testing or forcing a fresh read.
     */
    public static function clearCache(): void
    {
        static::$processListCache = null;
        static::$cacheTime = null;
    }
}
