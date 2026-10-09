<?php
declare(strict_types=1);

namespace Crustum\Ignis\Skills\Remote;

use Cake\Collection\Collection;
use Cake\Core\Configure;
use Cake\Http\Client;
use Cake\Http\Client\Response;
use Crustum\Ignis\Install\SkillWriter;
use RuntimeException;
use Throwable;

/**
 * Discovers and downloads skills from a GitHub repository.
 */
class GitHubSkillProvider
{
    /**
     * The resolved repository default branch.
     *
     * @var string|null
     */
    protected ?string $defaultBranch = null;

    /**
     * Cached response from the GitHub tree API.
     *
     * @var array<string, mixed>|null
     */
    protected ?array $cachedTree = null;

    /**
     * Create a GitHub skill provider.
     *
     * @param \Crustum\Ignis\Skills\Remote\GitHubRepository $repository Source repository
     * @param \Cake\Http\Client|null $httpClient Optional HTTP client for testing
     */
    public function __construct(
        protected GitHubRepository $repository,
        protected ?Client $httpClient = null,
    ) {
    }

    /**
     * Discover the direct child skills within the configured repository path.
     *
     * @return \Cake\Collection\Collection<string, \Crustum\Ignis\Skills\Remote\RemoteSkill>
     */
    public function discoverSkills(): Collection
    {
        $tree = $this->fetchRepositoryTree();

        if ($tree === null) {
            return new Collection([]);
        }

        $basePath = $this->repository->path;
        $prefix = $basePath === '' ? '' : $basePath . '/';

        return (new Collection($tree['tree']))
            ->filter(function (array $item) use ($prefix): bool {
                $path = (string)($item['path'] ?? '');

                return ($item['type'] ?? null) === 'blob'
                    && $this->isSkillMarker($path)
                    && str_starts_with($path, $prefix)
                    && SkillWriter::isValidSkillName(static::skillName($path));
            })
            ->map(fn(array $item): RemoteSkill => new RemoteSkill(
                static::skillName((string)$item['path']),
                $this->repository->fullName(),
                static::skillDirectory((string)$item['path']),
            ))
            ->indexBy(fn(RemoteSkill $skill): string => $skill->name);
    }

    /**
     * Whether a tree path points at a skill marker file.
     *
     * @param string $path Repository-relative tree path
     * @return bool
     */
    protected function isSkillMarker(string $path): bool
    {
        $name = str_contains($path, '/') ? substr($path, (int)strrpos($path, '/') + 1) : $path;

        return $name === 'SKILL.md' || $name === 'SKILL.twig';
    }

    /**
     * Derive a skill name from its marker path.
     *
     * @param string $markerPath Repository-relative marker path
     * @return string
     */
    protected static function skillName(string $markerPath): string
    {
        $directory = self::skillDirectory($markerPath);

        if ($directory === '' || !str_contains($directory, '/')) {
            return $directory;
        }

        return substr($directory, (int)strrpos($directory, '/') + 1);
    }

    /**
     * Derive a skill directory from its marker path.
     *
     * Repository paths are always slash-delimited, so basename() and dirname()
     * would split on a backslash under Windows.
     *
     * @param string $markerPath Repository-relative marker path
     * @return string
     */
    protected static function skillDirectory(string $markerPath): string
    {
        return str_contains($markerPath, '/')
            ? substr($markerPath, 0, (int)strrpos($markerPath, '/'))
            : '';
    }

    /**
     * Download all files belonging to a skill.
     *
     * @param \Crustum\Ignis\Skills\Remote\RemoteSkill $skill Skill to download
     * @param string $targetPath Local skill target directory
     * @return bool Whether all files were downloaded successfully
     */
    public function downloadSkill(RemoteSkill $skill, string $targetPath): bool
    {
        $tree = $this->fetchRepositoryTree();

        if ($tree === null) {
            return false;
        }

        $skillFiles = $this->extractSkillFilesFromTree($tree['tree'], $skill->path);

        if ($skillFiles->isEmpty()) {
            return false;
        }

        $blobs = $skillFiles->filter(fn(array $item): bool => ($item['type'] ?? null) === 'blob');

        if (self::treeEscapesSkillDirectory($blobs->toList())) {
            return false;
        }

        if (!$this->ensureDirectoryExists($targetPath)) {
            return false;
        }

        $files = $blobs
            ->reject(fn(array $item): bool => preg_match('/\.(php\d?|phar|phtml)$/i', (string)($item['path'] ?? '')) === 1);

        if (!$this->treeContainsSkillMarker($files->toList())) {
            return false;
        }

        $directories = $skillFiles->filter(fn(array $item): bool => ($item['type'] ?? null) === 'tree');

        foreach ($directories as $directory) {
            $localDirectory = $this->resolveSafeLocalPath(
                $targetPath,
                $this->getRelativePath((string)$directory['path'], $skill->path),
            );

            if ($localDirectory === null || !$this->ensureDirectoryExists($localDirectory)) {
                return false;
            }
        }

        return $this->downloadFiles($files->toList(), $targetPath, $skill->path);
    }

    /**
     * Whether any tree path escapes the skill directory.
     *
     * @param array<int, array<string, mixed>> $blobs Blob tree entries
     * @return bool
     */
    protected static function treeEscapesSkillDirectory(array $blobs): bool
    {
        foreach ($blobs as $item) {
            foreach (explode('/', (string)($item['path'] ?? '')) as $segment) {
                if (!SkillWriter::isValidSkillName($segment)) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Whether the tree holds a skill marker file.
     *
     * @param array<int, array<string, mixed>> $files Blob tree entries
     * @return bool
     */
    protected function treeContainsSkillMarker(array $files): bool
    {
        foreach ($files as $item) {
            if ($this->isSkillMarker((string)($item['path'] ?? ''))) {
                return true;
            }
        }

        return false;
    }

    /**
     * Fetch and cache the repository file tree.
     *
     * @return array{tree: array<int, array<string, mixed>>, sha: string, url: string, truncated: bool}|null
     * @throws \RuntimeException When GitHub returns an invalid or failed response
     */
    protected function fetchRepositoryTree(): ?array
    {
        if ($this->cachedTree !== null) {
            return $this->cachedTree;
        }

        $response = $this->get(
            sprintf(
                'https://api.github.com/repos/%s/%s/git/trees/%s?recursive=1',
                $this->repository->owner,
                $this->repository->repo,
                urlencode($this->resolveBranch()),
            ),
        );

        if ($response->getStatusCode() === 403 && $response->getHeaderLine('X-RateLimit-Remaining') === '0') {
            $reset = $response->getHeaderLine('X-RateLimit-Reset');
            $resetTime = $reset !== '' ? date('Y-m-d H:i:s', (int)$reset) : 'unknown';

            throw new RuntimeException(
                "GitHub API rate limit exceeded. Rate limit will reset at {$resetTime}. "
                . 'Configure a GitHub token via Ignis.github.token for higher limits.',
            );
        }

        if ($response->getStatusCode() >= 400) {
            $body = $this->decodeJson($response);
            $message = is_string($body['message'] ?? null) ? $body['message'] : 'Unknown error';

            throw new RuntimeException(
                "Failed to fetch repository tree from GitHub: {$message} (HTTP {$response->getStatusCode()})",
            );
        }

        $tree = $this->decodeJson($response);

        if (!isset($tree['tree']) || !is_array($tree['tree'])) {
            throw new RuntimeException('Invalid response structure from GitHub Tree API');
        }

        $this->cachedTree = $tree;

        return $tree;
    }

    /**
     * Extract the tree entries within a skill path.
     *
     * @param array<int, array<string, mixed>> $tree Repository tree
     * @param string $skillPath Repository-relative skill path
     * @return \Cake\Collection\Collection<int, array<string, mixed>>
     */
    protected function extractSkillFilesFromTree(array $tree, string $skillPath): Collection
    {
        $prefix = $skillPath . '/';

        return new Collection((new Collection($tree))
            ->filter(fn(array $item): bool => str_starts_with((string)($item['path'] ?? ''), $prefix))
            ->toList());
    }

    /**
     * Download remote files for a skill.
     *
     * @param array<int, array<string, mixed>> $files File tree entries
     * @param string $targetPath Local target directory
     * @param string $basePath Repository-relative skill root
     * @return bool Whether all files were written successfully
     */
    protected function downloadFiles(array $files, string $targetPath, string $basePath): bool
    {
        foreach ($files as $file) {
            $path = (string)$file['path'];

            try {
                $response = $this->get($this->buildRawFileUrl($path), 60);
            } catch (Throwable) {
                return false;
            }

            if ($response->getStatusCode() >= 400) {
                return false;
            }

            $localPath = $this->resolveSafeLocalPath(
                $targetPath,
                $this->getRelativePath($path, $basePath),
            );

            if (
                $localPath === null
                || !$this->ensureDirectoryExists(dirname($localPath))
                || file_put_contents($localPath, $response->getStringBody()) === false
            ) {
                return false;
            }
        }

        return true;
    }

    /**
     * Build the raw GitHub URL for a repository file.
     *
     * @param string $path Repository-relative file path
     * @return string Raw file URL
     */
    protected function buildRawFileUrl(string $path): string
    {
        return sprintf(
            'https://raw.githubusercontent.com/%s/%s/%s/%s',
            $this->repository->owner,
            $this->repository->repo,
            $this->resolveBranch(),
            ltrim($path, '/'),
        );
    }

    /**
     * Get the path relative to a skill root.
     *
     * @param string $fullPath Full repository-relative path
     * @param string $basePath Repository-relative skill root
     * @return string Relative file path
     */
    protected function getRelativePath(string $fullPath, string $basePath): string
    {
        $prefix = $basePath . '/';

        return str_starts_with($fullPath, $prefix)
            ? substr($fullPath, strlen($prefix))
            : basename($fullPath);
    }

    /**
     * Resolve a download path that stays inside the skill target directory.
     *
     * Rejects relative segments such as `..` so a malicious remote tree cannot
     * write outside `$targetPath`.
     *
     * @param string $targetPath Local skill target directory
     * @param string $relativePath Path relative to the skill root
     * @return string|null Absolute local path when safe; null when rejected
     */
    protected function resolveSafeLocalPath(string $targetPath, string $relativePath): ?string
    {
        $relativePath = str_replace('\\', '/', $relativePath);

        if ($relativePath === '' || str_contains($relativePath, "\0")) {
            return null;
        }

        $segments = array_values(array_filter(explode('/', $relativePath), static fn(string $segment): bool => $segment !== ''));

        if (array_any($segments, static fn(string $segment): bool => $segment === '.' || $segment === '..')) {
            return null;
        }

        if (!$this->ensureDirectoryExists($targetPath)) {
            return null;
        }

        $targetReal = realpath($targetPath);

        if ($targetReal === false) {
            return null;
        }

        $candidate = $targetReal . DIRECTORY_SEPARATOR . implode(DIRECTORY_SEPARATOR, $segments);
        $candidateParent = dirname($candidate);

        if (!$this->ensureDirectoryExists($candidateParent)) {
            return null;
        }

        $parentReal = realpath($candidateParent);

        if ($parentReal === false || !$this->isPathInsideDirectory($parentReal, $targetReal)) {
            return null;
        }

        return $parentReal . DIRECTORY_SEPARATOR . basename($candidate);
    }

    /**
     * Determine whether a resolved path is inside a jail directory.
     *
     * @param string $path Absolute path to check
     * @param string $directory Absolute jail directory
     * @return bool
     */
    protected function isPathInsideDirectory(string $path, string $directory): bool
    {
        $normalizedDirectory = rtrim(str_replace('\\', '/', $directory), '/');
        $normalizedPath = rtrim(str_replace('\\', '/', $path), '/');

        return $normalizedPath === $normalizedDirectory
            || str_starts_with($normalizedPath, $normalizedDirectory . '/');
    }

    /**
     * Ensure a directory exists.
     *
     * @param string $path Directory path
     * @return bool Whether the directory exists or was created
     */
    protected function ensureDirectoryExists(string $path): bool
    {
        return is_dir($path) || mkdir($path, 0755, true);
    }

    /**
     * Perform a GET request with GitHub headers.
     *
     * @param string $url Absolute URL
     * @param int $timeout Request timeout in seconds
     * @return \Cake\Http\Client\Response
     */
    protected function get(string $url, int $timeout = 30): Response
    {
        return $this->client($timeout)->get($url, [], [
            'headers' => $this->defaultHeaders(),
            'timeout' => $timeout,
        ]);
    }

    /**
     * Create a configured GitHub HTTP client.
     *
     * @param int $timeout Request timeout in seconds
     * @return \Cake\Http\Client HTTP client
     */
    protected function client(int $timeout = 30): Client
    {
        if ($this->httpClient instanceof Client) {
            return $this->httpClient;
        }

        return new Client([
            'headers' => $this->defaultHeaders(),
            'timeout' => $timeout,
        ]);
    }

    /**
     * Default headers for GitHub API and raw content requests.
     *
     * @return array<string, string>
     */
    protected function defaultHeaders(): array
    {
        $headers = [
            'Accept' => 'application/vnd.github.v3+json',
            'User-Agent' => 'CakePHP-Ignis',
        ];
        $token = $this->getGitHubToken();

        if ($token !== null) {
            $headers['Authorization'] = "Bearer {$token}";
        }

        return $headers;
    }

    /**
     * Resolve and cache the repository default branch.
     *
     * @return string Default branch name
     */

    /**
     * Resolve the branch used for tree and raw content requests.
     *
     * @return string Branch name
     */
    protected function resolveBranch(): string
    {
        if ($this->repository->branch !== '') {
            return $this->repository->branch;
        }

        return $this->resolveDefaultBranch();
    }

    /**
     * Resolve and cache the repository default branch.
     *
     * @return string Default branch name
     */
    protected function resolveDefaultBranch(): string
    {
        if ($this->defaultBranch !== null) {
            return $this->defaultBranch;
        }

        $response = $this->get(
            sprintf('https://api.github.com/repos/%s/%s', $this->repository->owner, $this->repository->repo),
            15,
        );
        $data = $response->getStatusCode() < 400 ? $this->decodeJson($response) : [];
        $branch = $data['default_branch'] ?? null;
        $this->defaultBranch = is_string($branch) ? $branch : 'main';

        return $this->defaultBranch;
    }

    /**
     * Decode a JSON GitHub response.
     *
     * @param \Cake\Http\Client\Response $response HTTP response
     * @return array<string, mixed> Decoded response data
     */
    protected function decodeJson(Response $response): array
    {
        $data = $response->getJson();

        return is_array($data) ? $data : [];
    }

    /**
     * Get the configured GitHub access token.
     *
     * @return string|null GitHub token when configured
     */
    protected function getGitHubToken(): ?string
    {
        $token = Configure::read('Ignis.github.token');

        return is_string($token) && $token !== '' ? $token : null;
    }
}
