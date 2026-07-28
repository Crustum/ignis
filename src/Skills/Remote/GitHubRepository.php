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
     */
    public function __construct(
        public string $owner,
        public string $repo,
        public string $path = '',
    ) {
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
        return self::parseOwnerRepoPath(self::normalizeUrl($input));
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
     * Normalize a GitHub URL into repository shorthand.
     *
     * @param string $input Repository shorthand or URL
     * @return string Normalized shorthand
     * @throws \InvalidArgumentException When a URL is not hosted by GitHub
     */
    private static function normalizeUrl(string $input): string
    {
        if (!str_starts_with($input, 'http://') && !str_starts_with($input, 'https://')) {
            return $input;
        }

        $parsed = parse_url($input);
        $host = $parsed['host'] ?? '';

        if ($host !== 'github.com' && !str_ends_with($host, '.github.com')) {
            throw new InvalidArgumentException('Only GitHub URLs are supported.');
        }

        $path = trim((string)($parsed['path'] ?? ''), '/');

        return preg_replace('#/tree/[^/]+#', '', $path) ?? $path;
    }

    /**
     * Parse owner, repository, and optional path from shorthand.
     *
     * @param string $input Repository shorthand
     * @return self Parsed repository descriptor
     * @throws \InvalidArgumentException When the shorthand is invalid
     */
    private static function parseOwnerRepoPath(string $input): self
    {
        $parts = explode('/', $input);

        if (count($parts) < 2 || $parts[0] === '' || $parts[1] === '') {
            throw new InvalidArgumentException(
                'Invalid repository format. Expected: owner/repo, owner/repo/path, or GitHub URL',
            );
        }

        return new self($parts[0], $parts[1], implode('/', array_slice($parts, 2)));
    }
}
