<?php

declare(strict_types=1);

namespace Phpete1\Snitch\Tests;

use Phpete1\Snitch\BinaryResolver;
use Phpete1\Snitch\Installer;
use PHPUnit\Framework\TestCase;

final class InstallerTest extends TestCase
{
    private const string BINARY_CONTENT = "#!/bin/sh\nexit 0\n";

    private string $workDirectory;

    private string $releaseDirectory;

    private string $releaseAssetsDirectory;

    private string $binsDirectory;

    protected function setUp(): void
    {
        $this->workDirectory = sys_get_temp_dir().'/snitch-installer-'.bin2hex(random_bytes(8));
        $this->releaseDirectory = "{$this->workDirectory}/release";
        $this->releaseAssetsDirectory = "{$this->releaseDirectory}/v9.9.9";
        $this->binsDirectory = "{$this->workDirectory}/bins";

        mkdir($this->releaseAssetsDirectory, 0777, true);
        mkdir($this->binsDirectory, 0777, true);
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->workDirectory);
    }

    public function test_it_installs_the_binary_and_cleans_up_after_itself(): void
    {
        $resolver = $this->makeRelease(os: 'darwin', arch: 'arm64');

        (new Installer($resolver))->run();

        $this->assertTrue($resolver->canExecute());
        $this->assertSame(self::BINARY_CONTENT, file_get_contents($resolver->executablePath));
        $this->assertSame(0755, fileperms($resolver->executablePath) & 0777);
        $this->assertFileDoesNotExist($resolver->archiveDirectory);
    }

    public function test_it_installs_windows_binaries_from_zip_archives(): void
    {
        $resolver = $this->makeRelease(os: 'windows', arch: 'amd64');

        (new Installer($resolver))->run();

        $this->assertStringEndsWith('.exe', $resolver->executablePath);
        $this->assertTrue($resolver->canExecute());
        $this->assertSame(self::BINARY_CONTENT, file_get_contents($resolver->executablePath));
    }

    public function test_it_fails_when_the_binary_cannot_be_moved_into_the_bins_directory(): void
    {
        $resolver = $this->makeRelease(os: 'darwin', arch: 'arm64');

        // A read-only bins directory makes the final rename fail, leaving
        // the extracted binary behind for cleanup to remove.
        chmod($this->binsDirectory, 0555);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage("Failed to move binary from {$resolver->extractedBinaryPath} to {$resolver->executablePath}");

        try {
            (new Installer($resolver))->run();
        } finally {
            chmod($this->binsDirectory, 0755);
            $this->assertFileDoesNotExist($resolver->archiveDirectory);
        }
    }

    public function test_it_fails_when_the_archive_cannot_be_downloaded(): void
    {
        $resolver = $this->makeRelease(os: 'darwin', arch: 'arm64');
        unlink("{$this->releaseAssetsDirectory}/{$resolver->archiveName}");

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage("Download failed: {$resolver->archivePath}");

        try {
            (new Installer($resolver))->run();
        } finally {
            $this->assertFileDoesNotExist($resolver->archiveDirectory);
        }
    }

    public function test_it_fails_when_the_checksum_does_not_match(): void
    {
        $resolver = $this->makeRelease(os: 'darwin', arch: 'arm64');
        $this->writeChecksums($resolver, str_repeat('0', 64));

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage("Failed to verify {$resolver->archiveName} checksum");

        try {
            (new Installer($resolver))->run();
        } finally {
            $this->assertFileDoesNotExist($resolver->archiveDirectory);
        }
    }

    public function test_it_fails_when_the_archive_is_not_listed_in_the_checksums(): void
    {
        $resolver = $this->makeRelease(os: 'darwin', arch: 'arm64');
        $this->writeChecksums($resolver, hash('sha256', self::BINARY_CONTENT), 'some_other_archive.tar.gz');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage("Failed to verify {$resolver->archiveName} checksum");

        (new Installer($resolver))->run();
    }

    public function test_it_fails_when_the_checksums_cannot_be_fetched(): void
    {
        $resolver = $this->makeRelease(os: 'darwin', arch: 'arm64');
        unlink("{$this->releaseAssetsDirectory}/checksums.txt");

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage("ChecksumsUrl failed: {$resolver->checksumsUrl}");

        try {
            (new Installer($resolver))->run();
        } finally {
            $this->assertFileDoesNotExist($resolver->archiveDirectory);
        }
    }

    public function test_it_fails_when_the_archive_is_corrupt(): void
    {
        $resolver = $this->makeRelease(os: 'darwin', arch: 'arm64');
        file_put_contents("{$this->releaseAssetsDirectory}/{$resolver->archiveName}", 'not a real archive');
        $this->writeChecksums($resolver, $this->sha256("{$this->releaseAssetsDirectory}/{$resolver->archiveName}"));

        $this->expectException(\UnexpectedValueException::class);

        try {
            (new Installer($resolver))->run();
        } finally {
            $this->assertFileDoesNotExist($resolver->archiveDirectory);
        }
    }

    /**
     * Create a local release directory containing a real archive and a
     * matching checksums file, plus a resolver pointing at them.
     */
    private function makeRelease(string $os, string $arch): BinaryResolver
    {
        $resolver = new BinaryResolver(
            version: '9.9.9',
            os: $os,
            arch: $arch,
            releaseBaseUrl: "{$this->releaseDirectory}/",
            binsDirectory: $this->binsDirectory,
        );

        $archivePath = "{$this->releaseAssetsDirectory}/{$resolver->archiveName}";

        if ($os === 'windows') {
            $this->createZipArchive($archivePath, $resolver->archivedBinary);
        } else {
            $this->createTarGzArchive($archivePath, $resolver->archivedBinary);
        }

        $this->writeChecksums($resolver, $this->sha256($archivePath));

        return $resolver;
    }

    private function sha256(string $path): string
    {
        return hash_file('sha256', $path) ?: self::fail("Unable to hash {$path}");
    }

    private function writeChecksums(BinaryResolver $resolver, string $checksum, ?string $archiveName = null): void
    {
        file_put_contents(
            "{$this->releaseAssetsDirectory}/checksums.txt",
            "{$checksum}  ".($archiveName ?? $resolver->archiveName)."\n",
        );
    }

    private function createTarGzArchive(string $path, string $binaryName): void
    {
        $staging = sys_get_temp_dir().'/snitch-fixture-'.bin2hex(random_bytes(4));
        mkdir($staging);

        file_put_contents("{$staging}/{$binaryName}", self::BINARY_CONTENT);

        $tar = new \PharData("{$staging}/release.tar");
        $tar->addFile("{$staging}/{$binaryName}", $binaryName);
        $tar->compress(\Phar::GZ);

        rename("{$staging}/release.tar.gz", $path);

        $this->removeDirectory($staging);
    }

    private function createZipArchive(string $path, string $binaryName): void
    {
        $zip = new \ZipArchive;
        $zip->open($path, \ZipArchive::CREATE | \ZipArchive::OVERWRITE);
        $zip->addFromString($binaryName, self::BINARY_CONTENT);
        $zip->close();
    }

    private function removeDirectory(string $directory): void
    {
        if (! is_dir($directory)) {
            return;
        }

        foreach (glob($directory.'/*') ?: [] as $file) {
            is_dir($file) ? $this->removeDirectory($file) : unlink($file);
        }

        rmdir($directory);
    }
}
