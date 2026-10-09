<?php

declare(strict_types=1);

use Cake\Core\Configure;
use Crustum\Ignis\Install\GuidelineAssist;
use Crustum\Ignis\Install\GuidelineConfig;
use Crustum\Inspector\Ecosystems\Ecosystem;
use Crustum\Inspector\Ecosystems\JsEcosystem;
use Crustum\Inspector\PackageCollection;
use Crustum\Inspector\ProjectManager;
use JMac\Testing\Double;

beforeEach(function (): void {
    $this->project = Double::for(ProjectManager::class, override: true);
    $this->php = Double::for(Ecosystem::class);
    $this->js = Double::for(JsEcosystem::class);
    $this->project->allows('php')->returns($this->php);
    $this->project->allows('js')->returns($this->js);
    $this->js->allows('packageManager')->returns(null);
    $this->js->allows('packages')->returns(new PackageCollection([]));
    $this->php->allows('packages')->returns(new PackageCollection([]));
    $this->php->allows('uses')->returns(false);
    $this->js->allows('uses')->returns(false);

    $this->config = new GuidelineConfig;
});

afterEach(function (): void {
    Configure::delete('Ignis.executable_paths');
});

test('php executable falls back to php bin/cake.php when no config is set', function (): void {
    $assist = Double::for(new GuidelineAssist($this->project->instance(), $this->config))->passthru();
    $assist->allows('discover')->returns([]);

    expect($assist->cake())->toBe('php bin/cake.php');
});

test('php executable config takes precedence over default cake command', function (): void {
    Configure::write('Ignis.executable_paths.php', '/usr/local/bin/php8.3');

    $assist = Double::for(new GuidelineAssist($this->project->instance(), $this->config))->passthru();
    $assist->allows('discover')->returns([]);

    expect($assist->cake())->toBe('/usr/local/bin/php8.3 bin/cake.php');
});

test('cakeCommand builds a cake console command string', function (): void {
    $assist = Double::for(new GuidelineAssist($this->project->instance(), $this->config))->passthru();
    $assist->allows('discover')->returns([]);

    expect($assist->cakeCommand('cache clear'))->toBe('php bin/cake.php cache clear');
});

test('composer executable falls back to composer when no config is set', function (): void {
    $assist = Double::for(new GuidelineAssist($this->project->instance(), $this->config))->passthru();
    $assist->allows('discover')->returns([]);

    expect($assist->composerCommand('install'))->toBe('composer install');
});

test('composer executable config takes precedence over default', function (): void {
    Configure::write('Ignis.executable_paths.composer', '/usr/local/bin/composer2');

    $assist = Double::for(new GuidelineAssist($this->project->instance(), $this->config))->passthru();
    $assist->allows('discover')->returns([]);

    expect($assist->composerCommand('install'))->toBe('/usr/local/bin/composer2 install');
});

test('npm executable falls back to npm when no config is set', function (): void {
    $assist = Double::for(new GuidelineAssist($this->project->instance(), $this->config))->passthru();
    $assist->allows('discover')->returns([]);

    expect($assist->nodePackageManagerCommand('install'))->toBe('npm install');
});

test('npm executable config takes precedence over default', function (): void {
    Configure::write('Ignis.executable_paths.npm', '/usr/local/bin/yarn');

    $assist = Double::for(new GuidelineAssist($this->project->instance(), $this->config))->passthru();
    $assist->allows('discover')->returns([]);

    expect($assist->nodePackageManagerCommand('install'))->toBe('/usr/local/bin/yarn install');
});

test('vendor bin prefix falls back to vendor/bin when no config is set', function (): void {
    $assist = Double::for(new GuidelineAssist($this->project->instance(), $this->config))->passthru();
    $assist->allows('discover')->returns([]);

    expect($assist->binCommand('pint'))->toBe('vendor/bin/pint');
});

test('vendor bin prefix config takes precedence over default', function (): void {
    Configure::write('Ignis.executable_paths.vendor_bin', '/custom/path/');

    $assist = Double::for(new GuidelineAssist($this->project->instance(), $this->config))->passthru();
    $assist->allows('discover')->returns([]);

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
    $assist = new class($this->project->instance(), $this->config) extends GuidelineAssist
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
    $assist = new class($this->project->instance(), $this->config) extends GuidelineAssist
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
    $assist = new class($this->project->instance(), $this->config) extends GuidelineAssist
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

    $assist = Double::for(new GuidelineAssist($this->project->instance(), $this->config))->passthru();
    $assist->allows('discover')->returns([]);

    expect($assist->hasSkillsEnabled())->toBeFalse();
});

test('hasSkillsEnabled returns true when skills are enabled', function (): void {
    $this->config->hasSkills = true;

    $assist = Double::for(new GuidelineAssist($this->project->instance(), $this->config))->passthru();
    $assist->allows('discover')->returns([]);

    expect($assist->hasSkillsEnabled())->toBeTrue();
});

test('hasMcpEnabled returns false when MCP is disabled', function (): void {
    $this->config->hasMcp = false;

    $assist = Double::for(new GuidelineAssist($this->project->instance(), $this->config))->passthru();
    $assist->allows('discover')->returns([]);

    expect($assist->hasMcpEnabled())->toBeFalse();
});

test('hasMcpEnabled returns true when MCP is enabled', function (): void {
    $this->config->hasMcp = true;

    $assist = Double::for(new GuidelineAssist($this->project->instance(), $this->config))->passthru();
    $assist->allows('discover')->returns([]);

    expect($assist->hasMcpEnabled())->toBeTrue();
});

test('appPath returns default src path', function (): void {
    $assist = Double::for(new GuidelineAssist($this->project->instance(), $this->config))->passthru();
    $assist->allows('discover')->returns([]);

    expect($assist->appPath())->toBe('src');
    expect($assist->appPath('path/to/file.php'))->toBe('src/path/to/file.php');
});

test('appPath normalizes separators to forward slashes', function (): void {
    $assist = Double::for(new GuidelineAssist($this->project->instance(), $this->config))->passthru();
    $assist->allows('discover')->returns([]);

    expect($assist->appPath('Http/Kernel.php'))->toBe('src/Http/Kernel.php');
    expect($assist->appPath('Console/Commands/'))->toBe('src/Console/Commands/');
});

test('versionGte checks php ecosystem with >= constraint', function (): void {
    $this->php->expects('uses')->with('cakephp/cakephp', '>=5.4')->returns(true);

    $assist = Double::for(new GuidelineAssist($this->project->instance(), $this->config))->passthru();
    $assist->allows('discover')->returns([]);

    expect($assist->versionGte('cakephp/cakephp', '5.4'))->toBeTrue();
});

test('versionGte falls back to js ecosystem', function (): void {
    $this->php->expects('uses')->with('vue', '>=3.4')->returns(false);
    $this->js->expects('uses')->with('vue', '>=3.4')->returns(true);

    $assist = Double::for(new GuidelineAssist($this->project->instance(), $this->config))->passthru();
    $assist->allows('discover')->returns([]);

    expect($assist->versionGte('vue', '3.4'))->toBeTrue();
});

test('versionLt checks php ecosystem with < constraint', function (): void {
    $this->php->expects('uses')->with('cakephp/cakephp', '<5.4')->returns(true);

    $assist = Double::for(new GuidelineAssist($this->project->instance(), $this->config))->passthru();
    $assist->allows('discover')->returns([]);

    expect($assist->versionLt('cakephp/cakephp', '5.4'))->toBeTrue();
});

test('uses accepts composer semver constraints via inspector', function (): void {
    $this->php->expects('uses')->with('cakephp/cakephp', '>=5.0 <6')->returns(true);

    $assist = Double::for(new GuidelineAssist($this->project->instance(), $this->config))->passthru();
    $assist->allows('discover')->returns([]);

    expect($assist->uses('cakephp/cakephp', '>=5.0 <6'))->toBeTrue();
});

test('uses returns false when neither ecosystem matches', function (): void {
    $this->php->expects('uses')->with('cakephp/cakephp', '>=6.0')->returns(false);
    $this->js->expects('uses')->with('cakephp/cakephp', '>=6.0')->returns(false);

    $assist = Double::for(new GuidelineAssist($this->project->instance(), $this->config))->passthru();
    $assist->allows('discover')->returns([]);

    expect($assist->uses('cakephp/cakephp', '>=6.0'))->toBeFalse();
});

test('packageVersion returns resolved php package version', function (): void {
    $this->php->expects('package')->with('cakephp/cakephp')->returns(
        inspectorPackage('cakephp/cakephp', '5.4.2'),
    );

    $assist = Double::for(new GuidelineAssist($this->project->instance(), $this->config))->passthru();
    $assist->allows('discover')->returns([]);

    expect($assist->packageVersion('cakephp/cakephp'))->toBe('5.4.2');
});

test('packageVersion returns null when package is missing', function (): void {
    $this->php->expects('package')->with('missing/pkg')->returns(null);
    $this->js->expects('package')->with('missing/pkg')->returns(null);

    $assist = Double::for(new GuidelineAssist($this->project->instance(), $this->config))->passthru();
    $assist->allows('discover')->returns([]);

    expect($assist->packageVersion('missing/pkg'))->toBeNull();
});

test('inspectorPackages returns only direct dependencies by default', function (): void {
    $direct = inspectorPackage('cakephp/cakephp', '5.4.0')->setDirect(true);
    $transitive = inspectorPackage('doctrine/inflector', '2.0.0')->setDirect(false);
    $this->php->allows('packages')->returns(new PackageCollection([$direct, $transitive]));

    $assist = Double::for(new GuidelineAssist($this->project->instance(), $this->config))->passthru();
    $assist->allows('discover')->returns([]);

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
    $this->php->allows('packages')->returns(new PackageCollection([$direct, $transitive]));

    $assist = Double::for(new GuidelineAssist($this->project->instance(), $this->config))->passthru();
    $assist->allows('discover')->returns([]);

    expect($assist->listAllDependencies())->toBeTrue()
        ->and($assist->inspectorPackages())->toHaveCount(2)
        ->and($assist->inspectorPackages()[1]['name'])->toBe('doctrine/inflector');

    Configure::delete('Ignis.guidelines.dependencies');
});
