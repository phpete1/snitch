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

Snitch currently uses Betterleaks 1.9.0. Support for choosing a version in your `composer.json` is planned.

## Known limitations

- The scan includes `vendor`, which can produce a lot of noise. Support for excluding paths is coming.
- The binary is stored inside `vendor`, so it is downloaded again after the package is updated or reinstalled. The first run also needs write access to `vendor`, which can fail in images where it is read-only.

## Credits

Secret detection is done entirely by [Betterleaks](https://github.com/betterleaks/betterleaks). Snitch just makes it easy to use in PHP projects.

## License

MIT

---

> A talebearer reveals secrets, but he who is of a faithful spirit conceals a matter.
>
> — Proverbs 11:13 (NKJV)