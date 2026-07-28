<?php

declare(strict_types=1);

use Cake\Core\Configure;
use Crustum\Ignis\Install\GuidelineAssist;
use Crustum\Ignis\Install\GuidelineConfig;
use Crustum\Inspector\Ecosystems\Ecosystem;
use Crustum\Inspector\Ecosystems\JsEcosystem;
use Crustum\Inspector\PackageCollection;
use Crustum\Inspector\ProjectManager;

beforeEach(function (): void {
    $this->project = Mockery::mock(ProjectManager::class);
    $this->php = Mockery::mock(Ecosystem::class);
    $this->js = Mockery::mock(JsEcosystem::class);
    $this->project->shouldReceive('php')->andReturn($this->php)->byDefault();
    $this->project->shouldReceive('js')->andReturn($this->js)->byDefault();
    $this->js->shouldReceive('packageManager')->andReturn(null)->byDefault();
    $this->js->shouldReceive('packages')->andReturn(new PackageCollection([]))->byDefault();
    $this->php->shouldReceive('packages')->andReturn(new PackageCollection([]))->byDefault();
    $this->php->shouldReceive('uses')->andReturn(false)->byDefault();
    $this->js->shouldReceive('uses')->andReturn(false)->byDefault();

    $this->config = new GuidelineConfig;
});

afterEach(function (): void {
    Configure::delete('Ignis.executable_paths');
});

test('php executable falls back to php bin/cake.php when no config is set', function (): void {
    $assist = Mockery::mock(GuidelineAssist::class, [$this->project, $this->config])->makePartial();
    $assist->shouldAllowMockingProtectedMethods();
    $assist->shouldReceive('discover')->andReturn([]);

    expect($assist->cake())->toBe('php bin/cake.php');
});

test('php executable config takes precedence over default cake command', function (): void {
    Configure::write('Ignis.executable_paths.php', '/usr/local/bin/php8.3');

    $assist = Mockery::mock(GuidelineAssist::class, [$this->project, $this->config])->makePartial();
    $assist->shouldAllowMockingProtectedMethods();
    $assist->shouldReceive('discover')->andReturn([]);

    expect($assist->cake())->toBe('/usr/local/bin/php8.3 bin/cake.php');
});

test('cakeCommand builds a cake console command string', function (): void {
    $assist = Mockery::mock(GuidelineAssist::class, [$this->project, $this->config])->makePartial();
    $assist->shouldAllowMockingProtectedMethods();
    $assist->shouldReceive('discover')->andReturn([]);

    expect($assist->cakeCommand('cache clear'))->toBe('php bin/cake.php cache clear');
});

test('composer executable falls back to composer when no config is set', function (): void {
    $assist = Mockery::mock(GuidelineAssist::class, [$this->project, $this->config])->makePartial();
    $assist->shouldAllowMockingProtectedMethods();
    $assist->shouldReceive('discover')->andReturn([]);

    expect($assist->composerCommand('install'))->toBe('composer install');
});

test('composer executable config takes precedence over default', function (): void {
    Configure::write('Ignis.executable_paths.composer', '/usr/local/bin/composer2');

    $assist = Mockery::mock(GuidelineAssist::class, [$this->project, $this->config])->makePartial();
    $assist->shouldAllowMockingProtectedMethods();
    $assist->shouldReceive('discover')->andReturn([]);

    expect($assist->composerCommand('install'))->toBe('/usr/local/bin/composer2 install');
});

test('npm executable falls back to npm when no config is set', function (): void {
    $assist = Mockery::mock(GuidelineAssist::class, [$this->project, $this->config])->makePartial();
    $assist->shouldAllowMockingProtectedMethods();
    $assist->shouldReceive('discover')->andReturn([]);

    expect($assist->nodePackageManagerCommand('install'))->toBe('npm install');
});

test('npm executable config takes precedence over default', function (): void {
    Configure::write('Ignis.executable_paths.npm', '/usr/local/bin/yarn');

    $assist = Mockery::mock(GuidelineAssist::class, [$this->project, $this->config])->makePartial();
    $assist->shouldAllowMockingProtectedMethods();
    $assist->shouldReceive('discover')->andReturn([]);

    expect($assist->nodePackageManagerCommand('install'))->toBe('/usr/local/bin/yarn install');
});

test('vendor bin prefix falls back to vendor/bin when no config is set', function (): void {
    $assist = Mockery::mock(GuidelineAssist::class, [$this->project, $this->config])->makePartial();
    $assist->shouldAllowMockingProtectedMethods();
    $assist->shouldReceive('discover')->andReturn([]);

    expect($assist->binCommand('pint'))->toBe('vendor/bin/pint');
});

test('vendor bin prefix config takes precedence over default', function (): void {
    Configure::write('Ignis.executable_paths.vendor_bin', '/custom/path/');

    $assist = Mockery::mock(GuidelineAssist::class, [$this->project, $this->config])->makePartial();
    $assist->shouldAllowMockingProtectedMethods();
    $assist->shouldReceive('discover')->andReturn([]);

    expect($assist->binCommand('pint'))->toBe('/custom/path/pint');
});

test('hasSkills property defaults to false', function (): void {
    $config = new GuidelineConfig;

    expect($config->hasSkills)->toBeFalse();
});

test('hasSkills property can be set to true', function (): void {
    $config = new GuidelineConfig;
    $config->hasSkills = true;

    expect($config->hasSkills)->toBeTrue();
});

test('enumContents returns empty string when discovered paths are not files', function (): void {
    $assist = new class($this->project, $this->config) extends GuidelineAssist
    {
        protected function discover(): array
        {
            return [
                'App\Enums\Missing' => sys_get_temp_dir(),
            ];
        }
    };

    expect($assist->enumContents())->toBe('');
});

test('enumContents includes all discovered enum files in stable order', function (): void {
    $assist = new class($this->project, $this->config) extends GuidelineAssist
    {
        protected function discover(): array
        {
            return [
                'App\Enums\FlashKey' => fixture('Enums/FlashKey.php'),
                'App\Enums\CountryCode' => fixture('Enums/CountryCode.php'),
            ];
        }
    };

    $contents = $assist->enumContents();

    expect($contents)
        ->toContain("case USA = 'USA';")
        ->toContain("case Success = 'success';")
        ->and(strpos($contents, 'enum CountryCode'))
        ->toBeLessThan(strpos($contents, 'enum FlashKey'));
});

test('enumContents skips enum paths that are not files', function (): void {
    $assist = new class($this->project, $this->config) extends GuidelineAssist
    {
        protected function discover(): array
        {
            return [
                'App\Enums\Deleted' => fixture('Enums'),
                'App\Enums\FlashKey' => fixture('Enums/FlashKey.php'),
            ];
        }
    };

    expect($assist->enumContents())
        ->toStartWith('<?php')
        ->toContain('enum FlashKey');
});

test('hasSkillsEnabled returns false when skills are disabled', function (): void {
    $this->config->hasSkills = false;

    $assist = Mockery::mock(GuidelineAssist::class, [$this->project, $this->config])->makePartial();
    $assist->shouldAllowMockingProtectedMethods();
    $assist->shouldReceive('discover')->andReturn([]);

    expect($assist->hasSkillsEnabled())->toBeFalse();
});

test('hasSkillsEnabled returns true when skills are enabled', function (): void {
    $this->config->hasSkills = true;

    $assist = Mockery::mock(GuidelineAssist::class, [$this->project, $this->config])->makePartial();
    $assist->shouldAllowMockingProtectedMethods();
    $assist->shouldReceive('discover')->andReturn([]);

    expect($assist->hasSkillsEnabled())->toBeTrue();
});

test('hasMcpEnabled returns false when MCP is disabled', function (): void {
    $this->config->hasMcp = false;

    $assist = Mockery::mock(GuidelineAssist::class, [$this->project, $this->config])->makePartial();
    $assist->shouldAllowMockingProtectedMethods();
    $assist->shouldReceive('discover')->andReturn([]);

    expect($assist->hasMcpEnabled())->toBeFalse();
});

test('hasMcpEnabled returns true when MCP is enabled', function (): void {
    $this->config->hasMcp = true;

    $assist = Mockery::mock(GuidelineAssist::class, [$this->project, $this->config])->makePartial();
    $assist->shouldAllowMockingProtectedMethods();
    $assist->shouldReceive('discover')->andReturn([]);

    expect($assist->hasMcpEnabled())->toBeTrue();
});

test('appPath returns default src path', function (): void {
    $assist = Mockery::mock(GuidelineAssist::class, [$this->project, $this->config])->makePartial();
    $assist->shouldAllowMockingProtectedMethods();
    $assist->shouldReceive('discover')->andReturn([]);

    expect($assist->appPath())->toBe('src');
    expect($assist->appPath('path/to/file.php'))->toBe('src/path/to/file.php');
});

test('appPath normalizes separators to forward slashes', function (): void {
    $assist = Mockery::mock(GuidelineAssist::class, [$this->project, $this->config])->makePartial();
    $assist->shouldAllowMockingProtectedMethods();
    $assist->shouldReceive('discover')->andReturn([]);

    expect($assist->appPath('Http/Kernel.php'))->toBe('src/Http/Kernel.php');
    expect($assist->appPath('Console/Commands/'))->toBe('src/Console/Commands/');
});

test('versionGte checks php ecosystem with >= constraint', function (): void {
    $this->php->shouldReceive('uses')->once()->with('cakephp/cakephp', '>=5.4')->andReturn(true);

    $assist = Mockery::mock(GuidelineAssist::class, [$this->project, $this->config])->makePartial();
    $assist->shouldAllowMockingProtectedMethods();
    $assist->shouldReceive('discover')->andReturn([]);

    expect($assist->versionGte('cakephp/cakephp', '5.4'))->toBeTrue();
});

test('versionGte falls back to js ecosystem', function (): void {
    $this->php->shouldReceive('uses')->once()->with('vue', '>=3.4')->andReturn(false);
    $this->js->shouldReceive('uses')->once()->with('vue', '>=3.4')->andReturn(true);

    $assist = Mockery::mock(GuidelineAssist::class, [$this->project, $this->config])->makePartial();
    $assist->shouldAllowMockingProtectedMethods();
    $assist->shouldReceive('discover')->andReturn([]);

    expect($assist->versionGte('vue', '3.4'))->toBeTrue();
});

test('versionLt checks php ecosystem with < constraint', function (): void {
    $this->php->shouldReceive('uses')->once()->with('cakephp/cakephp', '<5.4')->andReturn(true);

    $assist = Mockery::mock(GuidelineAssist::class, [$this->project, $this->config])->makePartial();
    $assist->shouldAllowMockingProtectedMethods();
    $assist->shouldReceive('discover')->andReturn([]);

    expect($assist->versionLt('cakephp/cakephp', '5.4'))->toBeTrue();
});

test('uses accepts composer semver constraints via inspector', function (): void {
    $this->php->shouldReceive('uses')->once()->with('cakephp/cakephp', '>=5.0 <6')->andReturn(true);

    $assist = Mockery::mock(GuidelineAssist::class, [$this->project, $this->config])->makePartial();
    $assist->shouldAllowMockingProtectedMethods();
    $assist->shouldReceive('discover')->andReturn([]);

    expect($assist->uses('cakephp/cakephp', '>=5.0 <6'))->toBeTrue();
});

test('uses returns false when neither ecosystem matches', function (): void {
    $this->php->shouldReceive('uses')->once()->with('cakephp/cakephp', '>=6.0')->andReturn(false);
    $this->js->shouldReceive('uses')->once()->with('cakephp/cakephp', '>=6.0')->andReturn(false);

    $assist = Mockery::mock(GuidelineAssist::class, [$this->project, $this->config])->makePartial();
    $assist->shouldAllowMockingProtectedMethods();
    $assist->shouldReceive('discover')->andReturn([]);

    expect($assist->uses('cakephp/cakephp', '>=6.0'))->toBeFalse();
});

test('packageVersion returns resolved php package version', function (): void {
    $this->php->shouldReceive('package')->once()->with('cakephp/cakephp')->andReturn(
        inspectorPackage('cakephp/cakephp', '5.4.2'),
    );

    $assist = Mockery::mock(GuidelineAssist::class, [$this->project, $this->config])->makePartial();
    $assist->shouldAllowMockingProtectedMethods();
    $assist->shouldReceive('discover')->andReturn([]);

    expect($assist->packageVersion('cakephp/cakephp'))->toBe('5.4.2');
});

test('packageVersion returns null when package is missing', function (): void {
    $this->php->shouldReceive('package')->once()->with('missing/pkg')->andReturn(null);
    $this->js->shouldReceive('package')->once()->with('missing/pkg')->andReturn(null);

    $assist = Mockery::mock(GuidelineAssist::class, [$this->project, $this->config])->makePartial();
    $assist->shouldAllowMockingProtectedMethods();
    $assist->shouldReceive('discover')->andReturn([]);

    expect($assist->packageVersion('missing/pkg'))->toBeNull();
});

test('inspectorPackages returns only direct dependencies by default', function (): void {
    $direct = inspectorPackage('cakephp/cakephp', '5.4.0')->setDirect(true);
    $transitive = inspectorPackage('doctrine/inflector', '2.0.0')->setDirect(false);
    $this->php->shouldReceive('packages')->andReturn(new PackageCollection([$direct, $transitive]));

    $assist = Mockery::mock(GuidelineAssist::class, [$this->project, $this->config])->makePartial();
    $assist->shouldAllowMockingProtectedMethods();
    $assist->shouldReceive('discover')->andReturn([]);

    expect($assist->listAllDependencies())->toBeFalse()
        ->and($assist->inspectorPackages())->toBe([
            [
                'name' => 'cakephp/cakephp',
                'inspectorName' => 'CAKEPHP',
                'major' => '5',
            ],
        ]);
});

test('inspectorPackages includes transitive packages when dependencies is all', function (): void {
    Configure::write('Ignis.guidelines.dependencies', 'all');

    $direct = inspectorPackage('cakephp/cakephp', '5.4.0')->setDirect(true);
    $transitive = inspectorPackage('doctrine/inflector', '2.0.0')->setDirect(false);
    $this->php->shouldReceive('packages')->andReturn(new PackageCollection([$direct, $transitive]));

    $assist = Mockery::mock(GuidelineAssist::class, [$this->project, $this->config])->makePartial();
    $assist->shouldAllowMockingProtectedMethods();
    $assist->shouldReceive('discover')->andReturn([]);

    expect($assist->listAllDependencies())->toBeTrue()
        ->and($assist->inspectorPackages())->toHaveCount(2)
        ->and($assist->inspectorPackages()[1]['name'])->toBe('doctrine/inflector');

    Configure::delete('Ignis.guidelines.dependencies');
});
