<?php
declare(strict_types=1);

namespace Crustum\Ignis\Mcp\Tools\DatabaseSchema;

/**
 * Schema driver for PostgreSQL.
 */
class PostgreSQLSchemaDriver extends DatabaseSchemaDriver
{
    /** @return array<int, array<string, mixed>> */
    public function getViews(): array
    {
        return $this->query("SELECT schemaname, viewname, definition FROM pg_views WHERE schemaname NOT IN ('pg_catalog', 'information_schema')");
    }

    /** @return array<int, array<string, mixed>> */
    public function getStoredProcedures(): array
    {
        return $this->query("SELECT proname, prosrc FROM pg_proc p JOIN pg_namespace n ON p.pronamespace = n.oid WHERE n.nspname NOT IN ('pg_catalog', 'information_schema') AND prokind = 'p'");
    }

    /** @return array<int, array<string, mixed>> */
    public function getFunctions(): array
    {
        return $this->query("SELECT proname, prosrc FROM pg_proc p JOIN pg_namespace n ON p.pronamespace = n.oid WHERE n.nspname NOT IN ('pg_catalog', 'information_schema') AND prokind = 'f'");
    }

    /** @param string|null $table Table name @return array<int, array<string, mixed>> */
    public function getTriggers(?string $table = null): array
    {
        return $table === null ? $this->query('SELECT trigger_name, event_manipulation, event_object_table, action_statement FROM information_schema.triggers WHERE trigger_schema = current_schema()') : $this->query('SELECT trigger_name, event_manipulation, event_object_table, action_statement FROM information_schema.triggers WHERE trigger_schema = current_schema() AND event_object_table = ?', [$table]);
    }

    /** @param string $table Table name @return array<int, array<string, mixed>> */
    public function getCheckConstraints(string $table): array
    {
        return $this->query("SELECT conname, pg_get_constraintdef(oid) AS definition FROM pg_constraint WHERE contype = 'c' AND conrelid = ?::regclass", [$table]);
    }

    /** @return array<int, array<string, mixed>> */
    public function getSequences(): array
    {
        return $this->query('SELECT sequence_name, start_value, minimum_value, maximum_value, increment FROM information_schema.sequences WHERE sequence_schema = current_schema()');
    }

    /** @return array<int, array<string, mixed>> */
    public function getTables(): array
    {
        return $this->query('SELECT tablename AS name FROM pg_tables WHERE schemaname = current_schema() ORDER BY tablename');
    }
}
