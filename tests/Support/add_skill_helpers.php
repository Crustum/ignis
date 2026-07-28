<?php
declare(strict_types=1);

use Cake\Console\ConsoleIo;
use Crustum\Ignis\Command\AddSkillCommand;
use Crustum\Ignis\Skills\Remote\GitHubRepository;

if (!function_exists('prepareAddSkillTestProject')) {
    /**
     * Prepare the isolated project root and clear any installed skills.
     *
     * @return void
     */
    function prepareAddSkillTestProject(): void
    {
        prepareConsoleProjectRoot();

        $skillsDirectory = testAppPath('.ai/skills');

        if (is_dir($skillsDirectory)) {
            deleteDirectory($skillsDirectory);
        }
    }
}

if (!function_exists('skillOneYaml')) {
    /**
     * Build sample SKILL.md content for skill-one.
     *
     * @return string
     */
    function skillOneYaml(): string
    {
        return <<<'YAML'
---
name: skill-one
description: First skill
---
# SKILL Content
YAML;
    }
}

if (!function_exists('skillTwoYaml')) {
    /**
     * Build sample SKILL.md content for skill-two.
     *
     * @return string
     */
    function skillTwoYaml(): string
    {
        return <<<'YAML'
---
name: skill-two
description: Second skill
---
# SKILL Content
YAML;
    }
}

if (!function_exists('assertSkillFilenameExists')) {
    /**
     * Assert that a project-relative file exists.
     *
     * @param string $relativePath Project-relative path
     * @return void
     */
    function assertSkillFilenameExists(string $relativePath): void
    {
        expect(base_path($relativePath))->toBeFile();
    }
}

if (!function_exists('assertSkillFilenameNotExists')) {
    /**
     * Assert that a project-relative file does not exist.
     *
     * @param string $relativePath Project-relative path
     * @return void
     */
    function assertSkillFilenameNotExists(string $relativePath): void
    {
        expect(is_file(base_path($relativePath)))->toBeFalse();
    }
}

if (!function_exists('assertSkillFileContains')) {
    /**
     * Assert that a project-relative file contains each needle.
     *
     * @param array<int, string> $needles Strings that must appear in the file
     * @param string $relativePath Project-relative path
     * @return void
     */
    function assertSkillFileContains(array $needles, string $relativePath): void
    {
        $contents = file_get_contents(base_path($relativePath));

        expect($contents)->not->toBeFalse();

        foreach ($needles as $needle) {
            expect($contents)->toContain($needle);
        }
    }
}

if (!function_exists('assertSkillFileNotContains')) {
    /**
     * Assert that a project-relative file does not contain each needle.
     *
     * @param array<int, string> $needles Strings that must not appear in the file
     * @param string $relativePath Project-relative path
     * @return void
     */
    function assertSkillFileNotContains(array $needles, string $relativePath): void
    {
        $contents = file_get_contents(base_path($relativePath));

        expect($contents)->not->toBeFalse();

        foreach ($needles as $needle) {
            expect($contents)->not->toContain($needle);
        }
    }
}

if (!function_exists('writeSkillFile')) {
    /**
     * Write content to a project-relative skill file.
     *
     * @param string $relativePath Project-relative path
     * @param string $contents File contents
     * @return void
     */
    function writeSkillFile(string $relativePath, string $contents): void
    {
        $path = base_path($relativePath);
        ensureDirectoryExists(dirname($path));
        file_put_contents($path, $contents);
    }
}

if (!function_exists('bindAddSkillMocks')) {
    /**
     * Register mocked GitHub and audit HTTP clients for add-skill feature tests.
     *
     * @param object $test Console integration test case
     * @param string $repoInput Repository shorthand passed to add-skill
     * @param array<int, \Cake\Http\Client\Response|\Throwable> $githubResponses Queued GitHub HTTP responses
     * @param array<int, \Cake\Http\Client\Response|\Throwable>|null $auditResponses Queued audit responses, or null to omit auditor injection
     * @param array<int, array{request: \Psr\Http\Message\RequestInterface, response: \Cake\Http\Client\Response|null, options: array<string, mixed>}>|null $githubHistory GitHub request history container
     * @param array<int, array{request: \Psr\Http\Message\RequestInterface, response: \Cake\Http\Client\Response|null, options: array<string, mixed>}>|null $auditHistory Audit request history container
     * @return void
     */
    function bindAddSkillMocks(
        object $test,
        string $repoInput,
        array $githubResponses,
        ?array $auditResponses = [],
        ?array &$githubHistory = null,
        ?array &$auditHistory = null,
    ): void {
        $repository = GitHubRepository::fromInput($repoInput);
        $provider = githubProvider($repository, $githubResponses, $githubHistory);
        $auditor = null;

        if ($auditResponses !== null) {
            $responses = $auditResponses === [] ? [githubJsonResponse(200, [])] : $auditResponses;
            $auditor = skillAuditorWithResponses($responses, $auditHistory);
        }

        $command = new class (null, $provider, $auditor) extends AddSkillCommand {
            protected function runIgnisUpdate(ConsoleIo $io): void
            {
            }
        };

        $test->mockService(AddSkillCommand::class, static fn (): AddSkillCommand => $command);
    }
}

if (!function_exists('auditHistoryContainsUri')) {
    /**
     * Determine whether audit request history contains a URI fragment.
     *
     * @param array<int, array{request: \Psr\Http\Message\RequestInterface, response: \Psr\Http\Message\ResponseInterface, options: array<string, mixed>}> $history Request history
     * @param string $needle URI fragment to find
     * @return bool
     */
    function auditHistoryContainsUri(array $history, string $needle): bool
    {
        return githubHistoryContainsUri($history, $needle);
    }
}
