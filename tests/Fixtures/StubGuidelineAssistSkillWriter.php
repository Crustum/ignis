<?php
declare(strict_types=1);

namespace Crustum\Ignis\Test\Fixtures;

use Crustum\Ignis\Contracts\SupportsSkills;
use Crustum\Ignis\Install\GuidelineAssist;
use Crustum\Ignis\Install\SkillWriter;
use Override;

/**
 * SkillWriter test double that uses a fixed GuidelineAssist for Twig rendering.
 */
class StubGuidelineAssistSkillWriter extends SkillWriter
{
    /**
     * @param \Crustum\Ignis\Contracts\SupportsSkills $agent Target agent
     * @param \Crustum\Ignis\Install\GuidelineAssist $assist Guideline assist
     */
    public function __construct(SupportsSkills $agent, private GuidelineAssist $assist)
    {
        parent::__construct($agent);
    }

    /**
     * @inheritDoc
     */
    #[Override]
    protected function getGuidelineAssist(): GuidelineAssist
    {
        return $this->assist;
    }
}
