# Changelog

All notable changes to this project are documented here. The format follows
[Keep a Changelog](https://keepachangelog.com/en/1.1.0/) and the project uses
[Semantic Versioning](https://semver.org/).

## [Unreleased]

### Added

- Pre-scan of the central directory: raw entry names, types, encryption,
  duplicates, extension allowlist, entry count, archive size and declared
  sizes.
- Streaming extraction into a private staging directory with byte counters,
  size and CRC-32 checks, a time budget, exclusive file creation and cleanup on
  failure.
- Validation of nested archives within the shared limits (`maxNestingDepth`
  from 0 to 2).
- `ZipWarden::validate()` and the Laravel `SafeZip` rule.
- PHP 8.5 in the CI matrix, and a README note on the PHP builds that include
  the October 2026 security fixes.

## [0.1.0-alpha.0] - 2026-10-21

Name reservation. Scaffold only: extraction is not implemented, do not use this version.

### Added

- Project scaffold: value objects, error codes, exception types and a
  placeholder Laravel adapter. No extraction logic yet.
- Specification in `docs/SPEC.md`.
- Test setup, a malicious ZIP generator and pending security tests.
- CI workflow (lint, static analysis, tests).
