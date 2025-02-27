<?php

/**
 * @author Aaron Francis <aaron@tryhardstudios.com>
 *
 * @link https://aaronfrancis.com
 * @link https://x.com/aarondfrancis
 */

namespace SoloTerm\Solo\Console\Commands;

use Illuminate\Console\Command;
use Laravel\Prompts\Concerns\Colors;

class About extends Command
{
    use Colors;

    protected $signature = 'solo:about';

    protected $description = 'Display information about Solo.';

    public function handle()
    {
        $banner = <<<EOT
┃┃┃┃┃╰━━━━━━━╯┃┃┃┃┃   ███████╗ ██████╗ ██╗      ██████╗ 
┃┃┃┃╰━━━━━━━━━╯┃┃┃┃   ██╔════╝██╔═══██╗██║     ██╔═══██╗
┃┃┃╰━━━━━━━━━━━╯┃┃┃   ███████╗██║   ██║██║     ██║   ██║
┃┃╰━━━━━━━━━━━━━╯┃┃   ╚════██║██║   ██║██║     ██║   ██║
┃╰━━━━━━━━━━━━━━━╯┃   ███████║╚██████╔╝███████╗╚██████╔╝
╰━━━━━━━━━━━━━━━━━╯   ╚══════╝ ╚═════╝ ╚══════╝ ╚═════╝ 
EOT;

        $banner = preg_replace_callback('/[║╔╗╚═╝]/u', fn($matches) => $this->dim($this->blue($matches[0])), $banner);
        $banner = preg_replace_callback('/█/u', fn($matches) => $this->blue($matches[0]), $banner);
        $banner = preg_replace_callback('/[┃╰━━╯]/u', fn($matches) => $this->blue($matches[0]), $banner);

        echo "$banner\n";

        $message = <<<EOT
Solo for Laravel is a package to run multiple commands at once, to aid in local development. After installing, you can open the SoloServiceProvider to add or remove commands.

You can have all the commands needed to run your application behind a single command: 

> php artisan solo

Each command runs in its own tab in Solo. Use the left/right arrow keys to navigate between them. (See the hotkeys at the bottom of the screen.)

Solo was developed by Aaron Francis. If you like it, please let me know!
 
• Twitter: https://twitter.com/aarondfrancis
• Website: https://aaronfrancis.com
• YouTube: https://youtube.com/@aarondfrancis
• GitHub: https://github.com/aarondfrancis/solo

 
EOT;

        echo wordwrap($message);
    }
}
