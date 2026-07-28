<?php
declare(strict_types=1);

namespace Crustum\Ignis\Mcp\Tools;

use Cake\Core\Configure;
use Crustum\Ignis\Rules\RuleRepository;
use Crustum\JsonSchema\Contracts\JsonSchema;
use Crustum\Mcp\Request;
use Crustum\Mcp\Response;
use Crustum\Mcp\Server\Tool;
use Override;
use Throwable;

/**
 * Records a durable project rule in the configured rule repository.
 */
class RecordRule extends Tool
{
    /**
     * Tool description.
     *
     * @var string
     */
    protected string $description = 'Record a durable, non-secret project rule for future agents and teammates.';

    /**
     * Create the project rule tool.
     *
     * @param \Crustum\Ignis\Rules\RuleRepository $ruleRepository Rule repository
     */
    public function __construct(protected RuleRepository $ruleRepository)
    {
    }

    /**
     * Determine whether rule recording is enabled.
     *
     * @return bool Whether the tool can be registered
     */
    public function shouldRegister(): bool
    {
        return (bool)Configure::read('Ignis.rules.enabled', false);
    }

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
            'glob' => $schema->string()
                ->description('Glob for the files this rule applies to, for example src/Controller/** or templates/**/*.php.')
                ->required(),
            'title' => $schema->string()
                ->description('A short, specific heading for the rule.')
                ->required(),
            'note' => $schema->string()
                ->description('A few lines stating the rule plainly.')
                ->required(),
        ];
    }

    /**
     * Handle the tool request.
     *
     * @param \Crustum\Mcp\Request $request MCP request
     * @return \Crustum\Mcp\Response Result message
     */
    public function handle(Request $request): Response
    {
        $glob = trim((string)$request->get('glob'));
        $title = trim((string)$request->get('title'));
        $note = trim((string)$request->get('note'));

        if ($glob === '' || $title === '' || $note === '') {
            return Response::error('A rule needs a non-empty glob, title, and note.');
        }

        try {
            $glob = $this->ruleRepository->normalizeGlob($glob);
            $location = $this->ruleRepository->write($glob, $title, $note);
        } catch (Throwable $throwable) {
            return Response::error('Failed to write rule: ' . $throwable->getMessage());
        }

        return Response::text('Recorded rule in ' . $this->ruleRepository->relativePath($location) . ": {$title}.");
    }
}
