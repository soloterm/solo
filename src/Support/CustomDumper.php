<?php

/**
 * @author Aaron Francis <aaron@tryhardstudios.com>
 *
 * @link https://aaronfrancis.com
 * @link https://x.com/aarondfrancis
 */

namespace SoloTerm\Solo\Support;

use Illuminate\Foundation\Console\CliDumper;
use Illuminate\Foundation\Console\CliDumper as LaravelCliDumper;
use Symfony\Component\Console\Output\StreamOutput;
use Symfony\Component\VarDumper\Caster\ReflectionCaster;
use Symfony\Component\VarDumper\Cloner\Data;
use Symfony\Component\VarDumper\Cloner\VarCloner;
use Symfony\Component\VarDumper\Dumper\DataDumperInterface;
use Symfony\Component\VarDumper\Dumper\ServerDumper;
use Symfony\Component\VarDumper\VarDumper;
use Throwable;

readonly class CustomDumper
{
    public static function register($basePath, $compiledViewPath): static
    {
        return new static($basePath, $compiledViewPath);
    }

    public static function dumpServerHost(): string
    {
        return config()->get('solo.dump_server_host', 'tcp://127.0.0.1:9984');
    }

    public function __construct(public string $basePath, public string $compiledViewPath)
    {
        $cloner = new VarCloner;
        $cloner->addCasters(ReflectionCaster::UNSET_CLOSURE_FILE_INFO);

        $original = VarDumper::setHandler(null);

        // We only use this dumper to get the dump source and add it to the context.
        $fake = $this->makeSourceResolvingDumper();

        $server = new ServerDumper(static::dumpServerHost(), $this->makeFallbackDumper());

        VarDumper::setHandler(function (mixed $var) use ($cloner, $server, $fake, $original) {
            $data = $cloner->cloneVar($var)->withContext([
                'dumpSource' => $fake->resolveDumpSource()
            ]);

            try {
                $response = $server->dump($data);
            } catch (Throwable $e) {
                $response = 'server_dump_failed';
            }

            if ($response === 'server_dump_failed') {
                if (is_callable($original)) {
                    $original($var);
                } else {
                    print_r($var);
                }
            }
        });
    }

    protected function makeSourceResolvingDumper(): CliDumper
    {
        $output = new StreamOutput(fopen('php://memory', 'w'));

        return new LaravelCliDumper($output, $this->basePath, $this->compiledViewPath);
    }

    protected function makeFallbackDumper(): DataDumperInterface
    {
        return new class implements DataDumperInterface
        {
            public function dump(Data $data): string
            {
                return 'server_dump_failed';
            }
        };
    }
}
