<?php
declare(strict_types=1);

namespace Crustum\Ignis\Mcp\Tools;

use Crustum\Ignis\Trait\ReadsLogsTrait;
use Crustum\JsonSchema\Contracts\JsonSchema;
use Crustum\Mcp\Request;
use Crustum\Mcp\Response;
use Crustum\Mcp\Server\Tool;
use Crustum\Mcp\Server\Tools\Annotations\IsReadOnly;
use Override;

/**
 * Reads browser log entries written by Ignis.
 */
#[IsReadOnly]
class BrowserLogs extends Tool
{
    use ReadsLogsTrait;

    /**
     * Tool description.
     *
     * @var string
     */
    protected string $description = 'Read the last N entries from the browser log.';

    /**
     * Define the tool input schema.
     *
     * @param \Crustum\JsonSchema\Contracts\JsonSchema $schema JSON schema builder
     * @return array<string, mixed>
     */
    #[Override]
    public function schema(JsonSchema $schema): array
    {
        return [
            'entries' => $schema->integer()
                ->description('Number of log entries to return.')
                ->required(),
        ];
    }

    /**
     * Handle the tool request.
     *
     * @param \Crustum\Mcp\Request $request MCP request
     * @return \Crustum\Mcp\Response Browser log entries
     */
    public function handle(Request $request): Response
    {
        $entries = (int)$request->get('entries', 0);

        if ($entries <= 0) {
            return Response::error('The "entries" argument must be greater than 0.');
        }

        $logFile = $this->resolveLogFilePathForChannel(
            'browser',
            (defined('LOGS') ? LOGS : sys_get_temp_dir() . DS . 'logs' . DS) . 'browser.log',
        );

        if (!is_file($logFile)) {
            return Response::error('No log file found at ' . $logFile . '. This probably means no logs yet, or the `browser` log channel does not write to a file.');
        }

        $logs = trim(implode("\n\n", $this->readLastLogEntries($logFile, $entries)));

        return $logs === '' ? Response::text('Unable to retrieve log entries, or no logs') : Response::text($logs);
    }
}
