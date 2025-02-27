<?php

/**
 * @author Aaron Francis <aaron@tryhardstudios.com>
 *
 * @link https://aaronfrancis.com
 * @link https://x.com/aarondfrancis
 */

namespace SoloTerm\Solo\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Foundation\Console\CliDumper;
use Illuminate\Support\Arr;
use SoloTerm\Solo\Support\CustomDumper;
use Symfony\Component\VarDumper\Cloner\Data;
use Symfony\Component\VarDumper\Server\DumpServer;

class Dumps extends Command
{
    protected $signature = 'solo:dumps';

    protected $description = 'Collect dumps from your Laravel application.';

    public function handle()
    {
        $dumper = new CliDumper(
            output: $this->getOutput()->getOutput(),
            basePath: base_path(),
            compiledViewPath: config('view.compiled')
        );

        $server = new DumpServer(CustomDumper::dumpServerHost());
        $server->start();

        $this->info('Listening for dumps on ' . CustomDumper::dumpServerHost());

        $server->listen(function (Data $data) use ($dumper) {
            // We added the dump source on the sending side. If we tried to deduce
            // it here it would only point to this command, not the originator.
            // Here we set the resolver to just grab it from our context.
            CliDumper::resolveDumpSourceUsing(function () use ($data) {
                return Arr::get($data->getContext(), 'dumpSource');
            });

            $dumper->dumpWithSource($data);
        });
    }
}
