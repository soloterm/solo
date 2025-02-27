<?php

/**
 * @author Aaron Francis <aaron@tryhardstudios.com>
 *
 * @link https://aaronfrancis.com
 * @link https://x.com/aarondfrancis
 */

namespace SoloTerm\Solo\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Process\InvokedProcess;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Str;
use Laravel\Prompts\Concerns\Colors;
use Laravel\Prompts\Prompt;
use Laravel\Prompts\Terminal;
use SoloTerm\Solo\Support\PendingProcess;
use Symfony\Component\Process\InputStream;
use Throwable;

use function Laravel\Prompts\search;

class Make extends Command
{
    use Colors;

    protected $signature = 'solo:make';

    protected $description = 'A global, interactive entrypoint for Laravel\'s various `make:` commands.';

    protected InputStream $pty;

    protected Terminal $terminal;

    protected InvokedProcess $process;

    public function handle(): void
    {
        $this->pty = new InputStream;

        try {
            $this->loop();
        } catch (Throwable $e) {
            $this->error($e->getMessage());
        }

        if (!$this->pty->isClosed()) {
            $this->pty->close();
        }
    }

    public function loop(): void
    {
        $types = $this->getAvailableTypes();

        $type = search(
            label: 'What would you like to make?',
            options: fn(string $value) => $types
                ->filter(fn($option) => Str::contains($option, $value, ignoreCase: true))
                ->all(),
            placeholder: 'E.g. Model',
            hint: 'Begin typing to search.' . $this->dim(' (' . $types->take(3)->push('etc')->implode(', ') . ')')
        );

        $this->process = app(PendingProcess::class)
            ->command("php artisan make:$type")
            ->input($this->pty)
            ->pty()
            ->forever()
            ->env([
                'FORCE_COLOR' => '1',
                'COLUMNS' => getenv('COLUMNS'),
                'LINES' => getenv('LINES'),
            ])
            ->start(null, function ($type, $buffer) {
                echo $buffer;
            });

        $this->line('  ' . $this->bgBlue($this->white(' INFO ')) . " Starting `php artisan make:$type`");

        $this->proxyInput();

        $this->line('  ' . $this->bgBlue($this->white(' INFO ')) . " Finished `php artisan make:$type`");

        $this->line('');

        $this->line(
            '  '
            . $this->bgBlue($this->white(' MAKE '))
            . ' Make another class. '
            . $this->dim('(⌃c to quit)')
        );

        $this->loop();
    }

    protected function proxyInput(): void
    {
        Prompt::terminal()->setTty('-icanon -isig -echo');

        while ($this->process->running()) {
            $read = [STDIN];
            $write = null;
            $except = null;

            if (stream_select($read, $write, $except, 0, 5_000) === 1) {
                $key = fread(STDIN, 1024);
                $this->pty->write($key);
            }
        }

        Prompt::terminal()->restoreTty();
    }

    protected function getAvailableTypes(): Collection
    {
        $output = Process::command('php artisan')->run()->output();

        // Get all the options out of the output.
        preg_match_all('/make:([a-zA-Z0-9_-]+)/', $output, $matches);

        // Take all the captures and add a friendly display
        // as the value, keeping the id as the key.
        return collect($matches[1])->mapWithKeys(fn($name) => [$name => Str::title($name)]);
    }
}
