# Snitch

Scans your PHP project for leaked secrets, such as API keys, tokens and passwords, with a single command.

Snitch is a thin wrapper around [Betterleaks](https://github.com/betterleaks/betterleaks). It fetches the right Betterleaks binary for your machine, then runs a scan of your project.

## Requirements

- PHP 8.3 or higher
- The Phar and Zlib extensions, to unpack the Betterleaks release
- The OpenSSL extension with `allow_url_fopen` enabled, to download it over HTTPS

All of these are enabled by default in most PHP builds.

## Installation

```bash
composer require --dev phpete1/snitch
```

## Usage

From your project root:

```bash
vendor/bin/snitch
```

Snitch scans the current directory and reports every finding with its file and line.

### `snitch init`

Create a default Betterleaks config (`.betterleaks.toml`) and an empty `.betterleaksignore` file in the current project:

```bash
vendor/bin/snitch init
```

The generated config extends Betterleaks' built-in rules and skips common noisy
directories such as `vendor`, `node_modules`, `.git`, `dist`, `build`, and
others. Snitch only writes these files when you run this command; running a
scan never creates them automatically.

### Exit codes

- `0` means no leaks were found
- `1` means leaks were found
- `2` means Snitch failed, for example because the download didn't work or your platform isn't supported

That makes it easy to block commits or fail CI builds, for example from a GrumPHP task or a pipeline step.

## How it works

The first time Snitch runs, it downloads the Betterleaks release for your OS and architecture from GitHub. It checks the download against the SHA-256 checksums published with the release, and refuses to install it if they don't match. It then stores the binary inside the package, named by version and platform. Later runs reuse the stored binary.

Because each binary has its own versioned name, builds for different platforms sit side by side. A project shared between a Mac and a Linux Docker container just works.

## Supported platforms

| OS      | Architectures |
| ------- | ------------- |
| macOS   | x64, arm64    |
| Linux   | x64, arm64    |
| Windows | x64, arm64    |

Snitch stops with an error on any other platform.

## Betterleaks version

Snitch currently uses Betterleaks 1.9.0 by default. You can pin a different version by adding it to your `composer.json`:

```json
{
    "extra": {
        "snitch": {
            "version": "1.9.0"
        }
    }
}
```

## Known limitations

- The scan includes `vendor`, which can produce a lot of noise. Run `snitch init` to create a Betterleaks config that skips it.
- The binary is stored inside `vendor`, so it is downloaded again after the package is updated or reinstalled. The first run also needs write access to `vendor`, which can fail in images where it is read-only.

## Roadmap

- **GitHub Actions CI:** run `grumphp run` (Pest, PHPStan and Pint) on macOS, Linux and Windows.
- **Version picker:** a command to choose or type a Betterleaks version, which Snitch then saves to your `composer.json` for you (using `composer config extra.snitch.version <version>`).
- **GrumPHP task:** under consideration.

## Changelog

### Unreleased

- Added `snitch init`, which creates a default `.betterleaks.toml` (extending Betterleaks' defaults and skipping common noisy directories) and an empty `.betterleaksignore` file.
- Expanded the default ignored directories in the generated config.

### 1.1.0

- Support choosing the Betterleaks version via `composer.json` (`extra.snitch.version`).

### 1.0.1

- Minor fixes and improvements.

### 1.0.0

- Initial release.

## Credits

Secret detection is done entirely by [Betterleaks](https://github.com/betterleaks/betterleaks). Snitch just makes it easy to use in PHP projects.

## License

MIT

---

> A talebearer reveals secrets, but he who is of a faithful spirit conceals a matter.
>
> — Proverbs 11:13 (NKJV)