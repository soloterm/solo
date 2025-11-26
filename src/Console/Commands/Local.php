<?php

/**
 * @author Aaron Francis <aaron@tryhardstudios.com>
 *
 * @link https://aaronfrancis.com
 * @link https://x.com/aarondfrancis
 */

namespace SoloTerm\Solo\Console\Commands;

use Illuminate\Console\Command;

class Local extends Command
{
    protected $signature = 'solo:local
                            {--revert : Revert to using the published package}
                            {--path=../screen : Path to the local screen package}';

    protected $description = 'Link to a local copy of the Screen package for development.';

    public function handle()
    {
        $composerPath = $this->getComposerPath();

        if (!file_exists($composerPath)) {
            $this->error("Could not find composer.json at: {$composerPath}");
            return 1;
        }

        $composer = json_decode(file_get_contents($composerPath), true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            $this->error('Failed to parse composer.json: ' . json_last_error_msg());
            return 1;
        }

        if ($this->option('revert')) {
            return $this->revert($composer, $composerPath);
        }

        return $this->link($composer, $composerPath);
    }

    protected function link(array $composer, string $composerPath): int
    {
        $path = $this->option('path');

        // Check if the local path exists
        $absolutePath = realpath(dirname($composerPath) . '/' . $path);
        if (!$absolutePath || !is_dir($absolutePath)) {
            $this->error("Local screen package not found at: {$path}");
            $this->line("Make sure the screen package exists at the specified path relative to composer.json");
            return 1;
        }

        // Add repositories section if it doesn't exist
        if (!isset($composer['repositories'])) {
            $composer['repositories'] = [];
        }

        // Check if path repository already exists
        $hasPathRepo = false;
        foreach ($composer['repositories'] as $repo) {
            if (isset($repo['type']) && $repo['type'] === 'path' && isset($repo['url']) && $repo['url'] === $path) {
                $hasPathRepo = true;
                break;
            }
        }

        if (!$hasPathRepo) {
            // Add path repository at the beginning
            array_unshift($composer['repositories'], [
                'type' => 'path',
                'url' => $path,
                'options' => [
                    'symlink' => true,
                ],
            ]);
        }

        // Update screen dependency to @dev
        if (isset($composer['require']['soloterm/screen'])) {
            $composer['require']['soloterm/screen'] = '@dev';
        }

        // Write back
        if (!$this->writeComposer($composer, $composerPath)) {
            return 1;
        }

        $this->info('Linked to local screen package at: ' . $path);
        $this->line('');
        $this->line('Run <comment>composer update soloterm/screen</comment> to apply changes.');
        $this->line('');
        $this->line('To revert: <comment>php artisan solo:local --revert</comment>');

        return 0;
    }

    protected function revert(array $composer, string $composerPath): int
    {
        // Remove path repositories for screen
        if (isset($composer['repositories'])) {
            $composer['repositories'] = array_values(array_filter($composer['repositories'], function ($repo) {
                // Keep repos that aren't path repos pointing to screen
                if (!isset($repo['type']) || $repo['type'] !== 'path') {
                    return true;
                }
                // Remove if it looks like a screen path
                return !isset($repo['url']) || !str_contains($repo['url'], 'screen');
            }));

            // Remove empty repositories array
            if (empty($composer['repositories'])) {
                unset($composer['repositories']);
            }
        }

        // Restore screen dependency to ^1
        if (isset($composer['require']['soloterm/screen'])) {
            $composer['require']['soloterm/screen'] = '^1';
        }

        // Write back
        if (!$this->writeComposer($composer, $composerPath)) {
            return 1;
        }

        $this->info('Reverted to published screen package.');
        $this->line('');
        $this->line('Run <comment>composer update soloterm/screen</comment> to apply changes.');

        return 0;
    }

    protected function writeComposer(array $composer, string $composerPath): bool
    {
        // Reorder keys to maintain standard composer.json order
        $ordered = $this->reorderComposerKeys($composer);

        $json = json_encode($ordered, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

        if ($json === false) {
            $this->error('Failed to encode composer.json: ' . json_last_error_msg());
            return false;
        }

        // Add trailing newline
        $json .= "\n";

        if (file_put_contents($composerPath, $json) === false) {
            $this->error('Failed to write composer.json');
            return false;
        }

        return true;
    }

    protected function reorderComposerKeys(array $composer): array
    {
        $order = [
            'name',
            'description',
            'type',
            'license',
            'authors',
            'minimum-stability',
            'repositories',
            'require',
            'require-dev',
            'autoload',
            'autoload-dev',
            'extra',
            'scripts',
            'config',
        ];

        $ordered = [];

        foreach ($order as $key) {
            if (isset($composer[$key])) {
                $ordered[$key] = $composer[$key];
            }
        }

        // Add any remaining keys not in our order list
        foreach ($composer as $key => $value) {
            if (!isset($ordered[$key])) {
                $ordered[$key] = $value;
            }
        }

        return $ordered;
    }

    protected function getComposerPath(): string
    {
        // Look for composer.json in the package directory
        return dirname(__DIR__, 3) . '/composer.json';
    }
}
