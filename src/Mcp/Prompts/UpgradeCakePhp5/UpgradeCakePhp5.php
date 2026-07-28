<?php
declare(strict_types=1);

namespace Crustum\Ignis\Mcp\Prompts\UpgradeCakePhp5;

use Crustum\Ignis\Mcp\Prompts\AbstractIgnisPrompt;
use Crustum\Ignis\Support\PackageRegistry;
use Crustum\Inspector\ProjectManager;
use Crustum\Mcp\Response;

/**
 * MCP prompt for upgrading applications from CakePHP 4.x to 5.x.
 */
class UpgradeCakePhp5 extends AbstractIgnisPrompt
{
    /**
     * Prompt name.
     *
     * @var string
     */
    protected string $name = 'upgrade-cakephp-5';

    /**
     * Prompt title.
     *
     * @var string
     */
    protected string $title = 'upgrade_cakephp_5';

    /**
     * Prompt description.
     *
     * @var string
     */
    protected string $description = 'Provides step-by-step guidance for upgrading from CakePHP 4.x to 5.x.';

    /**
     * Register only when the project is on CakePHP 4.x.
     *
     * @param \Crustum\Inspector\ProjectManager $project Project manager
     * @return bool
     */
    public function shouldRegister(ProjectManager $project): bool
    {
        return $project->php()->uses(PackageRegistry::CAKEPHP, '>=4.0.0 <5.0.0');
    }

    /**
     * Handle the prompt request.
     *
     * @return \Crustum\Mcp\Response Prompt content
     */
    public function handle(): Response
    {
        $content = $this->renderTwigFile(__DIR__ . DS . 'upgrade-cakephp-5.twig');

        return Response::text($content);
    }
}
