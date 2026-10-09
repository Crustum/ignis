<?php
declare(strict_types=1);

namespace Crustum\Ignis\Mcp;

use Cake\Core\Configure;
use Crustum\Ignis\Support\CommandNormalizer;
use Crustum\Ignis\Support\ProjectRoot;
use Crustum\Mcp\Response;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Exception\ProcessTimedOutException;
use Symfony\Component\Process\Process;

/**
 * Runs allowed MCP tools in a Cake console subprocess.
 */
class ToolExecutor
{
    /**
     * Execute an allowed tool in an isolated process.
     *
     * @param string $toolClass Tool class name
     * @param array<string, mixed> $arguments Tool arguments
     * @return \Crustum\Mcp\Response Tool response
     */
    public function execute(string $toolClass, array $arguments = []): Response
    {
        if (!ToolRegistry::isToolAllowed($toolClass)) {
            return Response::error("Tool not registered or not allowed: {$toolClass}");
        }

        $process = new Process($this->buildCommand($toolClass, $arguments), timeout: $this->getTimeout($arguments));

        try {
            $process->mustRun();
        } catch (ProcessTimedOutException) {
            $process->stop();

            return Response::error("Tool execution timed out after {$this->getTimeout($arguments)} seconds");
        } catch (ProcessFailedException) {
            return Response::error('Process tool execution failed: ' . $process->getErrorOutput() . $process->getOutput());
        }

        $decoded = json_decode($process->getOutput(), true);

        if (!is_array($decoded)) {
            return Response::error('Invalid JSON output from tool process: ' . json_last_error_msg());
        }

        return $this->reconstructResponse($decoded);
    }

    /**
     * Get the configured execution timeout.
     *
     * @param array<string, mixed> $arguments Tool arguments
     * @return int Timeout in seconds
     */
    protected function getTimeout(array $arguments): int
    {
        $timeout = $arguments['timeout'] ?? Configure::read('Ignis.mcp.tool_timeout', 180);

        return max(1, (int)$timeout);
    }

    /**
     * Reconstruct an MCP response from subprocess JSON.
     *
     * @param array<string, mixed> $data Serialized response payload
     * @return \Crustum\Mcp\Response Reconstructed response
     */
    protected function reconstructResponse(array $data): Response
    {
        $content = $data['content'] ?? null;

        if (!isset($data['isError']) || !is_array($content)) {
            return Response::error('Invalid tool response format.');
        }

        $first = $content[0] ?? [];
        $text = is_array($first) && is_string($first['text'] ?? null) ? $first['text'] : '';

        if ($data['isError']) {
            return Response::error($text !== '' ? $text : 'Unknown error');
        }

        $json = json_decode($text, true);

        return is_array($json) ? Response::json($json) : Response::text($text);
    }

    /**
     * Build the Cake console command for tool execution.
     *
     * @param string $toolClass Tool class name
     * @param array<string, mixed> $arguments Tool arguments
     * @return array<int, string> Process command arguments
     */
    protected function buildCommand(string $toolClass, array $arguments): array
    {
        $php = Configure::read('Ignis.executable_paths.php') ?: PHP_BINARY;
        $normalized = CommandNormalizer::normalize((string)$php);
        $encoded = json_encode($arguments);

        return [
            $normalized['command'],
            ...$normalized['args'],
            ProjectRoot::path() . DS . 'bin' . DS . 'cake.php',
            'ignis',
            'execute-tool',
            $toolClass,
            base64_encode($encoded === false ? '{}' : $encoded),
        ];
    }
}
