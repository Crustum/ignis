<?php
declare(strict_types=1);

namespace Crustum\Ignis\Trait;

use Cake\Console\Arguments;
use Cake\Console\ConsoleIo;
use Cake\Console\ConsoleOptionParser;
use Cake\Core\Configure;
use Cake\Event\EventInterface;
use Closure;
use Crustum\Prompts\Cake\ConsoleIoFallbacks;
use Crustum\Prompts\Console\Helper\ConfirmHelper;
use Crustum\Prompts\Console\Helper\GridHelper;
use Crustum\Prompts\Console\Helper\MultiSelectHelper;
use Crustum\Prompts\Console\Helper\NoteHelper;
use Crustum\Prompts\Console\Helper\OutroHelper;
use Crustum\Prompts\Console\Helper\SpinHelper;
use Crustum\Prompts\Console\Helper\TableHelper;
use Crustum\Prompts\Console\Helper\TextHelper;
use Laravel\Prompts\Prompt;

/**
 * Interactive console prompts and shared Ignis command options.
 *
 * Delegates to `crustum/prompts` helpers. On Windows and non-interactive IO,
 * Prompts uses ConsoleIo fallbacks so commands keep working without a TTY.
 * MultiSelect glyph theme in IgnisPlugin for WSL-safe circles).
 */
trait ConsolePromptTrait
{
    /**
     * Apply shared Ignis console options to the command parser.
     *
     * @param \Cake\Console\ConsoleOptionParser $parser Option parser
     * @return \Cake\Console\ConsoleOptionParser
     */
    protected function configureIgnisOptionParser(ConsoleOptionParser $parser): ConsoleOptionParser
    {
        $parser->addOption('no-interaction', [
            'short' => 'n',
            'help' => 'Do not ask any interactive questions.',
            'boolean' => true,
        ]);

        return $parser;
    }

    /**
     * Bind ConsoleIo fallbacks and disable TTY prompts when non-interactive.
     *
     * `setInteractive(false)` must run before `setIo()` so Prompts enables
     * Cake fallbacks. `IGNIS_SUPPRESS_DISPLAY` (PHPUnit) also forces fallbacks
     * so Note/Table/Grid/Spinner write to Cake's captured stdout instead of the
     * real terminal (`25l`/`25h` spinner sequences).
     *
     * @param \Cake\Event\EventInterface $event Command event
     * @param \Cake\Console\Arguments $args Command arguments
     * @param \Cake\Console\ConsoleIo $io Console IO
     * @return void
     */
    public function beforeExecute(EventInterface $event, Arguments $args, ConsoleIo $io): void
    {
        unset($event);

        if ($this->isNonInteractive($args) || $this->shouldSuppressDisplay()) {
            $io->setInteractive(false);
        }

        ConsoleIoFallbacks::setIo($io);

        if ($this->shouldSuppressDisplay()) {
            Prompt::fallbackWhen(true);
        }
    }

    /**
     * Determine whether the command should prompt interactively.
     *
     * @param \Cake\Console\Arguments $args Command arguments
     * @param \Cake\Console\ConsoleIo $io Console IO
     * @return bool
     */
    protected function isInteractive(Arguments $args, ConsoleIo $io): bool
    {
        unset($io);

        return !$this->isNonInteractive($args);
    }

    /**
     * Determine whether non-interactive mode is requested.
     *
     * @param \Cake\Console\Arguments $args Command arguments
     * @return bool
     */
    protected function isNonInteractive(Arguments $args): bool
    {
        if ($args->getBooleanOption('quiet') === true) {
            return true;
        }

        return $args->getBooleanOption('no-interaction') === true;
    }

    /**
     * Prompt for one or more selections from a keyed option list.
     *
     * @param \Cake\Console\ConsoleIo $io Console IO
     * @param string $label Prompt label
     * @param array<string, string> $options Keyed options
     * @param array<int, string> $defaults Default selected keys
     * @param bool $required Whether at least one selection is required
     * @param string|null $hint Optional hint text
     * @return array<int, string> Selected keys
     */
    protected function promptMultiselect(
        ConsoleIo $io,
        string $label,
        array $options,
        array $defaults = [],
        bool $required = true,
        ?string $hint = null,
    ): array {
        if ($options === []) {
            return [];
        }

        $selected = (new MultiSelectHelper($io))->run([
            'label' => $label,
            'options' => $options,
            'default' => $defaults,
            'required' => $required,
            'hint' => $hint ?? '',
        ]);

        if (!is_array($selected)) {
            return [];
        }

        /** @var array<int, string> $keys */
        $keys = array_values(array_map(static fn(mixed $value): string => (string)$value, $selected));

        return $keys;
    }

    /**
     * Prompt for yes/no confirmation.
     *
     * @param \Cake\Console\ConsoleIo $io Console IO
     * @param string $label Prompt label
     * @param bool $default Default answer
     * @return bool
     */
    protected function promptConfirm(ConsoleIo $io, string $label, bool $default = false): bool
    {
        return (new ConfirmHelper($io))->run([
            'label' => $label,
            'default' => $default,
        ]);
    }

    /**
     * Prompt for required text input with optional validation.
     *
     * @param \Cake\Console\ConsoleIo $io Console IO
     * @param string $label Prompt label
     * @param string|null $placeholder Placeholder hint
     * @param callable(string): (string|null)|null $validator Validation callback returning error message
     * @return string
     */
    protected function promptText(
        ConsoleIo $io,
        string $label,
        ?string $placeholder = null,
        ?callable $validator = null,
    ): string {
        return (new TextHelper($io))->run([
            'label' => $label,
            'placeholder' => $placeholder ?? '',
            'required' => true,
            'validate' => $validator,
            'hint' => $placeholder ?? '',
        ]);
    }

    /**
     * Run a callback with a spinner (ConsoleIo message fallback on Windows / non-TTY).
     *
     * @template TReturn
     * @param \Cake\Console\ConsoleIo $io Console IO
     * @param \Closure(): TReturn $callback Work to run while spinning
     * @param string $message Spinner / fallback message
     * @return TReturn
     */
    protected function promptSpin(ConsoleIo $io, Closure $callback, string $message = ''): mixed
    {
        return (new SpinHelper($io))->run([
            'callback' => $callback,
            'message' => $message,
        ]);
    }

    /**
     * Render items in a multi-column grid.
     *
     * @param \Cake\Console\ConsoleIo $io Console IO
     * @param array<int, string> $items Items to display
     * @param int $columns Number of columns (unused; Prompts Grid sizes itself)
     * @return void
     */
    protected function displayGrid(ConsoleIo $io, array $items, int $columns = 3): void
    {
        unset($columns);

        if ($items === []) {
            return;
        }

        (new GridHelper($io))->run([
            'items' => array_values($items),
        ]);
    }

    /**
     * Render a table with headers and rows.
     *
     * @param \Cake\Console\ConsoleIo $io Console IO
     * @param array<int, string> $headers Table headers
     * @param array<int, array<int, string>> $rows Table rows
     * @return void
     */
    protected function displayTable(ConsoleIo $io, array $headers, array $rows): void
    {
        if ($headers === []) {
            return;
        }

        (new TableHelper($io))->run([
            'headers' => $headers,
            'rows' => $rows,
        ]);
    }

    /**
     * Render a styled note via Prompts.
     *
     * @param \Cake\Console\ConsoleIo $io Console IO
     * @param string $message Note body
     * @param string|null $type Optional note type
     * @return void
     */
    protected function displayNote(ConsoleIo $io, string $message, ?string $type = null): void
    {
        $args = ['message' => $message];
        if ($type !== null) {
            $args['type'] = $type;
        }

        (new NoteHelper($io))->run($args);
    }

    /**
     * Render the Ignis command header (plain text — no Theme / ASCII logo).
     *
     * @param \Cake\Console\ConsoleIo $io Console IO
     * @param string $featureName Feature label
     * @param string $projectName Project label
     * @return void
     */
    protected function displayIgnisHeader(ConsoleIo $io, string $featureName, string $projectName): void
    {
        if ($this->shouldSuppressDisplay()) {
            return;
        }

        $io->out('');
        $io->out("CakePHP Ignis :: {$featureName}");
        $this->displayNote($io, "Let's give {$projectName} an Ignis");
        $io->out('');
    }

    /**
     * Render the command outro via Prompts Outro helper.
     *
     * @param \Cake\Console\ConsoleIo $io Console IO
     * @param string $text Outro text
     * @return void
     */
    protected function displayOutro(ConsoleIo $io, string $text): void
    {
        if ($this->shouldSuppressDisplay()) {
            return;
        }

        (new OutroHelper($io))->run([
            'message' => trim($text),
        ]);
    }

    /**
     * Determine whether Ignis banners should be skipped.
     *
     * @return bool
     */
    protected function shouldSuppressDisplay(): bool
    {
        $configured = Configure::read('Ignis.suppress_display');

        if (in_array($configured, [true, '1', 1], true)) {
            return true;
        }

        $envValue = env('IGNIS_SUPPRESS_DISPLAY', false);

        return in_array($envValue, [true, '1', 1], true);
    }
}
