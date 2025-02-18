<?php

namespace SoloTerm\Solo\Support;

use Laravel\Prompts\Concerns\Colors;

/**
 * Renders a box with a title and message (default color is red).
 */
class ErrorBox
{
    use Colors;

    public function __construct(
        public string|array $message,
        public ?string $title = null,
        public ?string $color = null,
    ) {
        $this->message = is_string($this->message) ? [$this->message] : $this->message;
        $this->title ??= 'Error';
        $this->color ??= 'red';
    }

    public function render(): string
    {
        $newLine = PHP_EOL;
        $sidePadding = 2;
        $color = $this->color;

        // Gather all lines (title + messages) so we can measure the maximum length.
        $allLines = array_merge([$this->title], $this->message);
        $maxLength = max(array_map('mb_strlen', $allLines));
        $contentWidth = $maxLength + $sidePadding;

        // Build the top border with the title.
        // We start with a space, then the title, then another space.
        $usedLength = mb_strlen($this->title) + $sidePadding;
        $remaining = max(0, $contentWidth - $usedLength);
        $topBorder = $this->$color(
            $this->boxPiece('topLeft')
            . ' ' . $this->title . ' '
            . str_repeat($this->boxPiece('horizontal'), $remaining)
            . $this->boxPiece('topRight')
        );

        // Build the bottom border (full horizontal line).
        $bottomBorder =
            $this->$color(
                $this->boxPiece('bottomLeft')
                . str_repeat($this->boxPiece('horizontal'), $contentWidth)
                . $this->boxPiece('bottomRight')
            );

        // Build message lines with left padding and right-padding via str_pad.
        $messageLines = [];
        foreach ($this->message as $line) {
            $lineContent = ' ' . $line;
            $paddedContent = str_pad($lineContent, $contentWidth);
            $messageLines[] = $this->$color(
                $this->boxPiece('vertical')
                . $paddedContent
                . $this->boxPiece('vertical')
            );
        }

        return implode($newLine, [$topBorder, ...$messageLines, $bottomBorder]) . $newLine;
    }

    protected function boxPiece(string $position)
    {
        return match ($position) {
            'topLeft' => '╔',
            'topRight' => '╗',
            'vertical' => '║',
            'horizontal' => '═',
            'bottomLeft' => '╚',
            'bottomRight' => '╝',
        };
    }
}
