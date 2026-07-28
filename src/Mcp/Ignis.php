<?php
declare(strict_types=1);

namespace Crustum\Ignis\Mcp;

use Cake\Collection\Collection;
use Cake\Core\Configure;
use Crustum\Ignis\Mcp\Methods\CallToolWithExecutor;
use Crustum\Ignis\Mcp\Prompts\CakePhpCodeSimplifier\CakePhpCodeSimplifier;
use Crustum\Ignis\Mcp\Prompts\UpgradeCakePhp5\UpgradeCakePhp5;
use Crustum\Ignis\Mcp\Resources\ApplicationInfo as ApplicationInfoResource;
use Crustum\Ignis\Mcp\Tools\ApplicationInfo;
use Crustum\Ignis\Mcp\Tools\BrowserLogs;
use Crustum\Ignis\Mcp\Tools\CurrentTime;
use Crustum\Ignis\Mcp\Tools\DatabaseConnections;
use Crustum\Ignis\Mcp\Tools\DatabaseQuery;
use Crustum\Ignis\Mcp\Tools\DatabaseSchema;
use Crustum\Ignis\Mcp\Tools\GetAbsoluteUrl;
use Crustum\Ignis\Mcp\Tools\LastError;
use Crustum\Ignis\Mcp\Tools\ReadLogEntries;
use Crustum\Ignis\Mcp\Tools\RecordRule;
use Crustum\Ignis\Mcp\Tools\Tinker;
use Crustum\Mcp\Server;

/**
 * CakePHP Ignis MCP server.
 */
class Ignis extends Server
{
    /**
     * MCP server display name.
     *
     * @var string
     */
    protected string $name = 'Cake Ignis';

    /**
     * MCP server instructions.
     *
     * @var string
     */
    protected string $instructions = 'CakePHP MCP server offering database schema access, error logs, and project information.';

    /**
     * Default resource pagination length.
     *
     * @var int
     */
    public int $defaultPaginationLength = 50;

    /**
     * Discover server primitives and use isolated tool execution.
     *
     * @return void
     */
    protected function boot(): void
    {
        $this->tools = $this->filterPrimitives([
            ApplicationInfo::class,
            BrowserLogs::class,
            CurrentTime::class,
            DatabaseConnections::class,
            DatabaseQuery::class,
            DatabaseSchema::class,
            GetAbsoluteUrl::class,
            LastError::class,
            ReadLogEntries::class,
            RecordRule::class,
            Tinker::class,
        ], 'tools');
        $this->resources = $this->filterPrimitives([ApplicationInfoResource::class], 'resources');
        $this->prompts = $this->filterPrompts([
            CakePhpCodeSimplifier::class,
            UpgradeCakePhp5::class,
        ]);
        $this->methods['tools/call'] = CallToolWithExecutor::class;
    }

    /**
     * Filter MCP prompt class names through Ignis configuration.
     *
     * @param array<int, class-string<\Crustum\Mcp\Server\Prompt>> $available Available prompts
     * @return array<int, class-string<\Crustum\Mcp\Server\Prompt>>
     */
    protected function filterPrompts(array $available): array
    {
        return $this->filterPrimitives($available, 'prompts');
    }

    /**
     * Filter primitive classes through Ignis configuration.
     *
     * @param array<int, class-string<\Crustum\Mcp\Server\Tool|\Crustum\Mcp\Server\Resource|\Crustum\Mcp\Server\Prompt>> $available Available primitives
     * @param string $type Primitive category
     * @return array<int, class-string<\Crustum\Mcp\Server\Tool|\Crustum\Mcp\Server\Resource|\Crustum\Mcp\Server\Prompt>>
     */
    protected function filterPrimitives(array $available, string $type): array
    {
        $excluded = Configure::read("Ignis.mcp.{$type}.exclude", []);
        $included = Configure::read("Ignis.mcp.{$type}.include", []);
        $excluded = is_array($excluded) ? $excluded : [];
        $included = is_array($included) ? $included : [];

        $afterExclude = (new Collection($available))
            ->filter(fn(string $class): bool => !in_array($class, $excluded, true))
            ->toList();

        return (new Collection(array_merge($afterExclude, array_filter($included, is_string(...)))))
            ->filter(fn(string $class): bool => class_exists($class))
            ->unique()
            ->toList();
    }
}
