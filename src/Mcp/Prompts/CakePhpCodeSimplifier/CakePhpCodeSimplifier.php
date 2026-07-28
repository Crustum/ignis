<?php
declare(strict_types=1);

namespace Crustum\Ignis\Mcp\Prompts\CakePhpCodeSimplifier;

use Crustum\Ignis\Mcp\Prompts\AbstractIgnisPrompt;
use Crustum\Mcp\Response;

/**
 * MCP prompt for refining recently modified CakePHP code.
 */
class CakePhpCodeSimplifier extends AbstractIgnisPrompt
{
    /**
     * Prompt name.
     *
     * @var string
     */
    protected string $name = 'cakephp-code-simplifier';

    /**
     * Prompt title.
     *
     * @var string
     */
    protected string $title = 'cakephp_code_simplifier';

    /**
     * Prompt description.
     *
     * @var string
     */
    protected string $description = 'Simplifies and refines PHP/CakePHP code for clarity, consistency, and maintainability while preserving all functionality. Focuses on recently modified code unless instructed otherwise.';

    /**
     * Handle the prompt request.
     *
     * @return \Crustum\Mcp\Response Prompt content
     */
    public function handle(): Response
    {
        $content = $this->renderTwigFile(__DIR__ . DS . 'cakephp-code-simplifier.twig');

        return Response::text($content);
    }
}
