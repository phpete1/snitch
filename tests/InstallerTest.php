<?php

declare(strict_types=1);

use Phpete1\Snitch\Installer;

test('it installs the binary and cleans up', function () {
    $resolver = fakeRelease();

    (new Installer($resolver))->run();

    expect($resolver->canExecute())->toBeTrue()
        ->and(file_get_contents($resolver->executablePath))->toBe(BINARY_CONTENT)
        ->and(fileperms($resolver->executablePath) & 0777)->toBe(0755)
        ->and($resolver->archiveDirectory)->not->toBeDirectory();
})->skipOnWindows();

test('it installs windows binaries from zip archives', function () {
    $resolver = fakeRelease(os: 'windows', arch: 'amd64');

    (new Installer($resolver))->run();

    expect($resolver->executablePath)->toEndWith('.exe')
        ->and($resolver->canExecute())->toBeTrue()
        ->and(file_get_contents($resolver->executablePath))->toBe(BINARY_CONTENT);
});

test('it fails when the binary cannot be moved into the bins directory', function () {
    $bins = temporaryDirectory('snitch-bins');
    $resolver = fakeRelease(binsDirectory: $bins);

    // A read-only bins directory makes the final rename fail.
    chmod($bins, 0555);

    expect(fn () => (new Installer($resolver))->run())
        ->toThrow(RuntimeException::class, "Failed to move binary from {$resolver->extractedBinaryPath} to {$resolver->executablePath}")
        ->and($resolver->archiveDirectory)->not->toBeDirectory();
})->skipOnWindows();

test('it fails when the archive cannot be downloaded', function () {
    $resolver = fakeRelease();
    unlink(releaseAsset($resolver, $resolver->archiveName));

    expect(fn () => (new Installer($resolver))->run())
        ->toThrow(RuntimeException::class, "Download failed: {$resolver->archivePath}")
        ->and($resolver->archiveDirectory)->not->toBeDirectory();
});

test('it fails when the checksum does not match', function () {
    $resolver = fakeRelease();
    writeChecksums($resolver, str_repeat('0', 64));

    expect(fn () => (new Installer($resolver))->run())
        ->toThrow(RuntimeException::class, "Failed to verify {$resolver->archiveName} checksum")
        ->and($resolver->archiveDirectory)->not->toBeDirectory();
});

test('it fails when the archive is missing from the checksums', function () {
    $resolver = fakeRelease();
    writeChecksums($resolver, hash('sha256', BINARY_CONTENT), 'some_other_archive.tar.gz');

    expect(fn () => (new Installer($resolver))->run())
        ->toThrow(RuntimeException::class, "Failed to verify {$resolver->archiveName} checksum");
});

test('it fails when the checksums cannot be fetched', function () {
    $resolver = fakeRelease();
    unlink(releaseAsset($resolver, 'checksums.txt'));

    expect(fn () => (new Installer($resolver))->run())
        ->toThrow(RuntimeException::class, "ChecksumsUrl failed: {$resolver->checksumsUrl}")
        ->and($resolver->archiveDirectory)->not->toBeDirectory();
});

test('it fails when the archive is corrupt', function () {
    $resolver = fakeRelease();
    $archive = releaseAsset($resolver, $resolver->archiveName);
    file_put_contents($archive, 'not a real archive');
    writeChecksums($resolver, sha256($archive));

    expect(fn () => (new Installer($resolver))->run())
        ->toThrow(UnexpectedValueException::class)
        ->and($resolver->archiveDirectory)->not->toBeDirectory();
});
