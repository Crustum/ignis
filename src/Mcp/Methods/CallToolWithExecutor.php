<?php
declare(strict_types=1);

namespace Crustum\Ignis\Mcp\Methods;

use Crustum\Ignis\Mcp\ToolExecutor;
use Crustum\Mcp\Exception\JsonRpcException;
use Crustum\Mcp\Response;
use Crustum\Mcp\ResponseFactory;
use Crustum\Mcp\Server\Contracts\Errable;
use Crustum\Mcp\Server\Contracts\Method;
use Crustum\Mcp\Server\Methods\Trait\InteractsWithResponsesTrait;
use Crustum\Mcp\Server\ServerContext;
use Crustum\Mcp\Server\Tool;
use Crustum\Mcp\Transport\JsonRpcRequest;
use Crustum\Mcp\Transport\JsonRpcResponse;
use Throwable;

/**
 * Handles tools/call requests through the isolated Ignis tool executor.
 */
class CallToolWithExecutor implements Errable, Method
{
    use InteractsWithResponsesTrait;

    /**
     * Create the isolated tools/call method handler.
     *
     * @param \Crustum\Ignis\Mcp\ToolExecutor $executor Tool executor
     */
    public function __construct(protected ToolExecutor $executor)
    {
    }

    /**
     * Handle a tools/call JSON-RPC request.
     *
     * @param \Crustum\Mcp\Transport\JsonRpcRequest $request JSON-RPC request
     * @param \Crustum\Mcp\Server\ServerContext $context Server context
     * @return \Crustum\Mcp\Transport\JsonRpcResponse|iterable<\Crustum\Mcp\Transport\JsonRpcResponse>
     */
    public function handle(JsonRpcRequest $request, ServerContext $context): iterable|JsonRpcResponse
    {
        $name = $request->get('name');

        if (!is_string($name) || $name === '') {
            throw new JsonRpcException('Missing [name] parameter.', -32602, $request->id);
        }

        $tool = $context->tools()->filter(fn(Tool $tool): bool => $tool->name() === $name)->first();

        if (!$tool instanceof Tool) {
            throw new JsonRpcException("Tool [{$name}] not found.", -32602, $request->id);
        }

        $arguments = $request->params['arguments'] ?? [];
        $arguments = is_array($arguments) ? $arguments : [];

        try {
            $response = $this->executor->execute($tool::class, $arguments);
        } catch (Throwable $throwable) {
            $response = Response::error('Tool execution error: ' . $throwable->getMessage());
        }

        return $this->toJsonRpcResponse($request, $response, $this->serializable($tool));
    }

    /**
     * Build a serializer for a tool response.
     *
     * @param \Crustum\Mcp\Server\Tool $tool Invoked tool
     * @return callable(\Crustum\Mcp\ResponseFactory): array<string, mixed>
     */
    protected function serializable(Tool $tool): callable
    {
        return fn(ResponseFactory $factory): array => $factory->mergeStructuredContent(
            $factory->mergeMeta([
                'content' => $factory->responses()->map(
                    fn(Response $response): array => $response->content()->toTool($tool),
                )->toList(),
                'isError' => $factory->responses()->some(
                    fn(Response $response): bool => $response->isError(),
                ),
            ]),
        );
    }
}
