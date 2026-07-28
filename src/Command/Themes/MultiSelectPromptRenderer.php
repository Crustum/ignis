<?php
declare(strict_types=1);

namespace Crustum\Ignis\Command\Themes;

use Laravel\Prompts\MultiSelectPrompt;
use Laravel\Prompts\Themes\Default\MultiSelectPromptRenderer as LaravelMultiSelectPromptRenderer;
use Override;

/**
 * MultiSelect renderer that uses circle markers instead of medium squares.
 */
class MultiSelectPromptRenderer extends LaravelMultiSelectPromptRenderer
{
    /**
     * Render the options with circle selected markers.
     *
     * @param \Laravel\Prompts\MultiSelectPrompt $prompt Prompt instance
     * @return string
     */
    #[Override]
    protected function renderOptions(MultiSelectPrompt $prompt): string
    {
        return implode(PHP_EOL, $this->scrollbar(
            array_map(function ($label, $key) use ($prompt): string {
                $label = $this->truncate($label, $prompt->terminal()->cols() - 12);

                $index = array_search($key, array_keys($prompt->options), true);
                $active = $index === $prompt->highlighted;
                $value = array_is_list($prompt->options) ? $prompt->options[$index] : array_keys($prompt->options)[$index];

                $selected = in_array($value, $prompt->value(), true);

                if ($prompt->state === 'cancel') {
                    return $this->dim(match (true) {
                        $active && $selected => "› ● {$this->strikethrough($label)}  ",
                        $active => "› ○ {$this->strikethrough($label)}  ",
                        $selected => "  ● {$this->strikethrough($label)}  ",
                        default => "  ○ {$this->strikethrough($label)}  ",
                    });
                }

                return match (true) {
                    $active && $selected => "{$this->cyan('› ●')} {$label}  ",
                    $active => "{$this->cyan('›')} ○ {$label}  ",
                    $selected => "  {$this->cyan('●')} {$this->dim($label)}  ",
                    default => "  {$this->dim('○')} {$this->dim($label)}  ",
                };
            }, $visible = $prompt->visible(), array_keys($visible)),
            $prompt->firstVisible,
            $prompt->scroll,
            count($prompt->options),
            min($this->longest($prompt->options, padding: 6), $prompt->terminal()->cols() - 6),
            $prompt->state === 'cancel' ? 'dim' : 'cyan',
        ));
    }
}
