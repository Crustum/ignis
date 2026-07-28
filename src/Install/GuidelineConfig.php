<?php
declare(strict_types=1);

namespace Crustum\Ignis\Install;

/**
 * Configuration flags used while composing project guidelines.
 */
class GuidelineConfig
{
    /**
     * Whether enforce-tests guidelines should be included.
     */
    public bool $enforceTests = false;

    /**
     * Whether CakePHP style guidelines should be included.
     */
    public bool $cakeStyle = false;

    /**
     * Whether localization guidelines should be included.
     */
    public bool $caresAboutLocalization = false;

    /**
     * Whether API guidelines should be included.
     */
    public bool $hasAnApi = false;

    /**
     * Whether skills are enabled for the install run.
     */
    public bool $hasSkills = false;

    /**
     * Whether MCP is enabled for the install run.
     */
    public bool $hasMcp = false;

    /**
     * Optional allow-list of third-party guideline package names.
     *
     * @var array<int, string>|null
     */
    public ?array $aiGuidelines = null;
}
