<?php
declare(strict_types=1);

namespace Crustum\Ignis\Mcp\Tools\DatabaseSchema;

/**
 * Schema driver for MySQL and MariaDB.
 */
class MySQLSchemaDriver extends DatabaseSchemaDriver
{
    /** @return array<int, array<string, mixed>> */
    public function getViews(): array
    {
        return $this->query('SELECT TABLE_NAME AS name, VIEW_DEFINITION AS definition FROM information_schema.VIEWS WHERE TABLE_SCHEMA = DATABASE()');
    }

    /** @return array<int, array<string, mixed>> */
    public function getStoredProcedures(): array
    {
        return $this->query('SHOW PROCEDURE STATUS WHERE Db = DATABASE()');
    }

    /** @return array<int, array<string, mixed>> */
    public function getFunctions(): array
    {
        return $this->query('SHOW FUNCTION STATUS WHERE Db = DATABASE()');
    }

    /** @param string|null $table Table name @return array<int, array<string, mixed>> */
    public function getTriggers(?string $table = null): array
    {
        return $table === null ? $this->query('SHOW TRIGGERS') : $this->query('SHOW TRIGGERS WHERE `Table` = ?', [$table]);
    }

    /** @param string $table Table name @return array<int, array<string, mixed>> */
    public function getCheckConstraints(string $table): array
    {
        $mariaDbConstraints = $this->tryQuery('SELECT CONSTRAINT_NAME, CHECK_CLAUSE FROM information_schema.CHECK_CONSTRAINTS WHERE CONSTRAINT_SCHEMA = DATABASE() AND TABLE_NAME = ?', [$table]);

        if ($mariaDbConstraints !== null) {
            return $mariaDbConstraints;
        }

        return $this->query("SELECT cc.CONSTRAINT_NAME, cc.CHECK_CLAUSE FROM information_schema.CHECK_CONSTRAINTS cc JOIN information_schema.TABLE_CONSTRAINTS tc ON tc.CONSTRAINT_SCHEMA = cc.CONSTRAINT_SCHEMA AND tc.CONSTRAINT_NAME = cc.CONSTRAINT_NAME WHERE cc.CONSTRAINT_SCHEMA = DATABASE() AND tc.TABLE_NAME = ? AND tc.CONSTRAINT_TYPE = 'CHECK'", [$table]);
    }

    /** @return array<int, array<string, mixed>> */
    public function getSequences(): array
    {
        return [];
    }

    /** @return array<int, array<string, mixed>> */
    public function getTables(): array
    {
        return $this->query("SELECT TABLE_NAME AS name FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_TYPE = 'BASE TABLE' ORDER BY TABLE_NAME");
    }
}
