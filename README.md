# HumHub S3

[![Code Quality](https://github.com/Adrian-MID/humhub-s3/actions/workflows/code-quality.yml/badge.svg)](https://github.com/Adrian-MID/humhub-s3/actions/workflows/code-quality.yml)
[![Packagist Version](https://img.shields.io/packagist/v/adrian-mid/humhub-s3)](https://packagist.org/packages/adrian-mid/humhub-s3)
[![License](https://img.shields.io/github/license/Adrian-MID/humhub-s3)](https://github.com/Adrian-MID/humhub-s3/blob/main/LICENSE)

Store HumHub file uploads in Amazon S3 or an S3-compatible service (MinIO, Wasabi, etc.) instead of the local filesystem.

## Features

- Drop-in replacement for HumHub's default file storage
- S3-backed profile images, banners, and site branding (logo, icons, login background, mail header)
- Works with AWS S3 and S3-compatible endpoints
- Write-through local runtime cache for compatibility with HumHub's file and image handling
- Public media proxy for profile/branding assets (same behaviour as legacy `/uploads/` URLs)
- Admin UI with connection testing before enabling
- Optional credentials from environment variables

## Requirements

- HumHub 1.14 or later. 
- PHP 8.0 or later (with `curl` extension)
- An S3 bucket and IAM credentials with appropriate permissions

## Installation

Use a separate Composer project in `protected/modules/`. The module installs to `protected/modules/humhub-s3/` and its dependencies go to `protected/modules/vendor/`, leaving HumHub's `protected/vendor/` tree untouched.

Do not run `composer init` or `composer require` at the HumHub web root.

**One-time setup** - copy the Composer scaffold (once per HumHub instance):

```bash
cd /path/to/humhub/protected/modules
curl -fsSL https://raw.githubusercontent.com/Adrian-MID/humhub-s3/main/modules.composer.json -o composer.json
```

**Install or update:**

```bash
cd /path/to/humhub/protected/modules
composer require adrian-mid/humhub-s3:^1.1
php ../yii cache/flush-all
```

Enable **HumHub S3** under *Administration → Modules*.

> HumHub 1.16+ processes module disable and removal via background queue jobs. Ensure cron is running (`php protected/yii cron/run`) or run the queue worker manually (`php protected/yii queue/run`).

## Configuration

| Setting | Description |
|---------|-------------|
| Enable HumHub S3 | Activates remote storage for new uploads |
| Bucket Name | Target S3 bucket (lowercase, 3–63 characters) |
| AWS Region | e.g. `ap-southeast-2` |
| Access Key ID | IAM access key |
| Secret Access Key | IAM secret key (leave blank when saving to keep the existing key) |
| Object Prefix | Optional folder prefix inside the bucket (default: `humhub`) |
| Media Proxy Path | Single URL segment for public assets, letters and numbers only (default: module path `humhub-s3/media/serve`) |
| Custom Endpoint | Leave empty for AWS S3 |
| Use path-style URLs | Enable for most S3-compatible endpoints |

Use **Test Connection** to verify upload, download, and delete operations before enabling storage.

HTTP endpoints are only allowed for `localhost`. Metadata IP addresses (e.g. `169.254.x.x`) are blocked.

### Environment variables

If you prefer to keep your AWS API key out of the database, you can configure optional environment variable names for credentials (e.g. `AWS_ACCESS_KEY_ID`, `AWS_SECRET_ACCESS_KEY`). When set and present on the server, they take precedence over database-stored values and database stored values can be empty.

Replace `YOUR-BUCKET` and adjust the prefix if you changed the default.

## How it works

HumHub uses two separate storage paths. This module replaces both when enabled and configured.

### File module attachments (private)

HumHub stores uploaded files through a pluggable `StorageManager`. When S3 is active, the module swaps in `S3StorageManager`.

Files are written to a local runtime cache first, then synced to S3. Downloads prefer the cache and fetch from S3 on demand. HumHub continues to handle access control through `File::canView()` - objects are not exposed via public S3 URLs.

Object keys:

```
{prefix}/{guid[0]}/{guid[1]}/{guid}/{variant}
```

Example: `humhub/a/3/a3f2…/file`

### Profile images and site branding (public)

HumHub core stores profile images, banners, logos, icons, and similar assets outside the File module, directly under `webroot/uploads/` or the asset manager. On ephemeral containers those paths are lost between restarts.

When S3 is active, the module:

1. Replaces HumHub's media helper classes with S3-backed implementations (via Yii class maps)
2. Stores objects in S3 under predictable keys (see below)
3. Serves them through a configurable read-only proxy (default: `/humhub-s3/media/serve`) with strict path validation

The media proxy is **publicly accessible without login**, matching legacy nginx behaviour for `/uploads/profile_image/` and branding assets. File module downloads still require authentication and permission checks.

Media object keys:

```
{prefix}/profile_image/{guid}.jpg
{prefix}/profile_image/{guid}_org.jpg
{prefix}/profile_image/banner/{guid}.jpg
{prefix}/branding/logo.png
{prefix}/branding/icon/{size}x{size}.png
{prefix}/branding/login-bg.png
{prefix}/branding/mail-header.png
```

Example proxy URLs (with default path):

```
/humhub-s3/media/serve?type=profile&guid={guid}&variant=
/humhub-s3/media/serve?type=banner&guid={guid}&variant=_org
/humhub-s3/media/serve?path=branding/icon/192x192.png
```

Set **Media Proxy Path** to a single alphanumeric segment (e.g. `s3media`) to use `/s3media?path=...` instead. Slashes and special characters are not allowed, and paths already used by HumHub are rejected.

## Limitations

- **Existing files are not migrated.** Files uploaded before enabling S3 remain on the local filesystem. Legacy paths are used as a fallback when serving media until re-uploaded.
- **Large files are streamed** during upload and download, but very large files still depend on PHP and web server limits.
- **No multipart upload.** Very large files use a single PUT request.

## Development

This module is maintained with strict static analysis and coding standards:

- **PHPStan level 10** - `composer phpstan`
- **PHP CS Fixer** (PER-CS + PHP 8.2 migration rules) - `composer cs:check` / `composer cs:fix`

Run all checks:

```bash
composer install
composer lint
```

CI runs the same checks on push and pull requests via GitHub Actions.

## Uninstall

Disabling or removing the module reverts to local filesystem storage and deletes all module settings (including stored credentials). On HumHub 1.16+, ensure the queue/cron is running so the background job completes.

## License

GPL-3.0-or-later - see [LICENSE](LICENSE).

## Changelog

See [CHANGELOG.md](CHANGELOG.md).


