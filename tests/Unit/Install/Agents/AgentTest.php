<?php

declare(strict_types=1);

namespace Tests\Unit\Install\Agents;

use Cake\Core\Configure;
use Crustum\Ignis\Contracts\SupportsGuidelines;
use Crustum\Ignis\Contracts\SupportsMcp;
use Crustum\Ignis\Install\Agents\Agent;
use Crustum\Ignis\Install\Contracts\DetectionStrategy;
use Crustum\Ignis\Install\Detection\DetectionStrategyFactory;
use Crustum\Ignis\Install\Enums\McpInstallationStrategy;
use Crustum\Ignis\Install\Enums\Platform;
use Mockery;

beforeEach(function (): void {
    $this->strategyFactory = Mockery::mock(DetectionStrategyFactory::class);
    $this->strategy = Mockery::mock(DetectionStrategy::class);
});

afterEach(function (): void {
    Configure::delete('Ignis.executable_paths.php');
    Mockery::close();
});

class TestAgent extends Agent
{
    public function name(): string
    {
        return 'test';
    }

    public function displayName(): string
    {
        return 'Test Environment';
    }

    public function systemDetectionConfig(Platform $platform): array
    {
        return ['paths' => ['/test/path']];
    }

    public function projectDetectionConfig(): array
    {
        return ['files' => ['test.config']];
    }

    public function testNormalizeCommand(string $command, array $args = []): array
    {
        return $this->normalizeCommand($command, $args);
    }
}

class TestAgentGuidelines extends TestAgent implements SupportsGuidelines
{
    public function guidelinesPath(): string
    {
        return 'test-guidelines.md';
    }
}

class TestSupportsMcp extends TestAgent implements SupportsMcp
{
    public function __construct(
        DetectionStrategyFactory $strategyFactory,
        private readonly string $configPath,
    ) {
        parent::__construct($strategyFactory);
    }

    public function mcpConfigPath(): string
    {
        return $this->configPath;
    }
}

test('detectOnSystem delegates to strategy factory and detection strategy', function (): void {
    $platform = Platform::Darwin;
    $config = ['paths' => ['/test/path']];

    $this->strategyFactory
        ->shouldReceive('makeFromConfig')
        ->once()
        ->with($config)
        ->andReturn($this->strategy);

    $this->strategy
        ->shouldReceive('detect')
        ->once()
        ->with($config, $platform)
        ->andReturn(true);

    $environment = new TestAgent($this->strategyFactory);
    $result = $environment->detectOnSystem($platform);

    expect($result)->toBe(true);
});

test('detectInProject merges config with basePath and delegates to strategy', function (): void {
    $basePath = '/project/path';
    $mergedConfig = ['files' => ['test.config'], 'basePath' => $basePath];

    $this->strategyFactory
        ->shouldReceive('makeFromConfig')
        ->once()
        ->with($mergedConfig)
        ->andReturn($this->strategy);

    $this->strategy
        ->shouldReceive('detect')
        ->once()
        ->with($mergedConfig)
        ->andReturn(false);

    $environment = new TestAgent($this->strategyFactory);
    $result = $environment->detectInProject($basePath);

    expect($result)->toBe(false);
});

test('installMcp uses Shell strategy when configured', function (): void {
    $environment = Mockery::mock(TestAgent::class, [$this->strategyFactory])->makePartial();
    $environment->shouldAllowMockingProtectedMethods();

    $environment->shouldReceive('mcpInstallationStrategy')
        ->andReturn(McpInstallationStrategy::SHELL);

    $environment->shouldReceive('installShellMcp')
        ->once()
        ->with('test-key', 'test-command', ['arg1'], ['ENV' => 'value'])
        ->andReturn(true);

    $result = $environment->installMcp('test-key', 'test-command', ['arg1'], ['ENV' => 'value']);

    expect($result)->toBe(true);
});

test('installMcp uses File strategy when configured', function (): void {
    $environment = Mockery::mock(TestAgent::class, [$this->strategyFactory])->makePartial();
    $environment->shouldAllowMockingProtectedMethods();

    $environment->shouldReceive('mcpInstallationStrategy')
        ->andReturn(McpInstallationStrategy::FILE);

    $environment->shouldReceive('installFileMcp')
        ->once()
        ->with('test-key', 'test-command', ['arg1'], ['ENV' => 'value'])
        ->andReturn(true);

    $result = $environment->installMcp('test-key', 'test-command', ['arg1'], ['ENV' => 'value']);

    expect($result)->toBe(true);
});

test('installMcp returns false for None strategy', function (): void {
    $environment = Mockery::mock(TestAgent::class, [$this->strategyFactory])->makePartial();

    $environment->shouldReceive('mcpInstallationStrategy')
        ->andReturn(McpInstallationStrategy::NONE);

    $result = $environment->installMcp('test-key', 'test-command');

    expect($result)->toBe(false);
});

test('installShellMcp returns false when shellMcpCommand is null', function (): void {
    $environment = Mockery::mock(TestAgent::class, [$this->strategyFactory])->makePartial();

    $environment->shouldReceive('mcpInstallationStrategy')
        ->andReturn(McpInstallationStrategy::SHELL);

    $environment->shouldReceive('shellMcpCommand')
        ->andReturn(null);

    $result = $environment->installMcp('test-key', 'test-command');

    expect($result)->toBe(false);
});

test('installShellMcp executes command with placeholders replaced', function (): void {
    $environment = Mockery::mock(TestAgent::class, [$this->strategyFactory])->makePartial();
    $environment->shouldAllowMockingProtectedMethods();

    $environment->shouldReceive('shellMcpCommand')
        ->andReturn('install {key} {command} {args} {env}');

    $environment->shouldReceive('mcpInstallationStrategy')
        ->andReturn(McpInstallationStrategy::SHELL);

    $environment->shouldReceive('runShellCommand')
        ->once()
        ->with(Mockery::on(fn (string $command): bool => str_contains($command, 'install test-key test-command "arg1" "arg2"')
            && str_contains($command, '-e ENV1="value1"')
            && str_contains($command, '-e ENV2="value2"')))
        ->andReturn(['success' => true, 'errorOutput' => '']);

    $result = $environment->installMcp('test-key', 'test-command', ['arg1', 'arg2'], ['env1' => 'value1', 'env2' => 'value2']);

    expect($result)->toBe(true);
});

test('installShellMcp returns true when process fails but has already exists error', function (): void {
    $environment = Mockery::mock(TestAgent::class, [$this->strategyFactory])->makePartial();
    $environment->shouldAllowMockingProtectedMethods();

    $environment->shouldReceive('shellMcpCommand')
        ->andReturn('install {key}');

    $environment->shouldReceive('mcpInstallationStrategy')
        ->andReturn(McpInstallationStrategy::SHELL);

    $environment->shouldReceive('runShellCommand')
        ->once()
        ->andReturn(['success' => false, 'errorOutput' => 'Error: already exists']);

    $result = $environment->installMcp('test-key', 'test-command');

    expect($result)->toBe(true);
});

test('installFileMcp returns false when mcpConfigPath is null', function (): void {
    $environment = new TestAgent($this->strategyFactory);

    $result = $environment->installMcp('test-key', 'test-command');

    expect($result)->toBe(false);
});

test('installFileMcp creates new config file when none exists', function (): void {
    $path = tempAgentConfigPath('mcp.json');
    $environment = new TestSupportsMcp($this->strategyFactory, $path);

    $result = $environment->installMcp('test-key', 'test-command', ['arg1'], ['ENV' => 'value']);

    expect($result)->toBeTrue()
        ->and(is_file($path))->toBeTrue();

    $decoded = json_decode(mcpFileContents($path), true);

    expect($decoded)->toMatchArray([
        'mcpServers' => [
            'test-key' => [
                'command' => 'test-command',
                'args' => ['arg1'],
                'env' => ['ENV' => 'value'],
            ],
        ],
    ]);
});

test('installFileMcp updates existing config file', function (): void {
    $path = tempAgentConfigPath('mcp.json');
    file_put_contents($path, json_encode(['mcpServers' => ['existing' => ['command' => 'existing-cmd']]]));

    $environment = new TestSupportsMcp($this->strategyFactory, $path);
    $result = $environment->installMcp('test-key', 'test-command', ['arg1'], ['ENV' => 'value']);

    expect($result)->toBeTrue();

    $decoded = json_decode(mcpFileContents($path), true);

    expect($decoded)->toMatchArray([
        'mcpServers' => [
            'existing' => [
                'command' => 'existing-cmd',
            ],
            'test-key' => [
                'command' => 'test-command',
                'args' => ['arg1'],
                'env' => ['ENV' => 'value'],
            ],
        ],
    ]);
});

test('getPhpPath uses absolute paths when forceAbsolutePath is true and config is empty', function (): void {
    Configure::delete('Ignis.executable_paths.php');
    $environment = new TestAgent($this->strategyFactory);
    expect($environment->getPhpPath(true))->toBe(PHP_BINARY);
});

test('getPhpPath maintains default behavior when forceAbsolutePath is false and config is empty', function (): void {
    Configure::delete('Ignis.executable_paths.php');
    $environment = new TestAgent($this->strategyFactory);
    expect($environment->getPhpPath(false))->toBe('php');
});

test('getPhpPath uses configured php path from config', function (): void {
    Configure::write('Ignis.executable_paths.php', '/usr/local/bin/php8.3');

    $environment = new TestAgent($this->strategyFactory);
    expect($environment->getPhpPath(false))->toBe('/usr/local/bin/php8.3');
});

test('getPhpPath returns php when config is set to php', function (): void {
    Configure::write('Ignis.executable_paths.php', 'php');

    $environment = new TestAgent($this->strategyFactory);
    expect($environment->getPhpPath(false))->toBe('php');
});

test('getPhpPath uses config even when forceAbsolutePath is true', function (): void {
    Configure::write('Ignis.executable_paths.php', '/usr/local/bin/php8.3');

    $environment = new TestAgent($this->strategyFactory);
    expect($environment->getPhpPath(true))->toBe('/usr/local/bin/php8.3');
});

test('preserves simple commands without normalisation', function (): void {
    $environment = new TestAgent($this->strategyFactory);

    $result = $environment->testNormalizeCommand('php', ['bin/cake.php', 'ignis', 'mcp']);

    expect($result)->toBe([
        'command' => 'php',
        'args' => ['bin/cake.php', 'ignis', 'mcp'],
    ]);
});

test('splits valet php into command and arguments', function (): void {
    $environment = new TestAgent($this->strategyFactory);

    $result = $environment->testNormalizeCommand('valet php', ['bin/cake.php', 'ignis', 'mcp']);

    expect($result)->toBe([
        'command' => 'valet',
        'args' => ['php', 'bin/cake.php', 'ignis', 'mcp'],
    ]);
});

test('splits herd php into command and arguments', function (): void {
    $environment = new TestAgent($this->strategyFactory);

    $result = $environment->testNormalizeCommand('herd php', ['bin/cake.php', 'ignis', 'mcp']);

    expect($result)->toBe([
        'command' => 'herd',
        'args' => ['php', 'bin/cake.php', 'ignis', 'mcp'],
    ]);
});

test('splits docker exec commands into parts', function (): void {
    $environment = new TestAgent($this->strategyFactory);

    $result = $environment->testNormalizeCommand('docker exec container php', ['bin/cake.php']);

    expect($result)->toBe([
        'command' => 'docker',
        'args' => ['exec', 'container', 'php', 'bin/cake.php'],
    ]);
});

test('splits commands even without additional arguments', function (): void {
    $environment = new TestAgent($this->strategyFactory);

    $result = $environment->testNormalizeCommand('valet php');

    expect($result)->toBe([
        'command' => 'valet',
        'args' => ['php'],
    ]);
});

test('preserves single commands without arguments', function (): void {
    $environment = new TestAgent($this->strategyFactory);

    $result = $environment->testNormalizeCommand('php');

    expect($result)->toBe([
        'command' => 'php',
        'args' => [],
    ]);
});

test('shell installation handles valet php commands', function (): void {
    $environment = Mockery::mock(TestAgent::class, [$this->strategyFactory])->makePartial();
    $environment->shouldAllowMockingProtectedMethods();

    $environment->shouldReceive('shellMcpCommand')
        ->andReturn('install {key} {command} {args}');

    $environment->shouldReceive('mcpInstallationStrategy')
        ->andReturn(McpInstallationStrategy::SHELL);

    $environment->shouldReceive('runShellCommand')
        ->once()
        ->with(Mockery::on(fn (string $command): bool => str_contains($command, 'install test-key valet')
            && str_contains($command, '"php"')
            && str_contains($command, '"bin/cake.php"')))
        ->andReturn(['success' => true, 'errorOutput' => '']);

    $result = $environment->installMcp('test-key', 'valet php', ['bin/cake.php', 'ignis', 'mcp']);

    expect($result)->toBe(true);
});

test('shell installation handles herd php commands', function (): void {
    $environment = Mockery::mock(TestAgent::class, [$this->strategyFactory])->makePartial();
    $environment->shouldAllowMockingProtectedMethods();

    $environment->shouldReceive('shellMcpCommand')
        ->andReturn('install {key} {command} {args}');

    $environment->shouldReceive('mcpInstallationStrategy')
        ->andReturn(McpInstallationStrategy::SHELL);

    $environment->shouldReceive('runShellCommand')
        ->once()
        ->with(Mockery::on(fn (string $command): bool => str_contains($command, 'install test-key herd')
            && str_contains($command, '"php"')
            && str_contains($command, '"bin/cake.php"')))
        ->andReturn(['success' => true, 'errorOutput' => '']);

    $result = $environment->installMcp('test-key', 'herd php', ['bin/cake.php', 'ignis', 'mcp']);

    expect($result)->toBe(true);
});

test('file installation handles valet php commands', function (): void {
    $path = tempAgentConfigPath('mcp.json');
    $environment = new TestSupportsMcp($this->strategyFactory, $path);

    $result = $environment->installMcp('test-key', 'valet php', ['bin/cake.php', 'ignis', 'mcp']);

    expect($result)->toBeTrue();

    $decoded = json_decode(mcpFileContents($path), true);

    expect($decoded)->toMatchArray([
        'mcpServers' => [
            'test-key' => [
                'command' => 'valet',
                'args' => ['php', 'bin/cake.php', 'ignis', 'mcp'],
            ],
        ],
    ]);
});

test('file installation handles herd php commands', function (): void {
    $path = tempAgentConfigPath('mcp.json');
    $environment = new TestSupportsMcp($this->strategyFactory, $path);

    $result = $environment->installMcp('test-key', 'herd php', ['bin/cake.php', 'ignis', 'mcp']);

    expect($result)->toBeTrue();

    $decoded = json_decode(mcpFileContents($path), true);

    expect($decoded)->toMatchArray([
        'mcpServers' => [
            'test-key' => [
                'command' => 'herd',
                'args' => ['php', 'bin/cake.php', 'ignis', 'mcp'],
            ],
        ],
    ]);
});

test('file installation handles docker exec commands', function (): void {
    $path = tempAgentConfigPath('mcp.json');
    $environment = new TestSupportsMcp($this->strategyFactory, $path);

    $result = $environment->installMcp('test-key', 'docker exec container php', ['bin/cake.php', 'ignis', 'mcp']);

    expect($result)->toBeTrue();

    $decoded = json_decode(mcpFileContents($path), true);

    expect($decoded)->toMatchArray([
        'mcpServers' => [
            'test-key' => [
                'command' => 'docker',
                'args' => ['exec', 'container', 'php', 'bin/cake.php', 'ignis', 'mcp'],
            ],
        ],
    ]);
});

test('preserves absolute unix paths with spaces without splitting', function (): void {
    $environment = new TestAgent($this->strategyFactory);

    $result = $environment->testNormalizeCommand(
        '/Users/dev/Library/Application Support/Herd/bin/php83',
        ['bin/cake.php', 'ignis', 'mcp'],
    );

    expect($result)->toBe([
        'command' => '/Users/dev/Library/Application Support/Herd/bin/php83',
        'args' => ['bin/cake.php', 'ignis', 'mcp'],
    ]);
});

test('preserves absolute unix paths without spaces without splitting', function (): void {
    $environment = new TestAgent($this->strategyFactory);

    $result = $environment->testNormalizeCommand(
        '/usr/local/bin/php',
        ['bin/cake.php', 'ignis', 'mcp'],
    );

    expect($result)->toBe([
        'command' => '/usr/local/bin/php',
        'args' => ['bin/cake.php', 'ignis', 'mcp'],
    ]);
});

test('preserves absolute windows paths with spaces without splitting', function (): void {
    $environment = new TestAgent($this->strategyFactory);

    $result = $environment->testNormalizeCommand(
        'C:\\Program Files\\PHP\\php.exe',
        ['bin/cake.php', 'ignis', 'mcp'],
    );

    expect($result)->toBe([
        'command' => 'C:\\Program Files\\PHP\\php.exe',
        'args' => ['bin/cake.php', 'ignis', 'mcp'],
    ]);
});

test('file installation handles absolute paths with spaces correctly', function (): void {
    $path = tempAgentConfigPath('mcp.json');
    $environment = new TestSupportsMcp($this->strategyFactory, $path);

    $result = $environment->installMcp(
        'test-key',
        '/Users/dev/Library/Application Support/Herd/bin/php83',
        ['bin/cake.php', 'ignis', 'mcp'],
    );

    expect($result)->toBeTrue();

    $decoded = json_decode(mcpFileContents($path), true);

    expect($decoded)->toMatchArray([
        'mcpServers' => [
            'test-key' => [
                'command' => '/Users/dev/Library/Application Support/Herd/bin/php83',
                'args' => ['bin/cake.php', 'ignis', 'mcp'],
            ],
        ],
    ]);
});

test('httpMcpServerConfig returns default http type config', function (): void {
    $environment = new TestSupportsMcp($this->strategyFactory, tempAgentConfigPath('mcp.json'));

    expect($environment->httpMcpServerConfig('https://example.com/mcp'))->toBe([
        'type' => 'http',
        'url' => 'https://example.com/mcp',
    ]);
});

test('installHttpMcp creates config file with http server', function (): void {
    $path = tempAgentConfigPath('mcp.json');
    $environment = new TestSupportsMcp($this->strategyFactory, $path);

    $result = $environment->installHttpMcp('example', 'https://example.com/mcp');

    expect($result)->toBeTrue();

    $decoded = json_decode(mcpFileContents($path), true);

    expect($decoded)->toMatchArray([
        'mcpServers' => [
            'example' => [
                'type' => 'http',
                'url' => 'https://example.com/mcp',
            ],
        ],
    ]);
});

test('installHttpMcp returns false when mcpConfigPath is null', function (): void {
    $environment = new TestAgent($this->strategyFactory);

    $result = $environment->installHttpMcp('example', 'https://example.com/mcp');

    expect($result)->toBe(false);
});
