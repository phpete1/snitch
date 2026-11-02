<?php

declare(strict_types=1);

namespace Phpete1\Snitch\Tests;

use Phpete1\Snitch\BinaryResolver;
use PHPUnit\Framework\TestCase;

final class BinaryResolverTest extends TestCase
{
    public function test_it_resolves_every_supported_platform(): void
    {
        $resolver = new BinaryResolver(version: '9.9.9', os: 'darwin', arch: 'arm64');

        $this->assertSame('betterleaks_9.9.9_darwin_arm64.tar.gz', $resolver->archiveName);
        $this->assertSame('https://github.com/betterleaks/betterleaks/releases/download/v9.9.9/betterleaks_9.9.9_darwin_arm64.tar.gz', $resolver->archiveUrl);
        $this->assertSame('https://github.com/betterleaks/betterleaks/releases/download/v9.9.9/checksums.txt', $resolver->checksumsUrl);
        $this->assertSame('betterleaks', $resolver->archivedBinary);
        $this->assertSame(dirname(__DIR__).'/src/../bins/betterleaks_9.9.9_darwin_arm64', $resolver->executablePath);
        $this->assertSame($resolver->archiveDirectory.'/'.$resolver->archiveName, $resolver->archivePath);
        $this->assertSame($resolver->archiveDirectory.'/'.$resolver->archivedBinary, $resolver->extractedBinaryPath);
        $this->assertStringStartsWith(sys_get_temp_dir().'/', $resolver->archiveDirectory);
    }

    public function test_it_resolves_windows_releases_from_zip_archives(): void
    {
        $resolver = new BinaryResolver(version: '9.9.9', os: 'windows', arch: 'amd64');

        $this->assertSame('betterleaks_9.9.9_windows_x64.zip', $resolver->archiveName);
        $this->assertSame('https://github.com/betterleaks/betterleaks/releases/download/v9.9.9/betterleaks_9.9.9_windows_x64.zip', $resolver->archiveUrl);
        $this->assertSame('betterleaks.exe', $resolver->archivedBinary);
        $this->assertSame(dirname(__DIR__).'/src/../bins/betterleaks_9.9.9_windows_x64.exe', $resolver->executablePath);
    }

    public function test_it_normalises_os_and_arch_aliases(): void
    {
        $resolver = new BinaryResolver(os: 'Darwin', arch: 'AARCH64');

        $this->assertSame('betterleaks_1.9.0_darwin_arm64.tar.gz', $resolver->archiveName);
    }

    public function test_it_accepts_x86_64_and_amd64_for_x64(): void
    {
        foreach (['x86_64', 'AMD64'] as $arch) {
            $resolver = new BinaryResolver(arch: $arch);

            $this->assertStringEndsWith('_x64.tar.gz', $resolver->archiveName);
        }
    }

    public function test_it_defaults_to_the_current_platform_and_version(): void
    {
        $default = new BinaryResolver;
        $explicit = new BinaryResolver(
            version: BinaryResolver::DEFAULT_VERSION,
            os: strtolower(PHP_OS_FAMILY),
            arch: strtolower(php_uname('m')),
        );

        $this->assertSame($explicit->archiveUrl, $default->archiveUrl);
        $this->assertSame($explicit->checksumsUrl, $default->checksumsUrl);
        $this->assertSame($explicit->executablePath, $default->executablePath);
        $this->assertSame($explicit->archivedBinary, $default->archivedBinary);
    }

    public function test_it_uses_the_default_version_when_none_is_given(): void
    {
        $resolver = new BinaryResolver;

        $this->assertStringContainsString('v'.BinaryResolver::DEFAULT_VERSION.'/', $resolver->archiveUrl);
    }

    public function test_it_rejects_unsupported_operating_systems(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Unsupported OS Family: freebsd');

        new BinaryResolver(os: 'freebsd');
    }

    public function test_it_rejects_unsupported_architectures(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Unsupported architecture: riscv');

        new BinaryResolver(arch: 'riscv');
    }

    public function test_it_uses_a_custom_release_url_and_bins_directory(): void
    {
        $bins = sys_get_temp_dir().'/snitch-bins-'.bin2hex(random_bytes(4));

        $resolver = new BinaryResolver(
            version: '9.9.9',
            os: 'linux',
            arch: 'amd64',
            releaseBaseUrl: 'https://example.com/releases/',
            binsDirectory: $bins,
        );

        $this->assertSame('https://example.com/releases/v9.9.9/betterleaks_9.9.9_linux_x64.tar.gz', $resolver->archiveUrl);
        $this->assertSame('https://example.com/releases/v9.9.9/checksums.txt', $resolver->checksumsUrl);
        $this->assertSame($bins.'/betterleaks_9.9.9_linux_x64', $resolver->executablePath);
    }

    public function test_can_execute_is_false_when_the_binary_is_missing(): void
    {
        $resolver = $this->resolverWithBinsDirectory();

        $this->assertFalse($resolver->canExecute());
    }

    public function test_can_execute_is_false_when_the_binary_is_not_executable(): void
    {
        $resolver = $this->resolverWithBinsDirectory();
        file_put_contents($resolver->executablePath, 'binary');
        chmod($resolver->executablePath, 0644);

        $this->assertFalse($resolver->canExecute());
    }

    public function test_can_execute_is_true_when_the_binary_is_executable(): void
    {
        $resolver = $this->resolverWithBinsDirectory();
        file_put_contents($resolver->executablePath, 'binary');
        chmod($resolver->executablePath, 0755);

        $this->assertTrue($resolver->canExecute());
    }

    private function resolverWithBinsDirectory(): BinaryResolver
    {
        $bins = sys_get_temp_dir().'/snitch-bins-'.bin2hex(random_bytes(4));
        @mkdir($bins);

        return new BinaryResolver(version: '9.9.9', binsDirectory: $bins);
    }
}
