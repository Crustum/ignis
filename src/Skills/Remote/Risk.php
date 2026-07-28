<?php
declare(strict_types=1);

namespace Crustum\Ignis\Skills\Remote;

/**
 * Risk levels reported by a remote skill audit.
 */
enum Risk: string
{
    case Critical = 'critical';
    case High = 'high';
    case Medium = 'medium';
    case Low = 'low';
    case Safe = 'safe';

    /**
     * Get the sortable severity weight.
     *
     * @return int Severity weight
     */
    public function weight(): int
    {
        return match ($this) {
            self::Critical => 5,
            self::High => 4,
            self::Medium => 3,
            self::Low => 2,
            self::Safe => 1,
        };
    }

    /**
     * Get the human-readable risk label.
     *
     * @return string Risk label
     */
    public function label(): string
    {
        return match ($this) {
            self::Critical => 'Critical Risk',
            self::High => 'High Risk',
            self::Medium => 'Med Risk',
            self::Low => 'Low Risk',
            self::Safe => 'Safe',
        };
    }

    /**
     * Get the display color associated with the risk.
     *
     * @return string Color name
     */
    public function color(): string
    {
        return match ($this) {
            self::Critical, self::High => 'red',
            self::Medium => 'yellow',
            self::Low, self::Safe => 'green',
        };
    }
}
