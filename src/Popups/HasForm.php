<?php

/**
 * @author Aaron Francis <aaron@tryhardstudios.com>
 *
 * @link https://aaronfrancis.com
 * @link https://x.com/aarondfrancis
 */

namespace SoloTerm\Solo\Popups;

use Generator;
use SoloTerm\Solo\Support\CapturedPromptInterface;

trait HasForm
{
    protected Generator $form;

    public function bootHasForm()
    {
        $this->form = $this->form();
    }

    abstract public function form(): Generator;

    public function renderForm()
    {
        $step = $this->form->current();

        if ($step instanceof CapturedPromptInterface) {
            $step->setScreen($this->screen);

            if ($step->isComplete()) {
                $this->form->next();

                return;
            }

            $step->renderSingleFrame();
        } elseif (is_string($step)) {
            $this->screen->writeln($step);
            $this->form->next();
        }
    }

    public function renderSingleFrame()
    {
        $this->renderForm();
    }

    public function handleFormInput($key)
    {
        if ($this->form->current() instanceof CapturedPromptInterface) {
            $this->form->current()->handleInput($key);
        }
    }
}
