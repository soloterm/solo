<?php

/**
 * @author Aaron Francis <aaron@tryhardstudios.com>
 *
 * @link https://aaronfrancis.com
 * @link https://x.com/aarondfrancis
 */

namespace SoloTerm\Solo;

use Exception;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Config;
use InvalidArgumentException;
use Laravel\Prompts\Themes\Default\Renderer as PromptsRenderer;
use ReflectionClass;
use SoloTerm\Solo\Commands\Command;
use SoloTerm\Solo\Concerns\HasEvents;
use SoloTerm\Solo\Contracts\HotkeyProvider;
use SoloTerm\Solo\Contracts\Theme;
use SoloTerm\Solo\Hotkeys\DefaultHotkeys;
use SoloTerm\Solo\Hotkeys\Hotkey;
use SoloTerm\Solo\Prompt\Renderer;
use SoloTerm\Solo\Themes\LightTheme;

class Manager
{
    use HasEvents;

    /**
     * @var array<Command>
     */
    protected array $commands = [];

    protected ?Theme $cachedTheme = null;

    protected string $renderer = Renderer::class;

    public function __construct()
    {
        $this->loadCommands();
    }

    /**
     * @return array<Command>
     */
    public function commands(): array
    {
        return $this->commands;
    }

    public function addCommand(string|Command $command, ?string $name = null): static
    {
        if (is_string($command) && is_a($command, Command::class, allow_string: true)) {
            $command = app($command);
        }

        if (is_string($command)) {
            if (is_null($name)) {
                throw new InvalidArgumentException('Name must be provided when command is a string.');
            }

            $command = new Command(name: $name, command: $command);
        }

        if (!is_null($name)) {
            $command->name = $name;
        }

        if (!$command instanceof Command) {
            throw new InvalidArgumentException(
                '[' . get_class($command) . '] must be an instance of [' . Command::class . '].'
            );
        }

        $this->commands[] = $command;

        return $this;
    }

    public function clearCommands(): static
    {
        $this->commands = [];

        return $this;
    }

    public function loadCommands(): static
    {
        $this->addCommands(config('solo.commands'));

        return $this;
    }

    public function addCommands(array $commands): static
    {
        foreach ($commands as $name => $command) {
            $this->addCommand($command, $name);
        }

        return $this;
    }

    /**
     * @return array<Hotkey>
     *
     * @throws Exception
     */
    public function hotkeys(): array
    {
        $bindings = $this->getConfigValue('solo.keybindings', []);
        $binding = $this->getConfigValue('solo.keybinding', 'default');

        $hotkeys = Arr::get($bindings, $binding, DefaultHotkeys::class);

        if (!is_a($hotkeys, HotkeyProvider::class, allow_string: true)) {
            throw new InvalidArgumentException('Hotkeys must implement [' . HotkeyProvider::class . ']');
        }

        return $hotkeys::keys();
    }

    public function theme(bool $reinitialize = false): Theme
    {
        if ($this->cachedTheme && !$reinitialize) {
            return $this->cachedTheme;
        }

        $theme = $this->getConfigValue('solo.theme', 'light');
        $themes = $this->getConfigValue('solo.themes', [
            'light' => LightTheme::class,
        ]);

        $theme = Arr::get($themes, $theme, $theme);

        if (!$theme || !class_exists($theme)) {
            throw new InvalidArgumentException("Theme class '{$theme}' does not exist.");
        }

        $reflected = new ReflectionClass($theme);

        if (!$reflected->implementsInterface(Theme::class)) {
            throw new InvalidArgumentException("Theme class '{$theme}' must implement the SoloTheme interface.");
        }

        if ($reflected->isAbstract()) {
            throw new InvalidArgumentException("Theme class '{$theme}' is not instantiable.");
        }

        return $this->cachedTheme = new $theme;
    }

    public function setRenderer($renderer)
    {
        if (!is_subclass_of($renderer, PromptsRenderer::class)) {
            throw new InvalidArgumentException(
                "[$renderer] must be a subclass of [" . PromptsRenderer::class . ']'
            );
        }

        $this->renderer = $renderer;
    }

    public function getRenderer(): string
    {
        return $this->renderer;
    }

    private function getConfigValue(string $key, $default = null)
    {
        $value = Config::get($key, $default);

        if (gettype($value) !== gettype($default)) {
            throw new InvalidArgumentException(
                sprintf('Configuration value for key [%s] must be a string, %s given.', $key, gettype($value))
            );
        }

        return $value;
    }
}
