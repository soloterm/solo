<?php

/**
 * @author Aaron Francis <aaron@tryhardstudios.com>
 *
 * @link https://aaronfrancis.com
 * @link https://x.com/aarondfrancis
 */

namespace SoloTerm\Solo\Support;

use Closure;
use Laravel\Prompts\SearchPrompt;
use Laravel\Prompts\Themes\Default\SearchPromptRenderer;

class QuickPickPrompt extends SearchPrompt
{
    public function __construct(
        string $label,
        Closure $options,
        string $placeholder = '',
        int $scroll = 5,
        mixed $validate = null,
        string $hint = '',
        bool|string $required = true
    ) {
        parent::__construct($label, $options, $placeholder, $scroll, $validate, $hint, $required);

        if (count($this->matches())) {
            $this->highlighted = 0;
        }
    }

    protected function search(): void
    {
        parent::search();

        if (count($this->matches())) {
            $this->highlighted = 0;
        }
    }

    protected function getRenderer(): callable
    {
        return new SearchPromptRenderer($this);
    }
}
