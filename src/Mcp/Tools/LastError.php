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
 * Returns the latest backend error from CakePHP log files.
 */
#[IsReadOnly]
class LastError extends Tool
{
    use ReadsLogsTrait;

    /**
     * Tool description.
     *
     * @var string
     */
    protected string $description = 'Get the latest backend error from a CakePHP log channel configured with Log::setConfig() (defaults to the error channel).';

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
            'channel' => $schema->string()
                ->description('CakePHP log channel name. Defaults to error when omitted.'),
        ];
    }

    /**
     * Handle the tool request.
     *
     * @param \Crustum\Mcp\Request $request MCP request
     * @return \Crustum\Mcp\Response Latest error log entry
     */
    public function handle(Request $request): Response
    {
        $channel = $request->get('channel');

        if (!is_string($channel) || $channel === '') {
            $channel = 'error';
        }

        $channelError = $this->validateFileLogChannel($channel);

        if ($channelError instanceof Response) {
            return $channelError;
        }

        $logFile = $this->resolveLogFilePathForChannel($channel);

        if (!is_file($logFile)) {
            return Response::error("Log file not found at {$logFile}");
        }

        $entry = $this->readLastErrorEntry($logFile);

        if ($entry === null) {
            return Response::error('Unable to find an ERROR entry in the inspected portion of the log file.');
        }

        return Response::text(strlen($entry) > 500 ? substr($entry, 0, 500) . '... more logs' : $entry);
    }
}
