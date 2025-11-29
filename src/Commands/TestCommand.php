<?php

/**
 * @author Aaron Francis <aaron@tryhardstudios.com>
 *
 * @link https://aaronfrancis.com
 * @link https://x.com/aarondfrancis
 */

namespace SoloTerm\Solo\Commands;

class TestCommand extends Command
{
    /**
     * Patterns that identify test commands which should use TestCommand
     * to ensure APP_ENV=testing is set properly.
     */
    protected static array $testCommandPatterns = [
        '/\bartisan\s+test\b/',
        '/\bpest\b/',
        '/\bphpunit\b/',
    ];

    public function __construct(
        ?string $name = null,
        ?string $command = null,
        bool $autostart = true,
    ) {
        parent::__construct($name, $command, $autostart);

        // Automatically set APP_ENV to testing to ensure tests run
        // in the correct environment and don't affect local data.
        // @see https://github.com/soloterm/solo/issues/97
        $this->environment['APP_ENV'] = 'testing';
    }

    /**
     * Check if a command string looks like a test command.
     */
    public static function looksLikeTestCommand(string $command): bool
    {
        foreach (static::$testCommandPatterns as $pattern) {
            if (preg_match($pattern, $command)) {
                return true;
            }
        }

        return false;
    }

    public static function artisan(string $args = '--colors=always'): static
    {
        return static::make('Tests', "php artisan test {$args}")->lazy();
    }

    public static function pest(string $args = '--colors=always'): static
    {
        return static::make('Tests', "./vendor/bin/pest {$args}")->lazy();
    }

    public static function phpunit(string $args = '--colors=always'): static
    {
        return static::make('Tests', "./vendor/bin/phpunit {$args}")->lazy();
    }
}
