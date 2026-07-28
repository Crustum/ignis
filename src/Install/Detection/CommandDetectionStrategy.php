<?php
declare(strict_types=1);

namespace Crustum\Ignis\Install\Detection;

use Crustum\Ignis\Install\Contracts\DetectionStrategy;
use Crustum\Ignis\Install\Enums\Platform;

/**
 * Detects installation by running a shell command and checking its exit code.
 */
class CommandDetectionStrategy implements DetectionStrategy
{
    /**
     * Detect if the configured command succeeds.
     *
     * @param array{command?: string, basePath?: string, files?: array<int, string>, paths?: array<int, string>} $config Detection configuration
     * @param \Crustum\Ignis\Install\Enums\Platform|null $platform Target platform
     * @return bool
     */
    public function detect(array $config, ?Platform $platform = null): bool
    {
        if (!isset($config['command'])) {
            return false;
        }

        return $this->runCommand($config['command']);
    }

    /**
     * Execute a command and return whether it exited successfully.
     *
     * @param string $command Shell command
     * @return bool
     */
    protected function runCommand(string $command): bool
    {
        $descriptorSpec = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];

        $process = proc_open($command, $descriptorSpec, $pipes);

        if (!is_resource($process)) {
            return false;
        }

        fclose($pipes[0]);
        stream_get_contents($pipes[1]);
        stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);

        return proc_close($process) === 0;
    }
}
