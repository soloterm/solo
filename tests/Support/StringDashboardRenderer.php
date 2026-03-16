<?php

namespace SoloTerm\Solo\Tests\Support;

use SoloTerm\Solo\Prompt\Dashboard;
use SoloTerm\Solo\Prompt\Renderer;

class StringDashboardRenderer extends Renderer
{
    public function __invoke(Dashboard $dashboard): string
    {
        return (string) parent::__invoke($dashboard);
    }
}
