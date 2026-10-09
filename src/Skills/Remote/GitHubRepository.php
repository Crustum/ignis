<?php
declare(strict_types=1);

namespace Crustum\Ignis\Skills\Remote;

use InvalidArgumentException;

/**
 * A GitHub repository and optional path containing skills.
 */
class GitHubRepository
{
    /**
     * Create a GitHub repository descriptor.
     *
     * @param string $owner GitHub repository owner
     * @param string $repo GitHub repository name
     * @param string $path Optional repository-relative path
     * @param string $branch Optional branch name (overrides the default branch)
     */
    public function __construct(
        public string $owner,
        public string $repo,
        public string $path = '',
        public string $branch = '',
    ) {
        $path = trim($path, '/');
        $lastSegment = str_contains($path, '/') ? substr($path, (int)strrpos($path, '/') + 1) : $path;
        $directory = $lastSegment === 'SKILL.md'
            ? (str_contains($path, '/') ? substr($path, 0, (int)strrpos($path, '/')) : '')
            : $path;

        $this->path = $directory === '.' ? '' : $directory;
    }

    /**
     * Parse a repository shorthand or GitHub URL.
     *
     * @param string $input Repository shorthand or URL
     * @return self Parsed repository descriptor
     * @throws \InvalidArgumentException When the input is invalid
     */
    public static function fromInput(string $input): self
    {
        [$input, $branch] = self::normalizeUrl($input);

        return self::parseOwnerRepoPath($input, $branch);
    }

    /**
     * Get the repository owner/name.
     *
     * @return string Full GitHub repository name
     */
    public function fullName(): string
    {
        return $this->owner . '/' . $this->repo;
    }

    /**
     * Get the repository source string including its optional path.
     *
     * @return string Source string
     */
    public function source(): string
    {
        return $this->path === ''
            ? $this->fullName()
            : $this->fullName() . '/' . $this->path;
    }

    /**
     * Normalize a GitHub URL into repository shorthand and branch.
     *
     * @param string $input Repository shorthand or URL
     * @return array{0: string, 1: string} Normalized shorthand and branch
     * @throws \InvalidArgumentException When a URL is not hosted by GitHub
     */
    private static function normalizeUrl(string $input): array
    {
        if (preg_match('~^(?:[^@/:]+@)?(?<host>[^:]+):(?!//)(?<path>.+)$~', $input, $matches) === 1) {
            $input = 'ssh://' . $matches['host'] . '/' . $matches['path'];
        }

        if (!str_starts_with($input, 'http://') && !str_starts_with($input, 'https://') && !str_starts_with($input, 'ssh://')) {
            return [$input, ''];
        }

        $parsed = parse_url($input);
        $host = $parsed['host'] ?? '';

        if ($host !== 'github.com' && !str_ends_with($host, '.github.com')) {
            throw new InvalidArgumentException('Only GitHub URLs are supported.');
        }

        $path = trim((string)($parsed['path'] ?? ''), '/');

        if (str_ends_with($path, '.git')) {
            $path = substr($path, 0, -4);
        }

        // A branch name containing a slash is indistinguishable from the path after it.
        if (preg_match('#^(?P<repository>[^/]+/[^/]+)/(?:tree|blob)/(?P<branch>[^/]+)/?(?P<path>.*)$#', $path, $matches) === 1) {
            return [rtrim($matches['repository'] . '/' . $matches['path'], '/'), $matches['branch']];
        }

        return [$path, ''];
    }

    /**
     * Parse owner, repository, optional path, and branch from shorthand.
     *
     * @param string $input Repository shorthand
     * @param string $branch Optional branch name
     * @return self Parsed repository descriptor
     * @throws \InvalidArgumentException When the shorthand is invalid
     */
    private static function parseOwnerRepoPath(string $input, string $branch = ''): self
    {
        $parts = explode('/', $input);

        if (count($parts) < 2 || $parts[0] === '' || $parts[1] === '') {
            throw new InvalidArgumentException(
                'Invalid repository format. Expected: owner/repo, owner/repo/path, or GitHub URL',
            );
        }

        return new self($parts[0], $parts[1], implode('/', array_slice($parts, 2)), $branch);
    }
}
