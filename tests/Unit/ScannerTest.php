<?php

declare(strict_types=1);

use ZipWarden\ErrorCode;
use ZipWarden\Limits;
use ZipWarden\Policy;
use ZipWarden\Scanner;
use ZipWarden\Tests\Support\MaliciousZipFactory;

const FIFO = 0010644;

it('returns normalised entries in central-directory order', function (): void {
    $entries = scanEntries([
        ['name' => 'docs/', 'data' => ''],
        ['name' => 'docs/a.txt', 'data' => 'hello'],
    ]);

    expect($entries)->toHaveCount(2)
        ->and($entries[0]->isDirectory)->toBeTrue()
        ->and($entries[1]->path)->toBe('docs/a.txt')
        ->and($entries[1]->size)->toBe(5);
});

it('rejects hostile names read from the raw central directory', function (string $name, ErrorCode $code): void {
    expect(errorCodeOf(fn () => scanEntries([['name' => $name, 'data' => 'x']])))->toBe($code);
})->with([
    'zip slip' => ['../evil.txt', ErrorCode::PathTraversal],
    'zip slip backslash' => ['..\\evil.txt', ErrorCode::PathTraversal],
    'absolute path' => ['/tmp/evil.txt', ErrorCode::AbsolutePath],
    'nul byte' => ["safe.txt\0.exe", ErrorCode::NullByte],
]);

it('rejects symlinks and special files', function (int $mode, ErrorCode $code): void {
    expect(errorCodeOf(fn () => scanEntries([['name' => 'x', 'data' => 'y', 'mode' => $mode]])))->toBe($code);
})->with([
    'symlink' => [0120777, ErrorCode::Symlink],
    'fifo' => [FIFO, ErrorCode::SpecialFile],
    'socket' => [0140644, ErrorCode::SpecialFile],
]);

it('rejects duplicate entries ignoring case', function (): void {
    $entries = [['name' => 'a.txt', 'data' => '1'], ['name' => 'A.TXT', 'data' => '2']];

    expect(errorCodeOf(fn () => scanEntries($entries)))->toBe(ErrorCode::DuplicateEntry);
});

it('accepts exactly maxEntries and rejects one more', function (): void {
    $make = fn (int $n): array => array_map(fn (int $i): array => ['name' => "f$i.txt", 'data' => 'x'], range(1, $n));
    $limits = new Limits(maxEntries: 3);

    expect(scanEntries($make(3), $limits))->toHaveCount(3)
        ->and(errorCodeOf(fn () => scanEntries($make(4), $limits)))->toBe(ErrorCode::TooManyEntries);
});

it('accepts a declared entry size at maxEntrySize and rejects one more', function (): void {
    $limits = new Limits(maxEntrySize: 10);

    expect(scanEntries([['name' => 'a', 'data' => str_repeat('x', 10)]], $limits))->toHaveCount(1)
        ->and(errorCodeOf(fn () => scanEntries([['name' => 'a', 'data' => str_repeat('x', 11)]], $limits)))
        ->toBe(ErrorCode::EntrySizeExceeded);
});

it('rejects a high declared ratio only above 1 MiB', function (): void {
    $small = [['name' => 'a', 'data' => str_repeat("\0", 512 * 1024)]];
    $bomb = [['name' => 'a', 'data' => str_repeat("\0", 20 * 1024 * 1024)]];

    expect(scanEntries($small, new Limits(maxRatio: 10)))->toHaveCount(1)
        ->and(errorCodeOf(fn () => scanEntries($bomb)))->toBe(ErrorCode::RatioExceeded);
});

it('rejects archives larger than maxArchiveSize', function (): void {
    $bytes = MaliciousZipFactory::build([['name' => 'a', 'data' => 'x']]);

    expect(scanBytes($bytes, new Limits(maxArchiveSize: strlen($bytes))))->toHaveCount(1)
        ->and(errorCodeOf(fn () => scanBytes($bytes, new Limits(maxArchiveSize: strlen($bytes) - 1))))
        ->toBe(ErrorCode::ArchiveTooLarge);
});

it('applies the extension allowlist to files but not directories', function (): void {
    $policy = new Policy(['txt']);
    $dir = ['name' => 'a.exe/', 'data' => ''];

    expect(scanEntries([$dir, ['name' => 'a.exe/NOTE.TXT', 'data' => 'x']], policy: $policy))->toHaveCount(2)
        ->and(errorCodeOf(fn () => scanEntries([['name' => 'report.pdf.exe', 'data' => 'x']], policy: $policy)))
        ->toBe(ErrorCode::ExtensionNotAllowed)
        ->and(errorCodeOf(fn () => scanEntries([['name' => 'Makefile', 'data' => 'x']], policy: $policy)))
        ->toBe(ErrorCode::ExtensionNotAllowed)
        ->and(scanEntries([['name' => 'Makefile', 'data' => 'x']], policy: new Policy([''])))->toHaveCount(1);
});

it('rejects garbage and truncated archives as INVALID_ARCHIVE', function (): void {
    $valid = MaliciousZipFactory::build([['name' => 'a', 'data' => 'x']]);

    expect(errorCodeOf(fn () => scanBytes('not a zip at all')))->toBe(ErrorCode::InvalidArchive)
        ->and(errorCodeOf(fn () => scanBytes(substr($valid, 0, -10))))->toBe(ErrorCode::InvalidArchive);
});

it('rejects a missing file as IO_ERROR', function (): void {
    expect(errorCodeOf(fn () => (new Scanner(new Limits, new Policy))->open('/nonexistent/x.zip')))
        ->toBe(ErrorCode::IoError);
});
