<?php
declare(strict_types=1);

namespace Crustum\Ignis\Test\Unit\Support;

use Crustum\Ignis\Support\FileLink;
use Crustum\Ignis\Support\Filesystem;

beforeEach(function (): void {
    useTestApp();
    $this->sandbox = testAppTmpPath('file-link-' . uniqid());
    Filesystem::ensureDirectory($this->sandbox);
});

afterEach(function (): void {
    $target = $this->sandbox . DIRECTORY_SEPARATOR . 'target.txt';
    $link = $this->sandbox . DIRECTORY_SEPARATOR . 'link.txt';

    if ((FileLink::isLink($link) || is_link($link) || is_file($link)) && !FileLink::remove($link)) {
        @unlink($link);
    }

    if (is_file($target)) {
        @unlink($target);
    }

    if (is_dir($this->sandbox)) {
        @rmdir($this->sandbox);
    }

    resetTestApp();
});

it('creates a file link that points at the target', function (): void {
    $target = $this->sandbox . DIRECTORY_SEPARATOR . 'target.txt';
    $link = $this->sandbox . DIRECTORY_SEPARATOR . 'link.txt';
    file_put_contents($target, 'linked-content');

    expect(FileLink::create($target, $link))->toBeTrue()
        ->and(is_file($link))->toBeTrue()
        ->and(FileLink::isLink($link))->toBeTrue()
        ->and(FileLink::pointsTo($link, $target))->toBeTrue()
        ->and(file_get_contents($link))->toBe('linked-content');
});

it('removes the link without deleting the target', function (): void {
    $target = $this->sandbox . DIRECTORY_SEPARATOR . 'target.txt';
    $link = $this->sandbox . DIRECTORY_SEPARATOR . 'link.txt';
    file_put_contents($target, 'keep-me');

    expect(FileLink::create($target, $link))->toBeTrue();
    expect(FileLink::remove($link))->toBeTrue()
        ->and(is_file($link))->toBeFalse()
        ->and(is_file($target))->toBeTrue()
        ->and(file_get_contents($target))->toBe('keep-me');
});

it('fails when the target file is missing', function (): void {
    $target = $this->sandbox . DIRECTORY_SEPARATOR . 'missing.txt';
    $link = $this->sandbox . DIRECTORY_SEPARATOR . 'link.txt';

    expect(FileLink::create($target, $link))->toBeFalse()
        ->and(is_file($link))->toBeFalse();
});
