<?php
declare(strict_types=1);

namespace Crustum\Ignis\Mcp\Tools;

use Cake\Core\Configure;
use Cake\I18n\DateTime;
use Crustum\JsonSchema\Contracts\JsonSchema;
use Crustum\Mcp\Request;
use Crustum\Mcp\Response;
use Crustum\Mcp\Server\Tool;
use Crustum\Mcp\Server\Tools\Annotations\IsReadOnly;
use DateTimeZone;
use Exception;
use Override;

/**
 * Returns the host application current date and time.
 */
#[IsReadOnly]
class CurrentTime extends Tool
{
    /**
     * Tool description.
     *
     * @var string
     */
    protected string $description = 'Get the current date and time from the application host in App.defaultTimezone. Prefer this over shell date/time commands. Returns a single formatted timestamp for docs and logs.';

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
            'timezone' => $schema->string()
                ->description('Optional IANA timezone (for example Europe/Kyiv). Defaults to App.defaultTimezone or the PHP default timezone.'),
        ];
    }

    /**
     * Handle the tool request.
     *
     * @param \Crustum\Mcp\Request $request MCP request
     * @return \Crustum\Mcp\Response Current time as a single formatted string
     */
    public function handle(Request $request): Response
    {
        $timezoneName = $this->resolveTimezoneName($request);

        try {
            $timezone = new DateTimeZone($timezoneName);
        } catch (Exception) {
            return Response::error(sprintf('Invalid timezone [%s].', $timezoneName));
        }

        return Response::text(DateTime::now($timezone)->format('Y-m-d H:i:s P'));
    }

    /**
     * Resolve the timezone name from the request or application defaults.
     *
     * @param \Crustum\Mcp\Request $request MCP request
     * @return string
     */
    protected function resolveTimezoneName(Request $request): string
    {
        $timezone = $request->get('timezone');

        if (is_string($timezone) && $timezone !== '') {
            return $timezone;
        }

        $configured = Configure::read('App.defaultTimezone');

        if (is_string($configured) && $configured !== '') {
            return $configured;
        }

        return date_default_timezone_get();
    }
}
