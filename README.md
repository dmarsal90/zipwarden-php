# ZipWarden for PHP

Safe ZIP reading and extraction. ZipWarden opens untrusted archives without
letting them write outside the target directory or exhaust your disk, memory
or CPU.

> **Status: pre-release.** The extraction is implemented and covered by tests
> that build malicious archives, but it has not had an independent security
> review and nothing is published yet. Review it yourself before trusting it
> with hostile files.

## What it does

- Reject zip slip: `../` segments, absolute paths, symbolic links and NUL bytes
  in names.
- Reject zip bombs: limits on compressed and uncompressed size, entry count,
  compression ratio, directory depth, nested archives and elapsed time.
- Extract as a stream into a private staging directory and move it to the
  destination only when every entry passed. Any failure deletes everything
  written. An optional allowlist restricts file extensions.
- Work with any framework. An optional Laravel adapter (service provider and
  validation rule) lives in the separate `ZipWarden\Laravel` namespace, and the
  core never depends on Laravel.

The exact behaviour, default limits and error codes are in
[docs/SPEC.md](docs/SPEC.md). The [JavaScript package](https://github.com/dmarsal90/zipwarden-js)
follows the same specification.

## Installation

```bash
composer require dmarsal90/zipwarden:^0.1@alpha
```

Requires PHP 8.2+ with `ext-zip`. Tested on 8.2, 8.3, 8.4 and 8.5.

Use a PHP build that includes the October 2026 security fixes (CVE-2026-109618
and CVE-2026-109619): 8.2.35, 8.3.36, 8.4.27, 8.5.12 or later.
<!-- TODO: confirm the Packagist package name before publishing. -->

## Usage

The API may still change before 1.0.

```php
use ZipWarden\Limits;
use ZipWarden\Policy;
use ZipWarden\ZipWarden;
use ZipWarden\Exception\ZipWardenException;

$warden = new ZipWarden(
    limits: new Limits(maxTotalUncompressedSize: 50 * 1024 * 1024),
    policy: new Policy(allowedExtensions: ['pdf', 'png', 'jpg']),
);

try {
    $result = $warden->extract('/path/to/upload.zip');
    // $result->directory is a private temporary directory (mode 0700)
    // $result->files lists what was extracted, relative to it
} catch (ZipWardenException $e) {
    // $e->errorCode is a stable ZipWarden\ErrorCode, e.g. ErrorCode::PathTraversal
}
```

Pass a second argument to extract into a directory of your choice. It must be
absent or empty, otherwise the error is `DESTINATION_NOT_EMPTY`, and existing
files are never overwritten. The directory is replaced by the finished tree, so
its mode is kept but its owner and group are not.

Files are created with mode `0644` and directories with `0755`, whatever the
archive says. Nested archives are rejected unless you raise `maxNestingDepth`
(0 to 2). When allowed, a nested archive is kept as a plain file and checked
with the same limits and policy, sharing the size and entry totals; its content
is not extracted.

Laravel validation rule:

```php
use ZipWarden\Laravel\Rules\SafeZip;

$request->validate(['archive' => ['required', 'file', new SafeZip]]);
```

`SafeZip` runs only the pre-scan (`ZipWarden::validate()`), which reads the
central directory and trusts the sizes declared there. It is a fast filter for
uploads, not a substitute for extracting with `ZipWarden`: an archive whose
headers lie about its sizes passes the rule and is stopped by `extract()`. The
rule accepts uploaded files only and never treats a string as a path. Pass your
own `new SafeZip(new ZipWarden($limits, $policy))` to change the limits.

## Threats covered

| Threat | Example | Defence | Error code |
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

## Known limits

- Entry names that are not valid UTF-8 are rejected, including names flagged as
  CP437, because `ZipArchive` does not expose that flag.
- Duplicate names are compared with ASCII case folding. A non-ASCII collision
  still fails when the second file is created, because files are created
  exclusively.
- The compression ratio uses the compressed size from the header. The entry and
  total size limits do not depend on it.
- Not tested with real Zip64 archives, multi-gigabyte inputs or Windows.

## Development

```bash
composer install
composer test      # Pest
composer lint      # Pint (check only); composer format fixes
composer analyse   # PHPStan, level max
```

## License

MIT. See [LICENSE](LICENSE).
