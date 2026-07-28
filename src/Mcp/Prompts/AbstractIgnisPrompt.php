<?php
declare(strict_types=1);

namespace Crustum\Ignis\Mcp\Prompts;

use Crustum\Ignis\Install\GuidelineAssist;
use Crustum\Ignis\Install\GuidelineConfig;
use Crustum\Ignis\Support\ProjectRoot;
use Crustum\Ignis\Trait\RendersTwigGuidelinesTrait;
use Crustum\Inspector\ProjectManager;
use Crustum\Mcp\Server\Prompt;
use Crustum\Mcp\Support\ContainerRegistry;

/**
 * Base MCP prompt with Twig guideline rendering and container-aware assist.
 */
abstract class AbstractIgnisPrompt extends Prompt
{
    use RendersTwigGuidelinesTrait;

    /**
     * Return the assist instance injected into Twig templates.
     *
     * @return \Crustum\Ignis\Install\GuidelineAssist
     */
    protected function getGuidelineAssist(): GuidelineAssist
    {
        $container = ContainerRegistry::getInstance();

        if ($container->has(GuidelineAssist::class)) {
            /** @var \Crustum\Ignis\Install\GuidelineAssist $assist */
            $assist = $container->get(GuidelineAssist::class);

            return $assist;
        }

        if ($container->has(ProjectManager::class)) {
            $project = $container->get(ProjectManager::class);
            $project->scan(ProjectRoot::path());

            return new GuidelineAssist($project, new GuidelineConfig());
        }

        $project = new ProjectManager();
        $project->scan(ProjectRoot::path());

        return new GuidelineAssist($project, new GuidelineConfig());
    }
}
