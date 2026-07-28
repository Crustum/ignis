<?php
declare(strict_types=1);

namespace Crustum\Ignis\Mcp\Resources;

use Crustum\Ignis\Mcp\Tools\ApplicationInfo as ApplicationInfoTool;
use Crustum\Mcp\Request;
use Crustum\Mcp\Response;
use Crustum\Mcp\Server\Resource;
use Crustum\Mcp\Support\ContainerRegistry;

/**
 * Exposes application information as an MCP resource.
 */
class ApplicationInfo extends Resource
{
    /**
     * Resource description.
     *
     * @var string
     */
    protected string $description = 'Comprehensive CakePHP application information including runtime and package versions.';

    /**
     * Resource URI.
     *
     * @var string
     */
    protected string $uri = 'file://instructions/application-info.md';

    /**
     * Resource MIME type.
     *
     * @var string
     */
    protected string $mimeType = 'text/markdown';

    /**
     * Handle the resource request.
     *
     * @return \Crustum\Mcp\Response Application data
     */
    public function handle(): Response
    {
        $container = ContainerRegistry::getInstance();
        /** @var \Crustum\Ignis\Mcp\Tools\ApplicationInfo $tool */
        $tool = $container->get(ApplicationInfoTool::class);

        return $tool->handle(new Request());
    }
}
