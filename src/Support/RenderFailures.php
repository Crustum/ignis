<?php
declare(strict_types=1);

namespace Crustum\Ignis\Support;

/**
 * Records guideline files that failed to render during composition.
 */
class RenderFailures
{
    /**
     * Failed guideline paths keyed by absolute path, value is the vendor package.
     *
     * @var array<string, string|null>
     */
    protected array $failures = [];

    /**
     * Record a failed render for the given path.
     *
     * @param string $path Guideline path that failed to render
     * @return void
     */
    public function record(string $path): void
    {
        $this->failures[$path] = $this->packageFromPath($path);
    }

    /**
     * Whether the given path failed to render.
     *
     * @param string $path Guideline path
     * @return bool
     */
    public function failedFor(string $path): bool
    {
        return array_key_exists($path, $this->failures);
    }

    /**
     * Paths that failed to render.
     *
     * @return array<int, string>
     */
    public function paths(): array
    {
        return array_keys($this->failures);
    }

    /**
     * Vendor packages that shipped failing guideline files.
     *
     * @return array<int, string>
     */
    public function packages(): array
    {
        $packages = array_values(array_unique(array_filter($this->failures)));
        sort($packages);

        return $packages;
    }

    /**
     * Whether no failures were recorded.
     *
     * @return bool
     */
    public function isEmpty(): bool
    {
        return $this->failures === [];
    }

    /**
     * Clear all recorded failures.
     *
     * @return void
     */
    public function flush(): void
    {
        $this->failures = [];
    }

    /**
     * Attribute a path to its vendor package, or null outside vendor.
     *
     * @param string $path Absolute guideline path
     * @return string|null
     */
    private function packageFromPath(string $path): ?string
    {
        $normalized = str_replace('\\', '/', $path);

        $vendorMarker = '/vendor/';

        if (!str_contains($normalized, $vendorMarker)) {
            return null;
        }

        $segments = explode('/', substr($normalized, (int)strrpos($normalized, $vendorMarker) + strlen($vendorMarker)));

        return count($segments) >= 3 ? $segments[0] . '/' . $segments[1] : null;
    }
}
