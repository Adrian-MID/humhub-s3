# Changelog

All notable changes to this project are documented here. Version numbers follow [Semantic Versioning](https://semver.org/).

## 1.1.0 - 2026-07-08

### Added

- Profile pictures, cover banners, and site branding (logo, favicon, login background, email header) can now be stored in S3 alongside file attachments — not just uploads from the Files module.
- A public media URL serves profile and branding images through HumHub, so they still work for visitors who are not logged in, similar to the old `/uploads/` behaviour.
- **Media Proxy Path** setting in the admin panel to choose a custom URL for those public assets (for example `publicassets` instead of the default module path).
- Fallback to existing local files when older media has not been re-uploaded since S3 was enabled.

### Changed

- File attachments remain private and continue to use HumHub’s normal permission checks - only profile and branding assets are served publicly.

## 1.0.0 - 2026-07-01

### Added

- Version 1.0 Published
