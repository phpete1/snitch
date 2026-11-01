<?php

namespace Phpete1\Snitch;

final readonly class BinaryResolver
{
    const string DEFAULT_VERSION = '1.9.0';

    public string $archiveUrl;

    public string $archivedBinary;

    public string $archivePath;

    public string $archiveDirectory;

    public string $archiveName;

    public string $executablePath;

    public string $checksumsUrl;

    public string $extractedBinaryPath;

    public function __construct(?string $version = null, ?string $os = null, ?string $arch = null)
    {
        $version = $version ?? self::DEFAULT_VERSION;

        $os = strtolower($os ?? PHP_OS_FAMILY);
        $os = match ($os) {
            'darwin', 'linux', 'windows' => $os,
            default => throw new \RuntimeException("Unsupported OS Family: {$os}")
        };

        $arch = strtolower($arch ?? php_uname('m'));
        $arch = match ($arch) {
            'x86_64', 'amd64' => 'x64',
            'arm64', 'aarch64' => 'arm64',
            default => throw new \RuntimeException("Unsupported architecture: {$arch}")
        };

        $archiveType = $os === 'windows' ? '.zip' : '.tar.gz';
        $binaryExtension = $os === 'windows' ? '.exe' : '';
        $releaseName = "betterleaks_{$version}_{$os}_{$arch}";
        $binary = "{$releaseName}{$binaryExtension}";
        $releaseUrl = "https://github.com/betterleaks/betterleaks/releases/download/v{$version}/";
        $directory = __DIR__.'/../bins/';

        $this->archiveName = $releaseName.$archiveType;
        $this->archiveUrl = "{$releaseUrl}{$this->archiveName}";
        $this->archiveDirectory = sys_get_temp_dir().'/'.bin2hex(random_bytes(8));
        $this->archivePath = "{$this->archiveDirectory}/{$this->archiveName}";
        $this->archivedBinary = "betterleaks{$binaryExtension}";
        $this->extractedBinaryPath = "{$this->archiveDirectory}/{$this->archivedBinary}";
        $this->executablePath = $directory.$binary;
        $this->checksumsUrl = "{$releaseUrl}checksums.txt";
    }

    public function canExecute(): bool
    {
        return is_file($this->executablePath) && is_executable($this->executablePath);
    }
}
