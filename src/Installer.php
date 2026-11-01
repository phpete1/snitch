<?php

namespace Phpete1\Snitch;

final readonly class Installer
{
    public function __construct(private BinaryResolver $resolver) {}

    public function run(): void
    {
        try {
            $this->prepare();
            $this->download();
            $this->verify();
            $this->extract();
        } finally {
            $this->cleanup();
        }
    }

    private function prepare(): void
    {
        if (! mkdir($this->resolver->archiveDirectory)) {
            throw new \RuntimeException("Cannot create directory '{$this->resolver->archiveDirectory}'");
        }
    }

    private function download(): void
    {
        if (! copy(from: $this->resolver->archiveUrl, to: $this->resolver->archivePath)) {
            throw new \RuntimeException("Download failed: {$this->resolver->archivePath}");
        }
    }

    private function extract(): void
    {
        $archive = new \PharData($this->resolver->archivePath);
        $archive->extractTo(directory: $this->resolver->archiveDirectory, files: $this->resolver->archivedBinary, overwrite: true);

        $this->permit();

        // we add !canExecute because if snitch runs multiple times at once on Windows, it will throw an error
        if (! rename($this->resolver->extractedBinaryPath, $this->resolver->executablePath) && ! $this->resolver->canExecute()) {
            throw new \RuntimeException("Failed to move binary from {$this->resolver->extractedBinaryPath} to {$this->resolver->executablePath}");
        }
    }

    private function permit(): void
    {
        if (! chmod(filename: $this->resolver->extractedBinaryPath, permissions: 0755)) {
            throw new \RuntimeException("Permissions failed: {$this->resolver->extractedBinaryPath}");
        }
    }

    private function cleanup(): void
    {
        if (is_file($this->resolver->archivePath)) {
            unlink($this->resolver->archivePath);
        }

        if (is_dir($this->resolver->archiveDirectory)) {
            $files = glob($this->resolver->archiveDirectory.'/*') ?: [];

            foreach ($files as $file) {
                unlink($file);
            }

            rmdir($this->resolver->archiveDirectory);
        }
    }

    private function verify(): void
    {
        $file = file_get_contents($this->resolver->checksumsUrl);

        if ($file === false) {
            throw new \RuntimeException("ChecksumsUrl failed: {$this->resolver->checksumsUrl}");
        }

        $lines = explode("\n", $file);

        foreach ($lines as $line) {
            $line = trim($line);
            if (str_ends_with($line, $this->resolver->archiveName)) {
                $expectedChecksum = strtok($line, ' ');
                $actualChecksum = hash_file('sha256', $this->resolver->archivePath);

                if (! $actualChecksum || ! $expectedChecksum) {
                    throw new \RuntimeException("ChecksumsUrl failed: {$this->resolver->archiveUrl}");
                }

                if (hash_equals($expectedChecksum, $actualChecksum)) {
                    return;
                }

                break;
            }
        }

        throw new \RuntimeException("Failed to verify {$this->resolver->archiveName} checksum");
    }
}
