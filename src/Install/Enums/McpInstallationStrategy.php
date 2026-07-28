<?php
declare(strict_types=1);

namespace Crustum\Ignis\Install\Enums;

/**
 * MCP server installation modes for AI coding agents.
 */
enum McpInstallationStrategy: string
{
    case SHELL = 'shell';
    case FILE = 'file';
    case NONE = 'none';
}
