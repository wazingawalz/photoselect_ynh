# PhotoSelect – YunoHost package (development scaffold)

This repository is an early development scaffold, not a production-ready photo proofing service.

## Current prototype
- First-run administrator creation and login
- Album creation with password
- JPEG/PNG/WebP uploads
- Client album password gate
- Heart selection
- Selection quota notice (no billing or payment)
- Preview generation with a visible text watermark when Imagick is available
- SQLite database stored outside the public web root

## Important limitations
- No multi-client identities or separate selections per client yet.
- No final-selection confirmation workflow yet.
- Download toggle is stored but downloads are not implemented.
- The watermark is a basic "PREVIEW" text mark, not a configurable logo.
- The storage manager and external-disk selection are not implemented yet. Do not point the app at arbitrary filesystem paths.
- No production security audit has been performed. Treat as a test build and do not upload real client photos.
- Backups must include `/var/lib/photoselect`; the remove script deliberately retains data.

## Install
Use the repository URL in YunoHost's custom app installer only after reviewing and testing the package. This initial package is intended for development and may require adjustments for the exact YunoHost/PHP version.
