<?php
declare(strict_types=1);

namespace Crustum\Ignis;

use Crustum\Ignis\Install\Agents\Amp;
use Crustum\Ignis\Install\Agents\Antigravity;
use Crustum\Ignis\Install\Agents\ClaudeCode;
use Crustum\Ignis\Install\Agents\Codex;
use Crustum\Ignis\Install\Agents\Copilot;
use Crustum\Ignis\Install\Agents\Cursor;
use Crustum\Ignis\Install\Agents\Factory;
use Crustum\Ignis\Install\Agents\GrokBuild;
use Crustum\Ignis\Install\Agents\Junie;
use Crustum\Ignis\Install\Agents\Kiro;
use Crustum\Ignis\Install\Agents\OpenCode;
use Crustum\Ignis\Install\Agents\Pi;
use Crustum\Ignis\Install\Agents\Zed;
use InvalidArgumentException;

/**
 * Registry of AI coding agent installers.
 */
class IgnisManager
{
    /**
     * Registered agent class map.
     *
     * @var array<string, class-string<\Crustum\Ignis\Install\Agents\Agent>>
     */
    private array $agents = [
        'amp' => Amp::class,
        'junie' => Junie::class,
        'cursor' => Cursor::class,
        'claude_code' => ClaudeCode::class,
        'codex' => Codex::class,
        'copilot' => Copilot::class,
        'factory' => Factory::class,
        'kiro' => Kiro::class,
        'opencode' => OpenCode::class,
        'antigravity' => Antigravity::class,
        'zed' => Zed::class,
        'pi' => Pi::class,
        'grok_build' => GrokBuild::class,
    ];

    /**
     * Register an agent installer class.
     *
     * @param string $key Agent key
     * @param class-string<\Crustum\Ignis\Install\Agents\Agent> $className Agent class name
     * @return void
     */
    public function registerAgent(string $key, string $className): void
    {
        if (array_key_exists($key, $this->agents)) {
            throw new InvalidArgumentException("Agent '{$key}' is already registered");
        }

        $this->agents[$key] = $className;
    }

    /**
     * Return registered agent installers.
     *
     * @return array<string, class-string<\Crustum\Ignis\Install\Agents\Agent>>
     */
    public function getAgents(): array
    {
        return $this->agents;
    }
}
