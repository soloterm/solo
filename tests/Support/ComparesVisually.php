<?php

declare(strict_types=1);

/**
 * @author Aaron Francis <aaron@tryhardstudios.com>
 *
 * @link https://aaronfrancis.com
 * @link https://x.com/aarondfrancis
 */

namespace SoloTerm\Solo\Tests\Support;

use Exception;
use Illuminate\Support\Str;
use Laravel\Prompts\Terminal;
use PHPUnit\Framework\Attributes\Test;
use ReflectionClass;
use SoloTerm\Solo\Support\Screen;

use function Orchestra\Testbench\package_path;

trait ComparesVisually
{
    protected $testsPerMethod = [
        //
    ];

    protected ?array $uniqueTestIdentifier = null;

    /**
     * Asserts that the given $content visually matches what would appear in iTerm.
     * This method takes screenshots of both the raw content rendered in iTerm and
     * an emulated version, then compares them pixel-by-pixel.
     *
     * @throws Exception
     */
    public function assertTerminalMatch(array|string $content, $iterate = false): void
    {
        // Just a little convenience for passing in a bunch of content.
        if (is_array($content) && !$iterate) {
            $content = implode(PHP_EOL, $content);
        }

        if (is_string($content)) {
            $content = [$content];
        }

        $this->uniqueTestIdentifier = $this->uniqueTestIdentifier();

        $shouldRunVisualTest = getenv('ENABLE_SCREENSHOT_TESTING') === '1'
            || getenv('ENABLE_SCREENSHOT_TESTING') === '2' && $this->getFixture($content) === false;

        if ($shouldRunVisualTest) {
            $this->withOutputEnabled(fn() => $this->assertVisualMatch($content));
        } else {
            $this->assertFixtureMatch($content);
        }
    }

    protected function getFixture(array $content)
    {
        if (!file_exists($this->fixturePath())) {
            return false;
        }

        $fixture = file_get_contents($this->fixturePath());
        $fixture = json_decode($fixture, true);

        if ($fixture['checksum'] !== md5(json_encode($content))) {
            return false;
        }

        return $fixture;
    }

    protected function assertFixtureMatch(array $content): bool
    {
        $fixture = $this->getFixture($content);

        if (!$fixture) {
            $this->markTestSkipped('Fixture does not exist for ' . $this->uniqueTestIdentifier[1]);
        }

        $screen = new Screen($fixture['width'], $fixture['height']);

        foreach ($content as $c) {
            $screen->write($c);
        }

        $this->assertEquals($fixture['output'], $screen->output());

        return true;
    }

    protected function assertVisualMatch(array $content, $attempt = 1)
    {
        $itermPath = $this->screenshotPath('iterm');
        $emulatedPath = $this->screenshotPath('emulated');

        $this->captureCleanOutput($itermPath, $content);

        $screen = $this->makeIdenticalScreen();

        foreach ($content as $c) {
            $screen->write($c);
        }

        $emulated = $screen->output();

        $this->captureCleanOutput($emulatedPath, [$emulated]);

        $matched = $this->terminalAreaIsIdentical($itermPath, $emulatedPath);

        // Due to the nature of screenshotting etc, these can be flaky.
        if (!$matched && $attempt === 1) {
            $this->assertVisualMatch($content, ++$attempt);

            return;
        }

        if ($matched) {
            $this->writeFixtureFile($content);
        }

        $this->assertTrue(
            $matched,
            'Failed asserting that screenshots are identical. Diff available at ' . $this->screenshotPath('diff')
        );
    }

    protected function writeFixtureFile($content)
    {
        $this->ensureDirectoriesExist($this->fixturePath());

        $screen = $this->makeIdenticalScreen();

        foreach ($content as $c) {
            $screen->write($c);
        }

        file_put_contents($this->fixturePath(), json_encode([
            'checksum' => md5(json_encode($content)),
            'width' => $screen->width,
            'height' => $screen->height,
            'output' => $screen->output()
        ]));
    }

    /**
     * Find the debug backtrace frame that called `assertTerminalMatch()`.
     *
     * @throws Exception If the caller cannot be found.
     */
    protected function uniqueTestIdentifier(): array
    {
        $assertFound = false;

        foreach (debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS) as $frame) {
            if (str_ends_with($frame['file'], 'ComparesVisually.php')) {
                continue;
            }

            if (!isset($frame['class'])) {
                continue;
            }

            $reflection = new ReflectionClass($frame['class']);
            $method = $reflection->getMethod($frame['function']);
            $isTest = $method->getAttributes(Test::class);

            if (count($isTest)) {
                $path = Str::after($frame['class'], '\\Tests\\');
                $path = Str::replace('\\', '/', $path);
                $function = $frame['function'];

                $key = "$path::$function";

                if (!array_key_exists($key, $this->testsPerMethod)) {
                    $this->testsPerMethod[$key] = 0;
                }

                $function = $function . '_' . ++$this->testsPerMethod[$key];

                return [$path, $function];
            }
        }

        throw new Exception('Unable to find caller in debug backtrace.');
    }

    /**
     * Execute a callback with output buffering disabled, then restore it.
     *
     * @return mixed
     */
    protected function withOutputEnabled(callable $cb)
    {
        $obLevel = ob_get_level();

        // If no output buffering, just run the callback.
        if ($obLevel === 0) {
            return $cb();
        }

        // Flush current buffer and temporarily disable output buffering.
        $captured = ob_get_clean();

        try {
            return $cb();
        } finally {
            // Re-enable output buffering and restore captured output.
            ob_start();
            echo $captured;
        }
    }

    protected function ensureDirectoriesExist($path)
    {
        // Ensure directories exist
        $dir = pathinfo($path, PATHINFO_DIRNAME);
        if (!is_dir($dir) && !mkdir($dir, 0777, true) && !is_dir($dir)) {
            throw new Exception("Could not create directory $dir");
        }
    }

    /**
     * Capture the provided $content from iTerm by:
     * - Clearing the screen, writing $content.
     * - Taking a screenshot of the iTerm window.
     * - Restoring the terminal state.
     *
     * @param  string  $filename  The filename to save the screenshot to.
     * @param  string  $content  The content to be rendered in iTerm.
     *
     * @throws Exception If screencapture fails or iTerm window not found.
     */
    protected function captureCleanOutput(string $filename, array $content): void
    {
        $this->ensureDirectoriesExist($filename);

        $this->restoreTerminal();

        echo "\e[0m"; // Reset styles
        echo "\e[H"; // Move cursor home
        echo "\e[2J"; // Clear screen
        echo "\e[?25l"; // Hide cursor

        foreach ($content as $c) {
            echo $c;
            // Give time for the screen to update visually
            usleep(10_000);
        }

        // Obtain iTerm window ID
        $iterm = trim((string) shell_exec("osascript -e 'tell application \"iTerm\" to get the id of window 1'"));

        if (empty($iterm)) {
            $this->restoreTerminal();
            throw new Exception('Could not determine iTerm window ID. Is iTerm running and visible?');
        }

        // Check if screencapture command is available
        if (shell_exec('which screencapture') === null) {
            $this->restoreTerminal();
            throw new Exception('screencapture command not found.');
        }

        // Run screencapture
        retry(times: 3, callback: function () use ($iterm, $filename) {
            exec('screencapture -l ' . escapeshellarg($iterm) . ' -o -x ' . escapeshellarg($filename), $output,
                $result);

            if ($result !== 0) {
                throw new Exception("Screencapture failed!\n" . implode(PHP_EOL, $output));
            }
        });

        // Crop off the top bar, as it causes false positives
        exec(sprintf('convert %s -gravity North -chop 0x60 %s', escapeshellarg($filename), escapeshellarg($filename)));

        $this->restoreTerminal();
    }

    /**
     * Restore terminal styles and show the cursor.
     */
    protected function restoreTerminal(): void
    {
        echo "\ec"; // Brute force reset of terminal.
    }

    protected function screenshotPath(string $suffix): string
    {
        [$path, $function] = $this->uniqueTestIdentifier;

        return package_path("tests/Screenshots/{$path}/{$function}_{$suffix}.png");
    }

    protected function fixturePath(): string
    {
        [$path, $function] = $this->uniqueTestIdentifier;

        return package_path("tests/Fixtures/{$path}/{$function}.json");
    }

    /**
     * Compare two screenshots, ensuring they are identical within the terminal's display area.
     *
     * @throws Exception
     */
    protected function terminalAreaIsIdentical(string $term, string $emulated): bool
    {
        $diff = $this->screenshotPath('diff');

        if (shell_exec('which compare') === null) {
            throw new Exception('The `compare` tool (ImageMagick) is not installed or not in PATH.');
        }

        // Compare images and capture difference count
        $diff = shell_exec(sprintf('compare -metric AE %s %s %s 2>&1',
            escapeshellarg($term),
            escapeshellarg($emulated),
            escapeshellarg($diff),
        ));

        $matched = trim((string) $diff) === '0';

        if ($matched) {
            @unlink($term);
            @unlink($emulated);
            @unlink($diff);
        }

        return $matched;
    }

    /**
     * Create and return a Screen object matching the terminal's dimensions.
     */
    protected function makeIdenticalScreen(): Screen
    {
        $terminal = new Terminal;
        $terminal->initDimensions();

        return new Screen($terminal->cols(), $terminal->lines());
    }
}
