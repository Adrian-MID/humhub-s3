# HumHub S3

[![Code Quality](https://github.com/Adrian-MID/humhub-s3/actions/workflows/code-quality.yml/badge.svg)](https://github.com/Adrian-MID/humhub-s3/actions/workflows/code-quality.yml)
[![Packagist Version](https://img.shields.io/packagist/v/adrian-mid/humhub-s3)](https://packagist.org/packages/adrian-mid/humhub-s3)
[![License](https://img.shields.io/github/license/Adrian-MID/humhub-s3)](https://github.com/Adrian-MID/humhub-s3/blob/main/LICENSE)

Store HumHub file uploads in Amazon S3 or an S3-compatible service (MinIO, Wasabi, etc.) instead of the local filesystem.

## Features

- Drop-in replacement for HumHub's default file storage
- S3-backed profile images, banners, and site branding (logo, icons, login background, mail header)
- Works with AWS S3 and S3-compatible endpoints
- **Public media** served via direct bucket URLs (logo, icons, profile images)
- **Private attachments** served via presigned S3 URLs after HumHub permission checks
- Temporary local files only during upload and image processing
- Admin UI with connection testing before enabling
- Optional credentials from environment variables

## Requirements

- HumHub 1.18 or later. Version 2.x is tested and officially supported only on HumHub 1.18 and up.
- PHP 8.0 or later (with `curl` extension)
- An S3 bucket and IAM credentials with appropriate permissions

If you run HumHub below 1.18, stay on the 1.x release line (`composer require adrian-mid/humhub-s3:^1.2`).

## Installation

```bash
cd protected/modules
composer require adrian-mid/humhub-s3
```

## Upgrade to the latest version
```bash
cd protected/modules
composer require adrian-mid/humhub-s3^2.0
```

Enable **HumHub S3** under *Administration → Modules*.

> HumHub processes module disable and removal via background queue jobs. Ensure cron is running (`php protected/yii cron/run`) or run the queue worker manually (`php protected/yii queue/run`).

## Configuration

| Setting | Description |
|---------|-------------|
| Enable HumHub S3 | Activates remote storage for new uploads |
| Bucket Name | Target S3 bucket (lowercase, 3-63 characters) |
| AWS Region | e.g. `ap-southeast-2` |
| Access Key ID | IAM access key |
| Secret Access Key | IAM secret key (leave blank when saving to keep the existing key) |
| Object Prefix | Optional folder prefix inside the bucket (default `humhub`) |
| Presigned Download URL TTL | Lifetime in seconds for private file download links (default 900 seconds) |
| Custom Endpoint | Leave empty for AWS S3 |
| Use path-style URLs | Enable for most S3-compatible endpoints |

Use **Test Connection** to verify upload, download, and delete operations before enabling storage.

HTTP endpoints are only allowed for `localhost`. Metadata IP addresses (e.g. `169.254.x.x`) are blocked.

### Environment variables

If you prefer to keep your AWS API key out of the database, you can configure optional environment variable names for credentials (e.g. `AWS_ACCESS_KEY_ID`, `AWS_SECRET_ACCESS_KEY`). When set and present on the server, they take precedence over database-stored values and database stored values can be empty.

### Bucket policy (required)

HumHub needs two kinds of S3 access.

1. **HumHub IAM user** can read, write, and delete all objects under your configured prefix (private attachments, presigned downloads, connection tests).
2. **Anonymous public read** applies only to `branding/*` and `profile_image/*` (logos, icons, profile pictures).

Apply a bucket policy like the one shown in *Administration → Settings → HumHub S3* (adjust bucket, prefix, and IAM user ARN). Below is an example for bucket `your-bucket`, prefix `humhub`, and IAM user `arn:aws:iam::123456789012:user/humhub-media`.

```json
{
  "Version": "2012-10-17",
  "Statement": [
    {
      "Sid": "HumHubServiceObjectAccess",
      "Effect": "Allow",
      "Principal": {
        "AWS": "arn:aws:iam::123456789012:user/humhub-media"
      },
      "Action": [
        "s3:GetObject",
        "s3:PutObject",
        "s3:DeleteObject"
      ],
      "Resource": "arn:aws:s3:::your-bucket/humhub/*"
    },
    {
      "Sid": "HumHubServiceListAccess",
      "Effect": "Allow",
      "Principal": {
        "AWS": "arn:aws:iam::123456789012:user/humhub-media"
      },
      "Action": "s3:ListBucket",
      "Resource": "arn:aws:s3:::your-bucket",
      "Condition": {
        "StringLike": {
          "s3:prefix": [
            "humhub/*",
            "humhub"
          ]
        }
      }
    },
    {
      "Sid": "HumHubPublicMedia",
      "Effect": "Allow",
      "Principal": "*",
      "Action": "s3:GetObject",
      "Resource": [
        "arn:aws:s3:::your-bucket/humhub/branding/*",
        "arn:aws:s3:::your-bucket/humhub/profile_image/*"
      ]
    }
  ]
}
```

**Important.** Remove any existing policy statements that **explicitly deny** `s3:GetObject` for your HumHub IAM user outside the public prefixes. AWS evaluates Deny before Allow. A broad or misplaced Deny will block private uploads, downloads, and presigned URLs even when Allow statements are present.

File module attachments are **not** exposed anonymously. Only the HumHub IAM user can read those keys. Adjust S3 Block Public Access so scoped public read on branding and profile images is permitted.

## Upgrading from 1.x

Version 2.0 requires HumHub 1.18 or later. It removes the HumHub media proxy. After upgrading,

1. Confirm HumHub is 1.18 or later. If not, stay on `adrian-mid/humhub-s3` 1.x.
2. Add the bucket policy for `branding/*` and `profile_image/*` (see above).
3. Remove any reverse-proxy or nginx rules pointing at the old media proxy path (`/humhub-s3/media/serve` or your custom **Media Proxy Path**).
4. Verify branding and profile images load from S3 URLs in the browser.
5. Verify private file downloads redirect to S3 after login.

## Limitations

- **Existing files are not migrated.** Re-upload branding and profile images after enabling S3 so they exist in the bucket.
- **Large files** use a single PUT request (no multipart upload).
- **Very large files** still depend on PHP and web server limits during upload processing.

## Uninstall

Disabling or removing the module reverts to local filesystem storage and deletes all module settings (including stored credentials). Ensure the queue or cron is running so the background job completes.

## License

GPL-3.0-or-later. See [LICENSE](LICENSE).

## Changelog

See [CHANGELOG.md](CHANGELOG.md).
