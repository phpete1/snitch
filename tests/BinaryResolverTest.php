<?php

declare(strict_types=1);

use Phpete1\Snitch\BinaryResolver;

test('it resolves the release for a platform', function () {
    $resolver = new BinaryResolver(version: '9.9.9', os: 'darwin', arch: 'arm64');

    expect($resolver->archiveName)->toBe('betterleaks_9.9.9_darwin_arm64.tar.gz')
        ->and($resolver->archiveUrl)->toBe('https://github.com/betterleaks/betterleaks/releases/download/v9.9.9/betterleaks_9.9.9_darwin_arm64.tar.gz')
        ->and($resolver->checksumsUrl)->toBe('https://github.com/betterleaks/betterleaks/releases/download/v9.9.9/checksums.txt')
        ->and($resolver->archivedBinary)->toBe('betterleaks')
        ->and($resolver->executablePath)->toBe(dirname(__DIR__).'/src/../bins/betterleaks_9.9.9_darwin_arm64')
        ->and($resolver->archivePath)->toBe($resolver->archiveDirectory.'/'.$resolver->archiveName)
        ->and($resolver->extractedBinaryPath)->toBe($resolver->archiveDirectory.'/'.$resolver->archivedBinary)
        ->and($resolver->archiveDirectory)->toStartWith(sys_get_temp_dir().'/');
});

test('it resolves windows releases from zip archives', function () {
    $resolver = new BinaryResolver(version: '9.9.9', os: 'windows', arch: 'amd64');

    expect($resolver->archiveName)->toBe('betterleaks_9.9.9_windows_x64.zip')
        ->and($resolver->archiveUrl)->toBe('https://github.com/betterleaks/betterleaks/releases/download/v9.9.9/betterleaks_9.9.9_windows_x64.zip')
        ->and($resolver->archivedBinary)->toBe('betterleaks.exe')
        ->and($resolver->executablePath)->toBe(dirname(__DIR__).'/src/../bins/betterleaks_9.9.9_windows_x64.exe');
});

test('it normalises os and arch casing', function () {
    $resolver = new BinaryResolver(os: 'Darwin', arch: 'AARCH64');

    expect($resolver->archiveName)->toBe('betterleaks_1.9.0_darwin_arm64.tar.gz');
});

test('it maps x64 aliases', function (string $arch) {
    $resolver = new BinaryResolver(arch: $arch);

    expect($resolver->archiveName)->toEndWith('_x64.tar.gz');
})->with(['x86_64', 'AMD64']);

test('it defaults to the current platform', function () {
    $default = new BinaryResolver;
    $explicit = new BinaryResolver(
        version: BinaryResolver::DEFAULT_VERSION,
        os: strtolower(PHP_OS_FAMILY),
        arch: strtolower(php_uname('m')),
    );

    expect($default->archiveUrl)->toBe($explicit->archiveUrl)
        ->and($default->checksumsUrl)->toBe($explicit->checksumsUrl)
        ->and($default->executablePath)->toBe($explicit->executablePath)
        ->and($default->archivedBinary)->toBe($explicit->archivedBinary);
});

test('it uses the default version when none is given', function () {
    $resolver = new BinaryResolver;

    expect($resolver->archiveUrl)->toContain('v'.BinaryResolver::DEFAULT_VERSION.'/');
});

test('it rejects unsupported operating systems', function () {
    new BinaryResolver(os: 'freebsd');
})->throws(RuntimeException::class, 'Unsupported OS Family: freebsd');

test('it rejects unsupported architectures', function () {
    new BinaryResolver(arch: 'riscv');
})->throws(RuntimeException::class, 'Unsupported architecture: riscv');

test('it uses a custom release url and bins directory', function () {
    $bins = temporaryDirectory('snitch-bins');

    $resolver = new BinaryResolver(
        version: '9.9.9',
        os: 'linux',
        arch: 'amd64',
        releaseBaseUrl: 'https://example.com/releases/',
        binsDirectory: $bins,
    );

    expect($resolver->archiveUrl)->toBe('https://example.com/releases/v9.9.9/betterleaks_9.9.9_linux_x64.tar.gz')
        ->and($resolver->checksumsUrl)->toBe('https://example.com/releases/v9.9.9/checksums.txt')
        ->and($resolver->executablePath)->toBe($bins.'/betterleaks_9.9.9_linux_x64');
});

test('it cannot execute a missing binary', function () {
    $resolver = new BinaryResolver(version: '9.9.9', binsDirectory: temporaryDirectory('snitch-bins'));

    expect($resolver->canExecute())->toBeFalse();
});

test('it cannot execute a binary without the executable bit', function () {
    $resolver = new BinaryResolver(version: '9.9.9', binsDirectory: temporaryDirectory('snitch-bins'));
    file_put_contents($resolver->executablePath, 'binary');
    chmod($resolver->executablePath, 0644);

    expect($resolver->canExecute())->toBeFalse();
});

test('it can execute an executable binary', function () {
    $resolver = new BinaryResolver(version: '9.9.9', binsDirectory: temporaryDirectory('snitch-bins'));
    file_put_contents($resolver->executablePath, 'binary');
    chmod($resolver->executablePath, 0755);

    expect($resolver->canExecute())->toBeTrue();
});
