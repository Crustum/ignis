<?php
declare(strict_types=1);

namespace Crustum\Ignis\Skills\Remote;

/**
 * The security audit result reported by a partner for a remote skill.
 */
class AuditResult
{
    /**
     * Create an audit result.
     *
     * @param string $partner Partner that produced the audit
     * @param \Crustum\Ignis\Skills\Remote\Risk $risk Reported risk level
     * @param int|null $alerts Number of reported alerts
     * @param string|null $analyzedAt Timestamp at which the audit ran
     */
    public function __construct(
        public string $partner,
        public Risk $risk,
        public ?int $alerts = null,
        public ?string $analyzedAt = null,
    ) {
    }
}
