# ZipWarden for PHP

Safe ZIP reading and extraction. ZipWarden opens untrusted archives without
letting them write outside the target directory or exhaust your disk, memory
or CPU.

> **Status: in development.** Only the project structure, the specification and
> pending tests exist. The extraction logic is not implemented yet, so do not
> use this package to handle untrusted files. Nothing here is published.

## What it will do

- Reject zip slip: `../` segments, absolute paths, symbolic links and NUL bytes
  in names.
- Reject zip bombs: limits on compressed and uncompressed size, entry count,
  compression ratio, directory depth, nested archives and elapsed time.
- Extract as a stream into an isolated temporary directory, with an optional
  allowlist of file extensions.
- Work with any framework. An optional Laravel adapter (service provider and
  validation rule) lives in the separate `ZipWarden\Laravel` namespace, and the
  core never depends on Laravel.

The exact behaviour, default limits and error codes are in
[docs/SPEC.md](docs/SPEC.md). The [JavaScript package](https://github.com/dmarsal90/zipwarden-js)
follows the same specification.

## Planned installation

```bash
composer require dmarsal90/zipwarden
```

Requires PHP 8.2+ with `ext-zip`. Tested on 8.2, 8.3, 8.4 and 8.5.

Use a PHP build that includes the October 2026 security fixes (CVE-2026-109618
and CVE-2026-109619): 8.2.35, 8.3.36, 8.4.27, 8.5.12 or later.
<!-- TODO: confirm the Packagist package name before publishing. -->

## Planned usage (draft API)

This API is a proposal and may change.

```php
use ZipWarden\Limits;
use ZipWarden\Policy;
use ZipWarden\ZipWarden;
use ZipWarden\Exception\UnsafeArchiveException;

$warden = new ZipWarden(
    limits: new Limits(maxTotalUncompressedSize: 50 * 1024 * 1024),
    policy: new Policy(allowedExtensions: ['pdf', 'png', 'jpg']),
);

try {
    $result = $warden->extract('/path/to/upload.zip');
    // $result->directory is a private temporary directory
    // $result->files lists what was extracted
} catch (UnsafeArchiveException $e) {
    // $e->errorCode is a stable ZipWarden\ErrorCode, e.g. ErrorCode::PathTraversal
}
```

Laravel validation rule (draft):

```php
use ZipWarden\Laravel\Rules\SafeZip;

$request->validate(['archive' => ['required', 'file', new SafeZip]]);
```

## Threats covered

| Threat | Example | Planned defence | Error code |
|---|---|---|---|
| Zip slip | `../../etc/cron.d/x` | Reject `..`; check the resolved path stays inside the destination | `PATH_TRAVERSAL` |
| Absolute path | `/var/www/shell.php` | Reject leading `/`, `\` and drive prefixes | `ABSOLUTE_PATH` |
| NUL byte in name | `safe.txt\0.php` | Reject any NUL byte | `NULL_BYTE` |
| Symlink escape | `link -> /etc/passwd` | Reject symlinks; never create or follow them | `SYMLINK` |
| Simple zip bomb | 1 entry, 20 MiB of zeros in 20 KiB | Count real output bytes; cap the ratio | `RATIO_EXCEEDED` |
| Nested zip bomb | A bomb inside a ZIP | Reject nesting by default; share the budget if allowed | `NESTED_ARCHIVE` |
| Lying size headers | Declares 1 byte, inflates to 1 GiB | Trust only streamed byte counts | `SIZE_MISMATCH` |
| Too many entries | 10 million empty files | Cap the entry count | `TOO_MANY_ENTRIES` |
| Deep directories | 5000 nested folders | Cap the depth | `DEPTH_EXCEEDED` |
| Slow archives | Crafted to burn CPU | Time limit checked between chunks | `TIMEOUT` |
| Dangerous file types | `.php`, `.exe` | Optional extension allowlist | `EXTENSION_NOT_ALLOWED` |

## Development

```bash
composer install
composer test      # Pest
composer lint      # Pint (check only); composer format fixes
composer analyse   # PHPStan, level max
```

## License

MIT. See [LICENSE](LICENSE).
