# Process Management Improvements Plan

This document outlines findings and proposed improvements for Solo's process management system, covering spawning, output buffering, termination, pause/resume, and monitoring.

---

## Executive Summary

After deep analysis of the process management code, I've identified **13 potential improvements** across 5 categories:

| Category | Critical | High | Medium | Low |
|----------|----------|------|--------|-----|
| Correctness | 1 | 2 | 2 | - |
| Performance | - | 1 | 3 | 2 |
| Robustness | 1 | 1 | 1 | - |
| Code Quality | - | - | 2 | 1 |
| **Total** | **2** | **4** | **8** | **3** |

---

## Current Architecture Overview

```
┌─────────────────────────────────────────────────────────────────┐
│                         Solo Command                             │
│  ┌─────────────────┐    ┌─────────────────────────────────────┐ │
│  │ Monitor Process │    │           Dashboard                  │ │
│  │ (background)    │    │  ┌─────────────────────────────────┐│ │
│  │                 │    │  │         Event Loop (25ms)       ││ │
│  │ Watches parent  │    │  │  ┌──────────┐  ┌──────────┐    ││ │
│  │ PID, kills      │    │  │  │ Command 1│  │ Command 2│ ...││ │
│  │ orphans on      │    │  │  │┌────────┐│  │┌────────┐│    ││ │
│  │ crash           │    │  │  ││Process ││  ││Process ││    ││ │
│  │                 │    │  │  ││(Screen)││  ││(Screen)││    ││ │
│  └────────┬────────┘    │  │  │└────────┘│  │└────────┘│    ││ │
│           │             │  │  └──────────┘  └──────────┘    ││ │
│           │             │  └─────────────────────────────────┘│ │
│           └─────────────┼──────────────────────────────────────┤ │
│                         │          ProcessTracker              │ │
│                         │    (child PID discovery & cleanup)   │ │
│                         └─────────────────────────────────────┘ │
└─────────────────────────────────────────────────────────────────┘
```

**Key Components:**
- `ManagesProcess` trait: Core process lifecycle (spawn, output, terminate)
- `ProcessTracker`: Child PID discovery and batch termination
- `Monitor` command: Background watchdog for crash cleanup
- `Dashboard`: Event loop coordination

---

## Category 1: Correctness Issues

### Issue C1: Output Collection Relies on Framework Internals (CRITICAL)

**Location:** `ManagesProcess.php:414-418`

**Current Behavior:**
```php
// A bit of a hack, but there's no other way in...
$running = $this->process?->running();
// Calling running() triggers internal readPipes() which invokes output callback
```

**Problem:**
- Core output collection depends on undocumented side effect
- If Laravel/Symfony changes `isRunning()` internals, Solo breaks silently
- No explicit output polling mechanism

**Proposed Fix:**
```php
protected function collectIncrementalOutput(): void
{
    if (!$this->process) {
        return;
    }

    // Option A: Use Symfony's explicit output fetching
    $this->withSymfonyProcess(function (SymfonyProcess $process) {
        // Force pipe read without relying on side effects
        $process->getIncrementalOutput();
        $process->getIncrementalErrorOutput();
    });

    // Or Option B: Direct pipe reading via proc_get_status + stream_select
    // (more control but more complex)

    $running = $this->process->running();
    // ... rest of method
}
```

**Alternative Approach:** Use Symfony's `Process::getIterator()` or `mustRun()` patterns designed for incremental output.

**Impact:** HIGH - Prevents silent breakage on framework updates

---

### Issue C2: Race Condition in Child Process Discovery (HIGH)

**Location:** `ManagesProcess.php:250-264`

**Current Behavior:**
```php
public function stop(): void
{
    $this->children = ProcessTracker::children($this->process->id());
    // ... sends SIGTERM to $this->children
}
```

**Problem:**
1. Child list captured once at stop time
2. If process spawns new children after SIGTERM, they're not tracked
3. New children become orphans, reparented to init

**Scenario:**
```
stop() called → children = [1234, 1235]
                    ↓
Process 1234 receives SIGTERM
Process 1234 forks child 1236 during cleanup handler
Process 1234 exits
                    ↓
ProcessTracker::kill([1234, 1235]) → 1236 not killed!
```

**Proposed Fix:**
```php
public function stop(): void
{
    $this->stopping = true;
    $this->whenStopping();

    if ($this->processRunning()) {
        $this->stopInitiatedAt ??= Carbon::now();
        $this->sendTermSignals();
    }
}

protected function sendTermSignals(): void
{
    // Re-enumerate children each time to catch new ones
    $this->children = ProcessTracker::children($this->process->id());

    foreach ($this->children as $pid) {
        $command = trim(shell_exec("ps -o command= -p $pid 2>/dev/null"));
        if (!Str::startsWith($command, 'screen') && !Str::startsWith($command, 'SCREEN')) {
            posix_kill((int) $pid, SIGTERM);
        }
    }
}

protected function marshalProcess(): void
{
    // ... existing logic ...

    // Re-send SIGTERM to any new children during grace period
    if ($this->stopping && $this->processRunning()) {
        $this->sendTermSignals();
    }

    // ... timeout handling ...
}
```

**Impact:** HIGH - Prevents orphaned processes

---

### Issue C3: ProcessTracker Recursion Has No Depth Limit (HIGH)

**Location:** `ProcessTracker.php:16-35`

**Current Behavior:**
```php
public static function children($pid, $processes = null)
{
    foreach ($processes as $process) {
        if ($process['ppid'] == $pid) {
            $children[] = $process['pid'];
            $children = array_merge($children, static::children($process['pid'], $processes));
        }
    }
}
```

**Problem:**
- No maximum recursion depth
- Malformed process trees (circular references) cause stack overflow
- Deeply nested process trees could exhaust stack

**Proposed Fix:**
```php
public static function children($pid, $processes = null, int $depth = 0, int $maxDepth = 50): array
{
    if ($depth >= $maxDepth) {
        return []; // Prevent runaway recursion
    }

    if (is_null($processes)) {
        $processes = static::getProcessList();
    }

    $children = [];
    $seen = []; // Prevent circular references

    foreach ($processes as $process) {
        if ($process['ppid'] == $pid && !isset($seen[$process['pid']])) {
            $seen[$process['pid']] = true;
            $children[] = $process['pid'];
            $children = array_merge(
                $children,
                static::children($process['pid'], $processes, $depth + 1, $maxDepth)
            );
        }
    }

    return $children;
}
```

**Impact:** MEDIUM - Prevents potential crashes on malformed trees

---

### Issue C4: Type Juggling in PID Comparison (MEDIUM)

**Location:** `ProcessTracker.php:25`

**Current Behavior:**
```php
if ($process['ppid'] == $pid) {  // Loose comparison
```

**Problem:**
- PHP type juggling: `"12" == 12` is true, but `"12" == "123"` depends on context
- Could cause false positives in edge cases

**Proposed Fix:**
```php
if ((int)$process['ppid'] === (int)$pid) {  // Strict comparison with cast
```

**Impact:** LOW - Edge case correctness

---

### Issue C5: Missing Error Handling in stop() Shell Commands (MEDIUM)

**Location:** `ManagesProcess.php:257`

**Current Behavior:**
```php
$command = trim(shell_exec("ps -o command= -p $pid"));
```

**Problem:**
- `shell_exec()` returns null on failure
- `trim(null)` returns empty string in PHP 8, but behavior varies
- No error logging for debugging

**Proposed Fix:**
```php
$command = @shell_exec("ps -o command= -p $pid 2>/dev/null");
if ($command === null || $command === false) {
    // Process may have already exited, skip
    continue;
}
$command = trim($command);
```

**Impact:** LOW - Defensive coding

---

## Category 2: Performance Issues

### Issue P1: Process List Scan on Every Stop (HIGH)

**Location:** `ProcessTracker.php:94-114`, called from `ManagesProcess.php:251`

**Current Behavior:**
```php
public static function getProcessList()
{
    exec('ps -eo pid,ppid | tail -n +2', $output);  // Scans ALL system processes
    // Parses 100-500+ entries
}
```

**Problem:**
- Full system process table scan on every `stop()` call
- O(n) where n = all system processes
- Spawns shell process for `ps` command
- Called synchronously in event loop

**Proposed Fix Option A: Cache with TTL**
```php
private static ?array $processListCache = null;
private static ?float $cacheTime = null;
private const CACHE_TTL_MS = 100; // 100ms cache

public static function getProcessList(): array
{
    $now = microtime(true) * 1000;

    if (self::$processListCache !== null &&
        ($now - self::$cacheTime) < self::CACHE_TTL_MS) {
        return self::$processListCache;
    }

    // ... existing exec logic ...

    self::$processListCache = $processes;
    self::$cacheTime = $now;

    return $processes;
}

public static function clearCache(): void
{
    self::$processListCache = null;
}
```

**Proposed Fix Option B: Use /proc filesystem (Linux only)**
```php
public static function getProcessListLinux(): array
{
    $processes = [];

    foreach (glob('/proc/[0-9]*') as $procDir) {
        $pid = basename($procDir);
        $statusFile = "$procDir/status";

        if (!is_readable($statusFile)) {
            continue;
        }

        $status = file_get_contents($statusFile);
        if (preg_match('/^PPid:\s*(\d+)/m', $status, $matches)) {
            $processes[] = ['pid' => (int)$pid, 'ppid' => (int)$matches[1]];
        }
    }

    return $processes;
}
```

**Impact:** MEDIUM - Reduces latency during shutdown

---

### Issue P2: lsof Parsing in Resize Handler (MEDIUM)

**Location:** `ManagesProcess.php:298-332`

**Current Behavior:**
```php
public function sendSizeViaStty(): void
{
    exec(sprintf('lsof -p %d 2>/dev/null', $pid), $output);  // Expensive!
    foreach ($output as $line) {
        // Regex match on every line
    }
}
```

**Problem:**
- `lsof` is slow (can block for seconds on loaded systems)
- Called on every SIGWINCH (terminal resize)
- No debouncing for rapid resizes (dragging window edge)
- Regex per line of output

**Proposed Fix:**
```php
private ?string $cachedPtyDevice = null;
private ?int $ptyDevicePid = null;

public function sendSizeViaStty(): void
{
    $pid = $this->process?->id();
    if (!$pid) {
        return;
    }

    // Cache PTY device path per process
    if ($this->ptyDevicePid !== $pid) {
        $this->cachedPtyDevice = $this->discoverPtyDevice($pid);
        $this->ptyDevicePid = $pid;
    }

    if (!$this->cachedPtyDevice) {
        return;
    }

    exec(sprintf(
        'stty rows %d cols %d < %s 2>/dev/null',
        $this->scrollPaneHeight(),
        $this->scrollPaneWidth(),
        escapeshellarg($this->cachedPtyDevice)
    ));
}

// Add debouncing in Dashboard::handleResize()
private ?float $lastResizeTime = null;

public function handleResize(): false
{
    $now = microtime(true);
    if ($this->lastResizeTime && ($now - $this->lastResizeTime) < 0.1) {
        return false; // Debounce: max 10 resizes/second
    }
    $this->lastResizeTime = $now;

    // ... existing resize logic ...
}
```

**Impact:** MEDIUM - Reduces blocking during window resize

---

### Issue P3: String Concatenation in Output Callback (MEDIUM)

**Location:** `ManagesProcess.php:234-236`

**Current Behavior:**
```php
$this->process = $this->createPendingProcess()->start(null, function ($type, $buffer) {
    $this->partialBuffer .= $buffer;  // String concat in hot path
});
```

**Problem:**
- PHP string concatenation with `.=` causes reallocation
- Called potentially hundreds of times per second
- On large buffers (10KB+), each concat is O(n)

**Proposed Fix:**
```php
// Use array of chunks, join only when flushing
protected array $outputChunks = [];
protected int $outputChunksSize = 0;

// In output callback:
$this->outputChunks[] = $buffer;
$this->outputChunksSize += strlen($buffer);

// In collectIncrementalOutput:
if ($this->outputChunksSize > 0) {
    $this->partialBuffer = implode('', $this->outputChunks);
    $this->outputChunks = [];
    $this->outputChunksSize = 0;
}
```

**Impact:** LOW - Marginal improvement, PHP 8 already optimizes string concat

---

### Issue P4: Grapheme Regex on Every Large Buffer (MEDIUM)

**Location:** `ManagesProcess.php:461-475`

**Current Behavior:**
```php
public function sliceBeforeLogicalCharacterBoundary(string $input): string
{
    $success = preg_match_all("/\X/u", $input, $matches);  // Expensive!
    return implode('', array_splice($matches[0], 0, -1));
}
```

**Problem:**
- `/\X/u` (grapheme cluster regex) is expensive on large strings
- Creates array of all grapheme clusters (potentially thousands)
- Called when buffer > 10KB and no newlines/ANSI codes found

**Proposed Fix:**
```php
public function sliceBeforeLogicalCharacterBoundary(string $input): string
{
    $len = strlen($input);

    // Fast path: find last safe UTF-8 boundary by scanning backwards
    for ($i = $len - 1; $i >= max(0, $len - 6); $i--) {
        $byte = ord($input[$i]);

        // If this is a leading byte (not a continuation), we found a boundary
        if (($byte & 0xC0) !== 0x80) {
            return substr($input, 0, $i);
        }
    }

    // Fallback to regex only if fast path fails
    $success = preg_match_all("/\X/u", $input, $matches);
    if (!$success) {
        return head(SafeBytes::parse($input));
    }

    return implode('', array_splice($matches[0], 0, -1));
}
```

**Impact:** LOW - Only affects edge case (large buffer without newlines)

---

### Issue P5: Carbon Object Creation for Rate Limiting (LOW)

**Location:** `ManagesProcess.php:383`

**Current Behavior:**
```php
if (Carbon::now()->microsecond < 25_000) {
    $this->addLine('Waiting...');
}
```

**Problem:**
- Creates new Carbon/DateTime object every tick during shutdown
- Called 40x/second during shutdown grace period

**Proposed Fix:**
```php
// Use simple counter instead
private int $waitingMessageCounter = 0;

// In marshalProcess:
if ($this->waitingMessageCounter++ % 40 === 0) {  // Every ~1 second at 40 FPS
    $this->addLine('Waiting...');
}

// Reset counter when stopping completes
$this->waitingMessageCounter = 0;
```

**Impact:** NEGLIGIBLE - Micro-optimization

---

## Category 3: Robustness Issues

### Issue R1: Monitor Process May Miss New Children (CRITICAL)

**Location:** `Monitor.php:29-44`

**Current Behavior:**
```php
while (true) {
    $children = array_unique([...$children, ...ProcessTracker::children($parent)]);

    if (Carbon::now()->second % 10 === 0) {
        $children = ProcessTracker::running($children);
    }

    sleep(1);  // 1 second between iterations

    if (ProcessTracker::isRunning($parent)) {
        continue;
    }
    // ... cleanup
}
```

**Problem:**
1. 1-second polling interval means up to 1 second of orphan accumulation
2. If parent crashes and child spawns quickly after, child may not be tracked
3. `second % 10 === 0` check is imprecise (could skip if sleep timing drifts)

**Proposed Fix:**
```php
public function handle()
{
    $parent = $this->argument('pid');
    $children = [];
    $lastCullTime = time();

    while (true) {
        // More frequent child discovery (250ms instead of 1s)
        usleep(250_000);

        $newChildren = ProcessTracker::children($parent);
        $children = array_unique([...$children, ...$newChildren]);

        // Time-based culling instead of second-based
        if (time() - $lastCullTime >= 10) {
            $children = ProcessTracker::running($children);
            $lastCullTime = time();
        }

        if (ProcessTracker::isRunning($parent)) {
            continue;
        }

        // Parent died - more aggressive cleanup
        $this->warn("Parent process {$parent} has died.");

        // Give less time for graceful exit (was 2s, now 500ms)
        usleep(500_000);

        // Final child enumeration to catch any last spawns
        $children = array_unique([
            ...$children,
            ...ProcessTracker::children($parent)
        ]);

        $children = array_diff($children, [getmypid()]);
        ProcessTracker::kill($children);

        break;
    }
}
```

**Impact:** MEDIUM - More reliable orphan cleanup

---

### Issue R2: No Graceful SIGTERM in ProcessTracker::kill() (HIGH)

**Location:** `ProcessTracker.php:37-46`

**Current Behavior:**
```php
public static function kill(array $pids)
{
    exec("kill -9 {$pidList} > /dev/null 2>&1");  // Always SIGKILL
}
```

**Problem:**
- SIGKILL doesn't allow processes to clean up (temp files, DB connections)
- By the time `kill()` is called, SIGTERM was already sent via `posix_kill()`
- But any NEW children discovered during grace period never got SIGTERM

**Proposed Fix:**
```php
public static function kill(array $pids, bool $graceful = false): void
{
    if (empty($pids)) {
        return;
    }

    $pidList = implode(' ', array_map('intval', $pids));

    if ($graceful) {
        // Try SIGTERM first
        exec("kill -15 {$pidList} > /dev/null 2>&1");
        usleep(100_000); // 100ms grace
    }

    // Then SIGKILL to ensure termination
    exec("kill -9 {$pidList} > /dev/null 2>&1");
}
```

**Impact:** LOW - Marginal improvement in cleanup behavior

---

### Issue R3: Reflection on Private Laravel Property (MEDIUM)

**Location:** `ManagesProcess.php:348-356`

**Current Behavior:**
```php
protected function withSymfonyProcess(Closure $callback)
{
    $process = (new ReflectionClass(InvokedProcess::class))
        ->getProperty('process')
        ->getValue($this->process);

    return $callback($process);
}
```

**Problem:**
- Depends on internal property name `process`
- No null checks
- If Laravel renames property, breaks silently

**Proposed Fix:**
```php
protected function withSymfonyProcess(Closure $callback)
{
    if (!$this->process) {
        return null;
    }

    try {
        $reflection = new ReflectionClass(InvokedProcess::class);

        if (!$reflection->hasProperty('process')) {
            // Log warning and return gracefully
            Log::warning('Solo: InvokedProcess internal structure changed');
            return null;
        }

        $property = $reflection->getProperty('process');
        $property->setAccessible(true);
        $symfonyProcess = $property->getValue($this->process);

        if (!$symfonyProcess instanceof SymfonyProcess) {
            return null;
        }

        return $callback($symfonyProcess);
    } catch (ReflectionException $e) {
        Log::warning('Solo: Failed to access Symfony process', ['error' => $e->getMessage()]);
        return null;
    }
}
```

**Impact:** LOW - Defensive coding for framework compatibility

---

## Category 4: Code Quality Improvements

### Issue Q1: Magic Number for Buffer Threshold (MEDIUM)

**Location:** `ManagesProcess.php:433`

**Current Behavior:**
```php
} elseif ($after > 10_240) {  // 10KB - why?
```

**Proposed Fix:**
```php
// Add as class constant with documentation
/**
 * Maximum buffer size before forced flush.
 * Chosen to balance:
 * - Memory usage (larger = more RAM)
 * - Responsiveness (smaller = more frequent flushes)
 * - UTF-8 safety (larger = more likely to find safe cut point)
 */
protected const MAX_BUFFER_SIZE = 10_240;

// In collectIncrementalOutput:
} elseif ($after > static::MAX_BUFFER_SIZE) {
```

**Impact:** LOW - Documentation and maintainability

---

### Issue Q2: Inconsistent Error Handling Patterns (MEDIUM)

**Location:** Various

**Current State:**
- `ProcessTracker::kill()` silences all errors
- `ProcessTracker::isRunning()` throws on invalid PID
- `ProcessTracker::running()` silently filters invalid PIDs
- `shell_exec` calls don't check return values

**Proposed Fix:**
Create consistent error handling policy:

```php
class ProcessTracker
{
    /**
     * @throws ProcessTrackerException on system command failure
     */
    public static function children($pid, $processes = null): array
    {
        // Validate input
        if (!is_numeric($pid)) {
            throw new ProcessTrackerException("Invalid PID: {$pid}");
        }
        // ...
    }

    /**
     * Best-effort kill - logs failures but doesn't throw
     */
    public static function kill(array $pids): void
    {
        // ... existing logic with logging
        if ($returnCode !== 0) {
            Log::debug('ProcessTracker::kill failed for some PIDs', ['pids' => $pids]);
        }
    }
}
```

**Impact:** LOW - Consistency and debuggability

---

### Issue Q3: Locale String Not Escaped (LOW)

**Location:** `ManagesProcess.php:145-150`

**Current Behavior:**
```php
protected function localeEnvironmentVariables()
{
    $locale = $this->utf8Locale();
    return "export LC_ALL={$locale}; export LANG={$locale}";  // Not escaped
}
```

**Problem:**
- While `utf8Locale()` returns sanitized values, the pattern is inconsistent
- All other command components use `escapeshellarg()`

**Proposed Fix:**
```php
protected function localeEnvironmentVariables()
{
    $locale = escapeshellarg($this->utf8Locale());
    return "export LC_ALL={$locale}; export LANG={$locale}";
}
```

**Impact:** NEGLIGIBLE - Consistency

---

## Implementation Priority Matrix

### Phase 1: Critical Fixes (Immediate)

| Issue | Description | Effort |
|-------|-------------|--------|
| C1 | Output collection side effect | Medium |
| R1 | Monitor polling improvements | Low |

### Phase 2: High Priority (Short-term)

| Issue | Description | Effort |
|-------|-------------|--------|
| C2 | Race condition in child tracking | Medium |
| C3 | Recursion depth limit | Low |
| P1 | Process list caching | Medium |
| R2 | Graceful kill option | Low |

### Phase 3: Medium Priority (Medium-term)

| Issue | Description | Effort |
|-------|-------------|--------|
| P2 | lsof caching + debounce | Medium |
| P3 | Output buffer optimization | Low |
| P4 | Grapheme boundary optimization | Low |
| C4 | Type juggling fix | Trivial |
| C5 | Error handling in stop() | Low |
| R3 | Reflection safety | Low |
| Q1 | Buffer constant | Trivial |

### Phase 4: Low Priority (Long-term)

| Issue | Description | Effort |
|-------|-------------|--------|
| P5 | Rate limiting micro-opt | Trivial |
| Q2 | Error handling consistency | Medium |
| Q3 | Locale escaping | Trivial |

---

## Testing Strategy

### Unit Tests to Add

1. **ProcessTracker Tests:**
   - `test_children_handles_circular_references`
   - `test_children_respects_depth_limit`
   - `test_kill_with_empty_array`
   - `test_running_filters_invalid_pids`

2. **ManagesProcess Tests:**
   - `test_output_collection_without_side_effects`
   - `test_stop_re_enumerates_children`
   - `test_buffer_flush_on_threshold`
   - `test_utf8_boundary_detection`

3. **Monitor Tests:**
   - `test_cleanup_on_parent_death`
   - `test_child_accumulation`

### Integration Tests

1. **Process Lifecycle:**
   - Start → Stop → Verify all children terminated
   - Start → Kill parent → Verify Monitor cleans up

2. **Stress Tests:**
   - Rapid start/stop cycles
   - High-output processes
   - Deep process trees

---

## Backward Compatibility

All proposed changes are backward compatible:

- New parameters have defaults
- New methods don't change existing signatures
- Config options preserved
- Public API unchanged

---

## Files to Modify

```
solo/src/
├── Commands/
│   └── Concerns/
│       └── ManagesProcess.php    (C1, C2, C5, P3, P4, P5, Q3)
├── Console/
│   └── Commands/
│       └── Monitor.php           (R1)
├── Support/
│   └── ProcessTracker.php        (C3, C4, P1, R2, Q2)
└── Prompt/
    └── Dashboard.php             (P2 - debounce)
```

---

## Conclusion

The current process management system is functional but has several areas for improvement:

1. **Most Critical:** Output collection's reliance on framework internals (C1) should be addressed first to prevent future breakage.

2. **Highest Impact:** Process list caching (P1) and child re-enumeration (C2) will provide the most noticeable improvements.

3. **Low-Hanging Fruit:** Recursion limits (C3), type fixes (C4), and constants (Q1) are quick wins.

The phased approach allows incremental improvements while maintaining stability.
