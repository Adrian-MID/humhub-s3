# Changelog

All notable changes to this project are documented here. Version numbers follow [Semantic Versioning](https://semver.org/).

## 2.0.1 - 2026-07-10

After the 2.0 move to direct S3 URLs, notification emails sent by the cron worker could fail before any message was delivered. HumHub loads a class-map override so richtext attachment links in emails keep their presigned S3 URLs instead of being rewritten into HumHub file routes. That override still declared the old untyped method signature, while HumHub 1.18 now requires typed `LinkParserBlock` parameters. PHP treats that mismatch as a fatal error when the class loads, which is exactly when the worker tries to render email HTML.

This patch aligns the override with HumHub 1.18. Queued and cron-driven emails work again, and presigned S3 attachment links in email HTML are still preserved.

### Fixed

- `RichTextToEmailHtmlConverter` override now matches HumHub 1.18's typed `LinkParserBlock` signature, fixing fatal errors when the cron worker sends notification emails.
- Presigned S3 attachment URLs in email richtext are still left unchanged (not tokenized into HumHub file routes).

## 2.0.0 - 2026-07-09

This release changes how HumHub hands files to people when S3 is enabled. HumHub still decides who may see a private attachment, but it no longer streams file bytes through PHP for normal delivery. Your bucket does that work instead. The goal is to support ephemeral environments like containers and auto-scaling app nodes, where local disk is temporary and PHP should not handle every image or download.

**Before 2.0**, logos, profile pictures, and similar public images always passed through a HumHub media proxy. Private attachments could be read from disk on the server. That was workable on a single long-lived server, but it kept copies on every app node and added PHP load. That is a poor fit when nodes are short-lived or scaled horizontally.

**From 2.0 onward**, HumHub checks permissions, then points browsers and email clients at S3.

- Public media (logo, icons, login background, mail header, profile pictures, banners) uses stable direct bucket URLs.
- Private attachments still need a HumHub permission check first. After that, HumHub issues a short-lived presigned S3 link.
- The HumHub server only keeps files briefly while an upload or image conversion runs. Once the object is in S3, the local copy is removed.

No HumHub core files need to be edited. The module hooks in at runtime through events, class maps, and a swapped file download controller.

Version 2.0 is tested and officially supported on HumHub 1.18 and later. Use the 1.x release line on older HumHub versions.

### Added

- Direct public bucket URLs for branding assets (logo, icons, login background, mail header) and profile images/banners.
- Presigned S3 GET URLs for private File module downloads after HumHub permission checks (configurable TTL, default 900 seconds).
- Bucket policy template and test in the admin UI under the **Bucket Policy** tab.
- Admin settings split into **General**, **Bucket Policy**, and **Processing Cache** tabs.
- Temporary processing files are deleted after upload or on request shutdown.

### Changed

- HumHub loads public media via `getUrl()` class-map overrides pointing at S3 object URLs instead of the HumHub media proxy.
- Local runtime storage is processing-only. Files are not kept on disk after they are synced to S3.
- Private file downloads redirect to S3 instead of streaming through PHP.

### Removed

- Media proxy (`MediaController`, `MediaProxyRoute`).
- **Media Proxy Path** admin setting.
- Separate **Empty S3 Processing Cache** entry in the settings menu (the same action lives on the **Processing Cache** tab).

### Migration

- Requires HumHub 1.18 or later. Stay on `^1.2` if your HumHub version is below 1.18.
- Apply the bucket policy from **Administration → Settings → HumHub S3 → Bucket Policy** before you rely on public images. Anonymous read should only cover `branding/*` and `profile_image/*` under your configured prefix.
- Remove any reverse-proxy or nginx rules pointing at the old media proxy path.
- Run **Test Connection** on the **General** tab and **Test Bucket Policy** on the **Bucket Policy** tab.
- Re-upload branding and profile images if they still only exist on local disk. The module does not migrate old files automatically.
- Verify uploads, branding, profile images, and private downloads after upgrade.

## 1.2.5 - 2026-07-09

### Added

- Admin control to empty the local runtime cache in `protected/runtime/humhub-s3`, with guidance on when to use it (for example, after branding assets are uploaded on another ephemeral server sharing the same S3 bucket).
- **Empty S3 Local Store** link in Administration → Settings for the same cache-clearing action.

## 1.2.0 - 2026-07-08
- Incremented MINOR version number for Packagist release index debugging.

## 1.1.1 - 2026-07-08
- Incremented PATCH version number for Packagist release index debugging.

## 1.1.0 - 2026-07-08

### Added

- Profile pictures, cover banners, and site branding (logo, favicon, login background, email header) can now be stored in S3 alongside file attachments, not just uploads from the Files module.
- A public media URL serves profile and branding images through HumHub, so they still work for visitors who are not logged in, similar to the old `/uploads/` behaviour.
- **Media Proxy Path** setting in the admin panel to choose a custom URL for those public assets (for example `publicassets` instead of the default module path).
- Fallback to existing local files when older media has not been re-uploaded since S3 was enabled.

### Changed

- File attachments remain private and continue to use HumHub’s normal permission checks. Only profile and branding assets are served publicly.

## 1.0.0 - 2026-07-01

### Added

- Version 1.0 Published
