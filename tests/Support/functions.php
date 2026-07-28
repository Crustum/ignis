<?php
declare(strict_types=1);

use Crustum\Ignis\Install\Detection\DetectionStrategyFactory;
use Crustum\Ignis\Support\DirectoryLink;
use Crustum\Ignis\Support\Filesystem;
use Crustum\Ignis\Support\ProjectRoot;

/**
 * Resolve a path under tests/.
 *
 * @param string $path Relative path
 * @return string
 */
function testDirectory(string $path): string
{
    return dirname(__DIR__) . DIRECTORY_SEPARATOR . ltrim(str_replace('/', DIRECTORY_SEPARATOR, $path), DIRECTORY_SEPARATOR);
}

/**
 * Resolve the isolated fake application root used by Support tests.
 *
 * @param string $path Optional relative path
 * @return string
 */
function testAppPath(string $path = ''): string
{
    if ($path === '') {
        return testDirectory('TestAppProject');
    }

    return testAppPath() . DIRECTORY_SEPARATOR . ltrim(str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $path), DIRECTORY_SEPARATOR);
}

/**
 * Resolve a path under the isolated test application tmp sandbox.
 *
 * @param string $path Optional relative path under tmp
 * @return string
 */
function testAppTmpPath(string $path = ''): string
{
    if ($path === '') {
        return testAppPath('tmp');
    }

    return testAppTmpPath() . DIRECTORY_SEPARATOR . ltrim(str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $path), DIRECTORY_SEPARATOR);
}

/**
 * Resolve a path relative to the active project root.
 *
 * @param string $path Optional relative path
 * @return string
 */
function base_path(string $path = ''): string
{
    if ($path === '') {
        return ProjectRoot::path();
    }

    return ProjectRoot::path() . DIRECTORY_SEPARATOR . ltrim(str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $path), DIRECTORY_SEPARATOR);
}

/**
 * Resolve a path under the plugin source tree.
 *
 * @param string $path Relative path from plugin root
 * @return string
 */
function pluginSourceFile(string $path): string
{
    return ROOT . DIRECTORY_SEPARATOR . ltrim(str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $path), DIRECTORY_SEPARATOR);
}

/**
 * Point support helpers at the isolated test application root.
 *
 * @return void
 */
function useTestApp(): void
{
    ensureDirectoryExists(testAppPath());
    ensureDirectoryExists(testAppTmpPath());
    ProjectRoot::set(testAppPath());
}

/**
 * Reset the test application sandbox and project root override.
 *
 * @return void
 */
function resetTestApp(): void
{
    ProjectRoot::set(null);
    sweepLeakedTestJunctionDirectories();

    $path = testAppPath();

    if (is_dir($path)) {
        deleteDirectory($path);
    }

    ensureDirectoryExists($path);
}

/**
 * Remove Windows junction debris left directly under tests/.
 *
 * @return void
 */
function sweepLeakedTestJunctionDirectories(): void
{
    $testsDir = testDirectory('');
    $protectedEntries = [
        'Feature',
        'Fixtures',
        'Support',
        'TestApp',
        'TestAppProject',
        'TestCase',
        'Unit',
    ];

    foreach (scandir($testsDir) ?: [] as $entry) {
        if ($entry === '.') {
            continue;
        }

        if ($entry === '..') {
            continue;
        }

        if (in_array($entry, $protectedEntries, true)) {
            continue;
        }

        if (!str_starts_with($entry, '.!!') && !str_starts_with($entry, '.ignis-')) {
            continue;
        }

        $path = $testsDir . DIRECTORY_SEPARATOR . $entry;

        if (!is_dir($path) && !DirectoryLink::isLink($path)) {
            continue;
        }

        removeSandboxLinksBeforeDelete($path);
        Filesystem::removeTree($path);
    }
}

/**
 * Remove directory links before deleting a sandbox tree on Windows.
 *
 * @param string $path Directory path
 * @return void
 */
function removeSandboxLinksBeforeDelete(string $path): void
{
    if (DirectoryLink::isLink($path)) {
        DirectoryLink::remove($path);

        return;
    }

    if (!is_dir($path)) {
        return;
    }

    foreach (scandir($path) ?: [] as $entry) {
        if ($entry === '.') {
            continue;
        }

        if ($entry === '..') {
            continue;
        }

        removeSandboxLinksBeforeDelete($path . DIRECTORY_SEPARATOR . $entry);
    }
}

/**
 * Build a detection strategy factory backed by a fresh Cake container.
 *
 * @return \Crustum\Ignis\Install\Detection\DetectionStrategyFactory
 */
function detectionStrategyFactory(): DetectionStrategyFactory
{
    $container = freshTestContainer();
    registerDetectionStrategies($container);

    return new DetectionStrategyFactory($container);
}

/**
 * Prepare a temporary MCP configuration file for writer tests.
 *
 * @param bool $exists Whether the file should exist before the test
 * @param string $content Initial file contents
 * @param string $extension File extension including dot
 * @return string
 */
function prepareMcpFile(bool $exists = false, string $content = '{}', string $extension = '.json'): string
{
    $directory = testAppTmpPath('mcp');
    ensureDirectoryExists($directory);
    $path = $directory . DS . 'file_' . uniqid('', true) . $extension;

    if ($exists) {
        file_put_contents($path, $content);
    }

    return $path;
}

/**
 * Read an MCP test file after save().
 *
 * @param string $path File path
 * @return string
 */
function mcpFileContents(string $path): string
{
    if (!is_file($path)) {
        return '';
    }

    $contents = file_get_contents($path);

    return is_string($contents) ? $contents : '';
}

/**
 * Build a temporary agent MCP configuration path under the test tmp sandbox.
 *
 * @param string $filename Configuration filename
 * @return string
 */
function tempAgentConfigPath(string $filename): string
{
    $directory = testAppTmpPath('agents' . DS . uniqid('', true));
    ensureDirectoryExists($directory);

    return $directory . DS . $filename;
}

/**
 * Legacy hook retained for Pest afterEach wiring; resetTestApp() removes TestAppProject/tmp.
 *
 * @return void
 */
function resetInstallTestSandbox(): void
{
}

/**
 * Resolve a path under the SkillWriter test application sandbox.
 *
 * @param string $path Optional relative path
 * @return string
 */
function skillTestAppPath(string $path = ''): string
{
    return testAppPath($path);
}

/**
 * Resolve a path relative to the active SkillWriter project root.
 *
 * @param string $path Optional relative path
 * @return string
 */
function skillRootPath(string $path = ''): string
{
    return base_path($path);
}

/**
 * Recursively remove a skill test directory or symlink inside allowed sandboxes.
 *
 * @param string $path Directory path
 * @return void
 */
function cleanupSkillDirectory(string $path): void
{
    if (DirectoryLink::isLink($path)) {
        DirectoryLink::remove($path);

        return;
    }

    if (!is_dir($path)) {
        return;
    }

    removeSandboxLinksBeforeDelete($path);
    deleteDirectory($path);
}

/**
 * Create a directory and parents when missing.
 *
 * @param string $path Directory path
 * @return void
 */
function ensureDirectoryExists(string $path): void
{
    if (!is_dir($path)) {
        mkdir($path, 0777, true);
    }
}

/**
 * Assert a delete target is outside protected plugin paths.
 *
 * @param string $path Directory path
 * @return void
 */
function assertDeletePathIsAllowed(string $path): void
{
    $realPath = realpath($path);
    $normalizedTarget = strtolower(str_replace('\\', '/', $realPath ?: $path));

    $protectedCandidates = [
        ROOT . DIRECTORY_SEPARATOR . 'vendor',
        ROOT . DIRECTORY_SEPARATOR . 'node_modules',
    ];

    foreach ($protectedCandidates as $protectedPath) {
        $protectedReal = realpath($protectedPath);
        $normalizedProtected = strtolower(str_replace('\\', '/', $protectedReal ?: $protectedPath));

        if ($normalizedTarget === $normalizedProtected || str_starts_with($normalizedTarget, $normalizedProtected . '/')) {
            throw new InvalidArgumentException(
                'deleteDirectory() refused: attempted to delete protected plugin path: ' . $path,
            );
        }
    }

    $testAppReal = realpath(testAppPath());
    $tmpReal = defined('TMP') ? realpath(TMP) : false;
    $allowed = false;

    if ($testAppReal !== false) {
        $normalizedTestApp = strtolower(str_replace('\\', '/', $testAppReal));

        if ($normalizedTarget === $normalizedTestApp || str_starts_with($normalizedTarget, $normalizedTestApp . '/')) {
            $allowed = true;
        }
    }

    if (!$allowed) {
        $normalizedTestAppFallback = strtolower(str_replace('\\', '/', testAppPath()));

        if ($normalizedTarget === $normalizedTestAppFallback || str_starts_with($normalizedTarget, $normalizedTestAppFallback . '/')) {
            $allowed = true;
        }
    }

    if (!$allowed && $tmpReal !== false) {
        $normalizedTmp = strtolower(str_replace('\\', '/', $tmpReal));

        if ($normalizedTarget === $normalizedTmp || str_starts_with($normalizedTarget, $normalizedTmp . '/')) {
            $allowed = true;
        }
    }

    $testsDirectoryReal = realpath(testDirectory(''));

    if (!$allowed && $testsDirectoryReal !== false) {
        $normalizedTests = strtolower(str_replace('\\', '/', $testsDirectoryReal));

        if ($normalizedTarget === $normalizedTests || str_starts_with($normalizedTarget, $normalizedTests . '/')) {
            $allowed = true;
        }
    }

    if (!$allowed) {
        throw new InvalidArgumentException(
            'deleteDirectory() refused: path is outside allowed test sandbox: ' . $path,
        );
    }
}

/**
 * Delete a directory recursively when present inside an allowed sandbox.
 *
 * @param string $path Directory path
 * @return void
 */
function deleteDirectory(string $path): void
{
    assertDeletePathIsAllowed($path);

    if (DirectoryLink::isLink($path)) {
        DirectoryLink::remove($path);

        return;
    }

    if (is_dir($path)) {
        removeSandboxLinksBeforeDelete($path);
    }

    Filesystem::removeTree($path);
}
