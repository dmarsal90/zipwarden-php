# ZipWarden (PHP)

Safe reading and extraction of untrusted ZIP archives (zip slip and zip bomb
protection). Behaviour is defined in `docs/SPEC.md`, which is identical to the
one in the `zipwarden-js` sibling project. Change both together.

Status: pre-release. Pre-scan, streaming extraction, nested archives and the
`SafeZip` rule are implemented; no independent security review yet.

## Commands

- `composer install`
- `composer test` (Pest)
- `composer lint` (Pint, check only) and `composer format` (fix)
- `composer analyse` (PHPStan, level max)

## Conventions

- PHP 8.2+, `declare(strict_types=1)` in every file, PSR-4 (`ZipWarden\`).
- The core in `src/` must not import Laravel. Laravel code lives only in
  `src/Laravel` (namespace `ZipWarden\Laravel`).
- Use `ZipArchive` with streams; never read a whole entry into memory.
- Errors are `ZipWarden\Exception\ZipWardenException` carrying an `ErrorCode`.
  Codes are stable; messages are not.
- Tests build malicious ZIPs at runtime with `tests/Support/MaliciousZipFactory`.
  Do not commit binary ZIP files.
- Commits follow Conventional Commits.

## Hard rule

No extraction may write outside the destination directory. Resolve every path
and check it is inside the destination before writing, and never create or
follow symlinks. Any change that weakens this needs a test that proves it
still holds.
