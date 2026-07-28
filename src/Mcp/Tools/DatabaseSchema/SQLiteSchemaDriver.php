<?php
declare(strict_types=1);

namespace Crustum\Ignis\Mcp\Tools\DatabaseSchema;

/**
 * Schema driver for SQLite.
 */
class SQLiteSchemaDriver extends DatabaseSchemaDriver
{
    /** @return array<int, array<string, mixed>> */
    public function getViews(): array
    {
        return $this->query("SELECT name, sql FROM sqlite_master WHERE type = 'view'");
    }

    /** @return array<int, array<string, mixed>> */
    public function getStoredProcedures(): array
    {
        return [];
    }

    /** @return array<int, array<string, mixed>> */
    public function getFunctions(): array
    {
        return [];
    }

    /** @param string|null $table Table name @return array<int, array<string, mixed>> */
    public function getTriggers(?string $table = null): array
    {
        return $table === null ? $this->query("SELECT name, sql FROM sqlite_master WHERE type = 'trigger'") : $this->query("SELECT name, sql FROM sqlite_master WHERE type = 'trigger' AND tbl_name = ?", [$table]);
    }

    /** @param string $table Table name @return array<int, array<string, mixed>> */
    public function getCheckConstraints(string $table): array
    {
        return [];
    }

    /** @return array<int, array<string, mixed>> */
    public function getSequences(): array
    {
        return [];
    }

    /** @return array<int, array<string, mixed>> */
    public function getTables(): array
    {
        return $this->query("SELECT name FROM sqlite_master WHERE type = 'table' AND name NOT LIKE 'sqlite_%' ORDER BY name");
    }
}
