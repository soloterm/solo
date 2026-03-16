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
     * POSIX error code when a process exists but we lack permission.
     */
    protected const POSIX_PERMISSION_ERROR = 1;

    /**
     * Cached process list.
     */
    protected static ?array $processListCache = null;

    /**
     * Timestamp when cache was last updated.
     */
    protected static ?float $cacheTime = null;

    /**
     * Find all child processes of a given PID.
     *
     * @param  int|string  $pid  Parent process ID
     * @param  array|null  $processes  Pre-fetched process list
     * @param  int  $depth  Current traversal depth
     * @return array List of child PIDs
     */
    public static function children($pid, $processes = null, int $depth = 0): array
    {
        // Prevent runaway traversal on malformed process trees.
        if ($depth >= static::MAX_RECURSION_DEPTH) {
            return [];
        }

        if ($processes === null) {
            $processes = static::getProcessList();
        }

        $pid = (int) $pid;

        if ($pid <= 0) {
            return [];
        }

        $childrenByParent = [];

        foreach ($processes as $process) {
            $childPid = (int) ($process['pid'] ?? 0);
            $parentPid = (int) ($process['ppid'] ?? 0);

            if ($childPid <= 0) {
                continue;
            }

            $childrenByParent[$parentPid][] = $childPid;
        }

        $children = [];
        $seen = [$pid => true];
        $stack = [[$pid, $depth]];

        while (!empty($stack)) {
            [$currentPid, $currentDepth] = array_pop($stack);

            if ($currentDepth >= static::MAX_RECURSION_DEPTH) {
                continue;
            }

            $directChildren = $childrenByParent[$currentPid] ?? [];

            // Push in reverse so traversal order matches process-list order.
            for ($i = count($directChildren) - 1; $i >= 0; $i--) {
                $childPid = $directChildren[$i];

                if (isset($seen[$childPid])) {
                    continue;
                }

                $seen[$childPid] = true;
                $children[] = $childPid;
                $stack[] = [$childPid, $currentDepth + 1];
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
        $pids = static::sanitizePids($pids);

        if (empty($pids)) {
            return;
        }

        if ($graceful) {
            // Try SIGTERM first for graceful shutdown.
            static::signal($pids, SIGTERM);
            usleep(100_000); // 100ms grace period

            // Only escalate for processes still alive.
            $pids = static::running($pids);

            if (empty($pids)) {
                return;
            }

        }

        // SIGKILL to ensure termination.
        static::signal($pids, SIGKILL);
    }

    /**
     * Kill only PIDs whose command signature still matches.
     *
     * @param  array<int|string, string>  $pidCommandMap
     */
    public static function killMatchingCommands(array $pidCommandMap, bool $graceful = false): void
    {
        $pidCommandMap = static::sanitizePidCommandMap($pidCommandMap);

        if (empty($pidCommandMap)) {
            return;
        }

        $commandsByPid = static::commandsByPid(array_keys($pidCommandMap));
        $matchingPids = [];

        foreach ($pidCommandMap as $pid => $commandSnapshot) {
            if (($commandsByPid[$pid] ?? null) === $commandSnapshot) {
                $matchingPids[] = $pid;
            }
        }

        static::kill($matchingPids, $graceful);
    }

    /**
     * Send a signal to a list of PIDs.
     */
    public static function signal(array $pids, int $signal): void
    {
        $pids = static::sanitizePids($pids);

        if (empty($pids)) {
            return;
        }

        if (function_exists('posix_kill')) {
            foreach ($pids as $pid) {
                @posix_kill($pid, $signal);
            }

            return;
        }

        $pidList = implode(' ', $pids);
        static::executeCommand("kill -{$signal} {$pidList} > /dev/null 2>&1");
    }

    public static function isScreenCommand(string $command): bool
    {
        return (bool) preg_match('/^\s*screen(\s|$)/i', $command);
    }

    /**
     * Check if a single process is running.
     *
     * @param  int|string  $pid  Process ID to check
     * @param  array|null  $processes  Optional process snapshot for lookup
     * @return bool True if process is running
     *
     * @throws RuntimeException If PID is invalid
     */
    public static function isRunning($pid, ?array $processes = null): bool
    {
        if (!is_numeric($pid)) {
            throw new RuntimeException("Invalid PID: {$pid}");
        }

        $pid = (int) $pid;

        if ($pid <= 0) {
            throw new RuntimeException("Invalid PID: {$pid}");
        }

        if ($processes !== null) {
            return static::processListContainsPid($pid, $processes);
        }

        $exists = static::pidExistsViaSignal($pid);

        if ($exists !== null) {
            return $exists;
        }

        return in_array($pid, static::running([$pid]), true);
    }

    /**
     * Resolve process command lines by PID.
     *
     * @return array<int, string>
     */
    public static function commandsByPid(array $pids): array
    {
        $pids = static::sanitizePids($pids);

        if (empty($pids)) {
            return [];
        }

        $output = static::executeCommand(
            'ps -o pid=,command= -p ' . implode(',', $pids) . ' 2>/dev/null',
            $returnCode
        );

        if ($returnCode !== 0 && !empty($output)) {
            throw new RuntimeException('Error executing ps command: ' . implode("\n", $output));
        }

        $commands = [];

        foreach ($output as $line) {
            $parts = preg_split('/\s+/', trim($line), 2);

            if (count($parts) !== 2 || !is_numeric($parts[0])) {
                continue;
            }

            $commands[(int) $parts[0]] = $parts[1];
        }

        return $commands;
    }

    /**
     * Return all the PIDs that are running from a given list.
     *
     * @param  array  $pids  Array of PIDs to check
     * @return array Array of PIDs that are still running
     */
    public static function running(array $pids): array
    {
        $pids = static::sanitizePids($pids);

        if (empty($pids)) {
            return [];
        }

        $runningPids = [];
        $canUseSignalChecks = true;

        foreach ($pids as $pid) {
            $exists = static::pidExistsViaSignal($pid);

            if ($exists === null) {
                $canUseSignalChecks = false;
                break;
            }

            if ($exists) {
                $runningPids[] = $pid;
            }
        }

        if ($canUseSignalChecks) {
            return $runningPids;
        }

        // Fallback to ps when POSIX signal checks are unavailable.
        $output = static::executeCommand('ps -p ' . implode(',', $pids) . ' -o pid= 2>/dev/null', $returnCode);

        if ($returnCode !== 0 && !empty($output)) {
            throw new RuntimeException('Error executing ps command: ' . implode("\n", $output));
        }

        $runningPids = array_filter(array_map('trim', $output), 'is_numeric');

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
        $now = static::nowMilliseconds();

        // Return cached list if still valid.
        if (static::$processListCache !== null &&
            static::$cacheTime !== null &&
            ($now - static::$cacheTime) < static::CACHE_TTL_MS) {
            return static::$processListCache;
        }

        $command = static::processListCommand();
        $output = static::executeCommand($command, $returnCode);

        if ($returnCode !== 0 && !empty($output)) {
            throw new RuntimeException('Error executing ps command: ' . implode("\n", $output));
        }

        $processes = [];

        foreach ($output as $line) {
            $parts = preg_split('/\s+/', trim($line), 2);

            if (count($parts) !== 2 || !is_numeric($parts[0]) || !is_numeric($parts[1])) {
                continue;
            }

            $processes[] = [
                'pid' => (int) $parts[0],
                'ppid' => (int) $parts[1],
            ];
        }

        // Update cache.
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

    /**
     * @return array<int>
     */
    protected static function sanitizePids(array $pids): array
    {
        $pids = array_filter($pids, fn($pid) => is_numeric($pid) && (int) $pid > 0);
        $pids = array_map('intval', $pids);

        return array_values(array_unique($pids));
    }

    /**
     * @param  array<int|string, string>  $pidCommandMap
     * @return array<int, string>
     */
    protected static function sanitizePidCommandMap(array $pidCommandMap): array
    {
        $sanitized = [];

        foreach ($pidCommandMap as $pid => $command) {
            if (!is_numeric($pid) || (int) $pid <= 0 || !is_string($command) || trim($command) === '') {
                continue;
            }

            $sanitized[(int) $pid] = trim($command);
        }

        return $sanitized;
    }

    /**
     * Determine if a PID exists, or return null if signal checks are unavailable.
     */
    protected static function pidExistsViaSignal(int $pid): ?bool
    {
        if (!function_exists('posix_kill')) {
            return null;
        }

        if (@posix_kill($pid, 0)) {
            return true;
        }

        if (!function_exists('posix_get_last_error')) {
            return false;
        }

        $error = posix_get_last_error();

        // EPERM means the process exists but is owned by another user.
        if (
            (defined('POSIX_EPERM') && $error === POSIX_EPERM)
            || (!defined('POSIX_EPERM') && $error === static::POSIX_PERMISSION_ERROR)
        ) {
            return true;
        }

        return false;
    }

    protected static function processListContainsPid(int $pid, array $processes): bool
    {
        foreach ($processes as $process) {
            if ((int) ($process['pid'] ?? 0) === $pid) {
                return true;
            }
        }

        return false;
    }

    protected static function processListCommand(): string
    {
        return match (static::osFamily()) {
            'Darwin' => 'ps -eo pid=,ppid=',
            'Linux' => 'ps -eo pid,ppid --no-headers',
            default => throw new RuntimeException('Unsupported operating system: ' . static::osFamily()),
        };
    }

    protected static function osFamily(): string
    {
        return PHP_OS_FAMILY;
    }

    protected static function nowMilliseconds(): float
    {
        return microtime(true) * 1000;
    }

    /**
     * Execute a shell command and return output lines.
     *
     * @return array<int, string>
     */
    protected static function executeCommand(string $command, ?int &$returnCode = null): array
    {
        $output = [];
        exec($command, $output, $returnCode);

        return $output;
    }
}
