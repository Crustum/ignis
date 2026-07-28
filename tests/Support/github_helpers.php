<?php
declare(strict_types=1);

use Cake\Http\Client;
use Cake\Http\Client\Exception\NetworkException;
use Cake\Http\Client\Request;
use Cake\Http\Client\Response;
use Crustum\Ignis\Skills\Remote\GitHubRepository;
use Crustum\Ignis\Skills\Remote\GitHubSkillProvider;
use Crustum\Ignis\Skills\Remote\SkillAuditor;
use Crustum\Ignis\Test\Support\QueuedHttpAdapter;

if (!function_exists('githubJsonResponse')) {
    /**
     * Build a JSON HTTP response for queued Cake HTTP adapters.
     *
     * @param int $status HTTP status code
     * @param array<string, mixed>|string $body Response body
     * @param array<string, string> $headers Additional headers
     * @return \Cake\Http\Client\Response
     */
    function githubJsonResponse(int $status, array|string $body, array $headers = []): Response
    {
        $content = is_string($body) ? $body : json_encode($body, JSON_THROW_ON_ERROR);
        $headerLines = ["HTTP/1.1 {$status}"];

        foreach (array_merge(['Content-Type' => 'application/json'], $headers) as $name => $value) {
            $headerLines[] = "{$name}: {$value}";
        }

        return new Response($headerLines, $content);
    }
}

if (!function_exists('githubMockClient')) {
    /**
     * Create a Cake HTTP client backed by a sequential response queue.
     *
     * @param array<int, \Cake\Http\Client\Response|\Throwable> $responses Queued responses or exceptions
     * @param array<int, array{request: \Psr\Http\Message\RequestInterface, response: \Cake\Http\Client\Response|null, options: array<string, mixed>}>|null $history Request history container
     * @return \Cake\Http\Client
     */
    function githubMockClient(array $responses, ?array &$history = null): Client
    {
        return new Client([
            'adapter' => new QueuedHttpAdapter($responses, $history),
        ]);
    }
}

if (!function_exists('githubProvider')) {
    /**
     * Create a GitHub skill provider with a mocked HTTP client.
     *
     * @param \Crustum\Ignis\Skills\Remote\GitHubRepository $repository Source repository
     * @param array<int, \Cake\Http\Client\Response|\Throwable> $responses Queued HTTP responses
     * @param array<int, array{request: \Psr\Http\Message\RequestInterface, response: \Cake\Http\Client\Response|null, options: array<string, mixed>}>|null $history Request history container
     * @return \Crustum\Ignis\Skills\Remote\GitHubSkillProvider
     */
    function githubProvider(GitHubRepository $repository, array $responses, ?array &$history = null): GitHubSkillProvider
    {
        return new GitHubSkillProvider($repository, githubMockClient($responses, $history));
    }
}

if (!function_exists('skillAuditorWithResponses')) {
    /**
     * Create a skill auditor with a mocked HTTP client.
     *
     * @param array<int, \Cake\Http\Client\Response|\Throwable> $responses Queued HTTP responses
     * @param array<int, array{request: \Psr\Http\Message\RequestInterface, response: \Cake\Http\Client\Response|null, options: array<string, mixed>}>|null $history Request history container
     * @return \Crustum\Ignis\Skills\Remote\SkillAuditor
     */
    function skillAuditorWithResponses(array $responses, ?array &$history = null): SkillAuditor
    {
        return new SkillAuditor(githubMockClient($responses, $history), ignisTestAuditUrl());
    }
}

if (!function_exists('githubDefaultBranchResponse')) {
    /**
     * Build a GitHub repository metadata response.
     *
     * @param string $branch Default branch name
     * @return \Cake\Http\Client\Response
     */
    function githubDefaultBranchResponse(string $branch = 'main'): Response
    {
        return githubJsonResponse(200, ['default_branch' => $branch]);
    }
}

if (!function_exists('githubTreeResponse')) {
    /**
     * Build a GitHub recursive tree response.
     *
     * @param array<int, array<string, mixed>> $tree Tree entries
     * @param bool $truncated Whether the tree was truncated
     * @return \Cake\Http\Client\Response
     */
    function githubTreeResponse(array $tree, bool $truncated = false): Response
    {
        return githubJsonResponse(200, [
            'sha' => 'abc123',
            'url' => 'https://api.github.com/repos/owner/repo/git/trees/abc123',
            'tree' => $tree,
            'truncated' => $truncated,
        ]);
    }
}

if (!function_exists('githubDiscoverResponses')) {
    /**
     * Build the default branch + tree response pair for skill discovery.
     *
     * @param array<int, array<string, mixed>> $tree Tree entries
     * @param string $branch Default branch name
     * @param bool $truncated Whether the tree was truncated
     * @return array<int, \Cake\Http\Client\Response>
     */
    function githubDiscoverResponses(array $tree, string $branch = 'main', bool $truncated = false): array
    {
        return [
            githubDefaultBranchResponse($branch),
            githubTreeResponse($tree, $truncated),
        ];
    }
}

if (!function_exists('githubRawFileResponse')) {
    /**
     * Build a raw.githubusercontent.com file response.
     *
     * @param string $content File contents
     * @return \Cake\Http\Client\Response
     */
    function githubRawFileResponse(string $content): Response
    {
        return new Response(['HTTP/1.1 200'], $content);
    }
}

if (!function_exists('ignisTestAuditUrl')) {
    /**
     * Placeholder hosted audit URL used in tests (not a real service).
     *
     * @return string
     */
    function ignisTestAuditUrl(): string
    {
        return 'https://ignis.test/api/v1/skills/audit';
    }
}

if (!function_exists('githubConnectException')) {
    /**
     * Build a network exception for mock adapters.
     *
     * @param string $message Exception message
     * @return \Cake\Http\Client\Exception\NetworkException
     */
    function githubConnectException(string $message = 'Connection timed out'): NetworkException
    {
        return new NetworkException($message, new Request(ignisTestAuditUrl(), 'GET'));
    }
}

if (!function_exists('githubHistoryContainsUri')) {
    /**
     * Determine whether request history contains a URI fragment.
     *
     * @param array<int, array{request: \Psr\Http\Message\RequestInterface, response: \Cake\Http\Client\Response|null, options: array<string, mixed>}> $history Request history
     * @param string $needle URI fragment to find
     * @return bool
     */
    function githubHistoryContainsUri(array $history, string $needle): bool
    {
        return array_any($history, fn(array $entry): bool => str_contains((string)$entry['request']->getUri(), $needle));
    }
}

if (!function_exists('githubHistoryUriCount')) {
    /**
     * Count request history entries containing a URI fragment.
     *
     * @param array<int, array{request: \Psr\Http\Message\RequestInterface, response: \Cake\Http\Client\Response|null, options: array<string, mixed>}> $history Request history
     * @param string $needle URI fragment to find
     * @return int
     */
    function githubHistoryUriCount(array $history, string $needle): int
    {
        $count = 0;

        foreach ($history as $entry) {
            if (str_contains((string)$entry['request']->getUri(), $needle)) {
                $count++;
            }
        }

        return $count;
    }
}

if (!function_exists('githubAuthProvider')) {
    /**
     * Create a provider that records auth headers on mocked requests.
     *
     * @param \Crustum\Ignis\Skills\Remote\GitHubRepository $repository Source repository
     * @param array<int, \Cake\Http\Client\Response|\Throwable> $responses Queued HTTP responses
     * @param array<int, array{request: \Psr\Http\Message\RequestInterface, response: \Cake\Http\Client\Response|null, options: array<string, mixed>}>|null $history Request history container
     * @return \Crustum\Ignis\Skills\Remote\GitHubSkillProvider
     */
    function githubAuthProvider(GitHubRepository $repository, array $responses, ?array &$history = null): GitHubSkillProvider
    {
        return githubProvider($repository, $responses, $history);
    }
}

if (!function_exists('githubHistoryHasAuthorization')) {
    /**
     * Determine whether any recorded request includes an Authorization header.
     *
     * @param array<int, array{request: \Psr\Http\Message\RequestInterface, response: \Cake\Http\Client\Response|null, options: array<string, mixed>}> $history Request history
     * @param string $token Expected bearer token
     * @return bool
     */
    function githubHistoryHasAuthorization(array $history, string $token): bool
    {
        return array_any(
            $history,
            fn(array $entry): bool => $entry['request']->getHeaderLine('Authorization') === 'Bearer ' . $token,
        );
    }
}
