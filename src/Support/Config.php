<?php
declare(strict_types=1);

namespace Crustum\Ignis\Support;

use Cake\Utility\Hash;

/**
 * Reads and writes project-local ignis.json install state.
 */
class Config
{
    protected const FILE = 'ignis.json';

    /**
     * Whether guidelines are enabled in ignis.json.
     *
     * @return bool
     */
    public function getGuidelines(): bool
    {
        return (bool)$this->get('guidelines', false);
    }

    /**
     * Persist guidelines toggle.
     *
     * @param bool $enabled Guidelines enabled flag
     * @return void
     */
    public function setGuidelines(bool $enabled): void
    {
        $this->set('guidelines', $enabled);
    }

    /**
     * Return configured skill names.
     *
     * @return array<int, string>
     */
    public function getSkills(): array
    {
        return $this->get('skills', []);
    }

    /**
     * Persist configured skill names.
     *
     * @param array<int, string> $skills Skill names
     * @return void
     */
    public function setSkills(array $skills): void
    {
        $this->set('skills', $skills);
    }

    /**
     * Whether any skills are configured.
     *
     * @return bool
     */
    public function hasSkills(): bool
    {
        return $this->getSkills() !== [];
    }

    /**
     * Whether MCP install is enabled in ignis.json.
     *
     * @return bool
     */
    public function getMcp(): bool
    {
        return (bool)$this->get('mcp', false);
    }

    /**
     * Persist MCP install toggle.
     *
     * @param bool $enabled MCP enabled flag
     * @return void
     */
    public function setMcp(bool $enabled): void
    {
        $this->set('mcp', $enabled);
    }

    /**
     * Return configured third-party package names.
     *
     * @return array<int, string>
     */
    public function getPackages(): array
    {
        return $this->get('packages', []);
    }

    /**
     * Persist third-party package names.
     *
     * @param array<int, string> $packages Package names
     * @return void
     */
    public function setPackages(array $packages): void
    {
        $this->set('packages', $packages);
    }

    /**
     * Persist configured agent keys.
     *
     * @param array<int, string> $agents Agent keys
     * @return void
     */
    public function setAgents(array $agents): void
    {
        $this->set('agents', $agents);
    }

    /**
     * Return configured agent keys.
     *
     * @return array<int, string>
     */
    public function getAgents(): array
    {
        return $this->get('agents', []);
    }

    /**
     * Whether ignis.json exists and contains valid JSON.
     *
     * @return bool
     */
    public function isValid(): bool
    {
        $path = $this->filePath();

        if (!is_file($path)) {
            return false;
        }

        $contents = file_get_contents($path);

        if ($contents === false) {
            return false;
        }

        json_decode($contents, true);

        return json_last_error() === JSON_ERROR_NONE;
    }

    /**
     * Delete ignis.json when present.
     *
     * @return void
     */
    public function flush(): void
    {
        $path = $this->filePath();

        if (is_file($path)) {
            unlink($path);
        }
    }

    /**
     * Read a nested config value from ignis.json.
     *
     * @param string $key Config key
     * @param mixed $default Default value
     * @return mixed
     */
    protected function get(string $key, mixed $default = null): mixed
    {
        return Hash::get($this->all(), $key, $default);
    }

    /**
     * Write a nested config value to ignis.json.
     *
     * @param string $key Config key
     * @param mixed $value Config value
     * @return void
     */
    protected function set(string $key, mixed $value): void
    {
        $config = array_filter($this->all(), static fn(mixed $item): bool => $item !== null && $item !== []);

        $config = Hash::insert($config, $key, $value);

        ksort($config);

        $encoded = json_encode($config, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

        if ($encoded === false) {
            return;
        }

        file_put_contents($this->filePath(), $encoded . PHP_EOL);
    }

    /**
     * Return decoded ignis.json contents.
     *
     * @return array<string, mixed>
     */
    protected function all(): array
    {
        $path = $this->filePath();

        if (!is_file($path)) {
            return [];
        }

        $contents = file_get_contents($path);

        if ($contents === false) {
            return [];
        }

        $config = json_decode($contents, true);

        if (json_last_error() !== JSON_ERROR_NONE || !is_array($config)) {
            return [];
        }

        return $config;
    }

    /**
     * Resolve ignis.json path for the current project root.
     *
     * @return string
     */
    protected function filePath(): string
    {
        return ProjectRoot::path() . DS . self::FILE;
    }
}
