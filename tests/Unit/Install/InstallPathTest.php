<?php
declare(strict_types=1);

namespace Crustum\Ignis\Test\Unit\Install;

use Crustum\Ignis\Install\InstallPath;
use Crustum\Ignis\Support\ProjectRoot;
use RuntimeException;

beforeEach(function (): void {
    useTestApp();
});

afterEach(function (): void {
    resetTestApp();
});

it('resolves absolute existing directories', function (): void {
    $path = ProjectRoot::path();

    expect(InstallPath::resolve($path))->toBe(realpath($path));
});

it('resolves paths relative to the CakePHP application ROOT', function (): void {
    $dir = 'ignis-rel-' . uniqid();
    $absolute = rtrim(ROOT, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $dir;
    mkdir($absolute, 0777, true);

    try {
        expect(InstallPath::resolve($dir))->toBe(realpath($absolute));
    } finally {
        rmdir($absolute);
    }
});

it('throws when the path is missing', function (): void {
    InstallPath::resolve(ProjectRoot::path() . DIRECTORY_SEPARATOR . 'missing-' . uniqid());
})->throws(RuntimeException::class);

it('detects an existing .ai directory as a conflict', function (): void {
    $withAi = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'ignis-path-ai-' . uniqid();
    $withoutAi = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'ignis-path-empty-' . uniqid();
    mkdir($withAi . DIRECTORY_SEPARATOR . '.ai', 0777, true);
    mkdir($withoutAi, 0777, true);

    try {
        expect(InstallPath::hasAiConflict($withAi))->toBeTrue();
        expect(InstallPath::hasAiConflict($withoutAi))->toBeFalse();
    } finally {
        rmdir($withAi . DIRECTORY_SEPARATOR . '.ai');
        rmdir($withAi);
        rmdir($withoutAi);
    }
});

it('treats absolute windows-style and unix paths as absolute', function (): void {
    expect(InstallPath::isAbsolutePath('/tmp/foo'))->toBeTrue();
    expect(InstallPath::isAbsolutePath('C:\\projects\\foo'))->toBeTrue();
    expect(InstallPath::isAbsolutePath('plugins/scout'))->toBeFalse();
});
