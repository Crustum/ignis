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
 * Reads the latest application log entries.
 */
#[IsReadOnly]
class ReadLogEntries extends Tool
{
    use ReadsLogsTrait;

    /**
     * Tool description.
     *
     * @var string
     */
    protected string $description = 'Read the last N entries from a CakePHP log channel configured with Log::setConfig() in config/app.php.';

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
            'channel' => $schema->string()
                ->description('CakePHP log channel name (for example error, debug, or queries).')
                ->required(),
        ];
    }

    /**
     * Handle the tool request.
     *
     * @param \Crustum\Mcp\Request $request MCP request
     * @return \Crustum\Mcp\Response Application log entries
     */
    public function handle(Request $request): Response
    {
        $entries = (int)$request->get('entries', 0);

        if ($entries <= 0) {
            return Response::error('The "entries" argument must be greater than 0.');
        }

        $channel = $request->get('channel');

        if (!is_string($channel) || $channel === '') {
            return Response::error(
                'The "channel" argument is required. Configured file log channels: '
                . $this->formatConfiguredFileLogChannels(),
            );
        }

        $channelError = $this->validateFileLogChannel($channel);

        if ($channelError instanceof Response) {
            return $channelError;
        }

        $logFile = $this->resolveLogFilePathForChannel($channel);

        if (!is_file($logFile)) {
            return Response::error("Log file not found at {$logFile}");
        }

        $logs = trim(implode("\n\n", $this->readLastLogEntries($logFile, $entries)));

        return $logs === '' ? Response::text('Unable to retrieve log entries, or no entries yet.') : Response::text($logs);
    }
}
