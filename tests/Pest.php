<?php

declare(strict_types=1);

use Phpete1\Snitch\BinaryResolver;

const BINARY_CONTENT = "#!/bin/sh\nexit 0\n";

pest()->afterEach(fn () => removeTemporaryDirectories());

/**
 * Create a unique directory that is removed after the current test.
 */
function temporaryDirectory(string $prefix = 'snitch'): string
{
    $directory = sys_get_temp_dir().'/'.$prefix.'-'.bin2hex(random_bytes(8));
    mkdir($directory, 0777, true);

    TemporaryDirectories::$paths[] = $directory;

    return $directory;
}

function removeTemporaryDirectories(): void
{
    foreach (TemporaryDirectories::$paths as $directory) {
        removeDirectory($directory);
    }

    TemporaryDirectories::$paths = [];
}

function removeDirectory(string $directory): void
{
    if (! is_dir($directory)) {
        return;
    }

    chmod($directory, 0755);

    foreach (glob($directory.'/*') ?: [] as $file) {
        is_dir($file) ? removeDirectory($file) : unlink($file);
    }

    rmdir($directory);
}

/**
 * Create a local release containing a real archive and a matching
 * checksums file, and return a resolver pointing at it.
 */
function fakeRelease(string $os = 'darwin', string $arch = 'arm64', ?string $binsDirectory = null): BinaryResolver
{
    $releaseDirectory = temporaryDirectory('snitch-release');
    mkdir("{$releaseDirectory}/v9.9.9");

    $resolver = new BinaryResolver(
        version: '9.9.9',
        os: $os,
        arch: $arch,
        releaseBaseUrl: "{$releaseDirectory}/",
        binsDirectory: $binsDirectory ?? temporaryDirectory('snitch-bins'),
    );

    $archivePath = releaseAsset($resolver, $resolver->archiveName);

    if ($os === 'windows') {
        createZipArchive($archivePath, $resolver->archivedBinary);
    } else {
        createTarGzArchive($archivePath, $resolver->archivedBinary);
    }

    writeChecksums($resolver, sha256($archivePath));

    return $resolver;
}

/**
 * Path of a file in the fake release that the resolver downloads from.
 */
function releaseAsset(BinaryResolver $resolver, string $name): string
{
    return dirname($resolver->checksumsUrl).'/'.$name;
}

function writeChecksums(BinaryResolver $resolver, string $checksum, ?string $archiveName = null): void
{
    file_put_contents(
        releaseAsset($resolver, 'checksums.txt'),
        "{$checksum}  ".($archiveName ?? $resolver->archiveName)."\n",
    );
}

function packageRoot(): string
{
    return str_replace('\\', '/', dirname(__DIR__));
}

function sha256(string $path): string
{
    return hash_file('sha256', $path) ?: throw new RuntimeException("Unable to hash {$path}");
}

function createTarGzArchive(string $path, string $binaryName): void
{
    $staging = temporaryDirectory('snitch-fixture');
    file_put_contents("{$staging}/{$binaryName}", BINARY_CONTENT);

    $tar = new PharData("{$staging}/release.tar");
    $tar->addFile("{$staging}/{$binaryName}", $binaryName);
    $tar->compress(Phar::GZ);

    rename("{$staging}/release.tar.gz", $path);
}

function createZipArchive(string $path, string $binaryName): void
{
    $zip = new ZipArchive;
    $zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE);
    $zip->addFromString($binaryName, BINARY_CONTENT);
    $zip->close();
}

final class TemporaryDirectories
{
    /** @var list<string> */
    public static array $paths = [];
}
