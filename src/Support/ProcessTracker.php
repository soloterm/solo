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
    public static function children($pid, $processes = null)
    {
        if (is_null($processes)) {
            $processes = static::getProcessList();
        }

        $children = [];

        foreach ($processes as $process) {
            if ($process['ppid'] == $pid) {
                $children[] = $process['pid'];
                $children = array_merge(
                    $children,
                    static::children($process['pid'], $processes)
                );
            }
        }

        return $children;
    }

    public static function kill(array $pids)
    {
        if (empty($pids)) {
            return;
        }

        $pidList = implode(' ', $pids);

        exec("kill -9 {$pidList} > /dev/null 2>&1");
    }

    public static function isRunning($pid)
    {
        if (!is_numeric($pid)) {
            throw new RuntimeException("Invalid PID: {$pid}");
        }

        $output = [];
        exec("ps -p {$pid} | grep {$pid}", $output);

        return count($output) > 0;
    }

    /**
     * Return all the PIDs that are running.
     *
     * @param  array  $pids  Array of PIDs to check.
     * @return array Associative array with PIDs as keys and boolean as values indicating if they are running.
     */
    public static function running(array $pids): array
    {
        $pids = array_filter($pids, 'is_numeric');

        if (empty($pids)) {
            return [];
        }

        $pids = array_unique($pids);
        $pids = array_map('intval', $pids);

        // Construct the ps command to check multiple PIDs at once
        // -p specifies the PIDs to check
        // -o pid= outputs only the PID without headers
        exec('ps -p ' . implode(',', $pids) . ' -o pid=', $output, $returnCode);

        // Handle potential errors in executing the ps command
        if ($returnCode !== 0 && !empty($output)) {
            throw new RuntimeException('Error executing ps command: ' . implode("\n", $output));
        }

        // Trim whitespace and filter out any non-numeric entries from the output
        $runningPids = array_filter(array_map('trim', $output), 'is_numeric');

        // Convert running PIDs to integers for accurate comparison
        return array_map('intval', $runningPids);
    }

    public static function getProcessList()
    {
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
            [$childPid, $parentPid] = explode(' ', trim(preg_replace('/\s+/', ' ', $line)), 2);
            $processes[] = ['pid' => $childPid, 'ppid' => $parentPid];
        }

        return $processes;
    }
}
