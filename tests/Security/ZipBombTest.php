<?php

declare(strict_types=1);

use ZipWarden\ErrorCode;
use ZipWarden\Limits;
use ZipWarden\Tests\Support\MaliciousZipFactory;
use ZipWarden\ZipWarden;

it('rejects a high-ratio entry with RATIO_EXCEEDED', function (): void {
    withWorkDir(function (string $dir): void {
        $zip = MaliciousZipFactory::createAll($dir)['bomb'];

        expect(errorCodeOf(fn () => (new ZipWarden)->extract($zip, "$dir/out")))->toBe(ErrorCode::RatioExceeded);
    });
});

it('stops streaming as soon as the limit is crossed and removes partial output', function (): void {
    withWorkDir(function (string $dir): void {
        // The header claims 10 bytes (the limit) but the entry really holds 1 MiB.
        $zip = writeZip("$dir/lying.zip", [
            ['name' => 'ok.txt', 'data' => 'fine'],
            ['name' => 'big.txt', 'data' => str_repeat('A', 1024 * 1024), 'declaredSize' => 10],
        ]);

        $code = errorCodeOf(fn () => (new ZipWarden(new Limits(maxEntrySize: 10)))->extract($zip, "$dir/out"));

        expect($code)->toBe(ErrorCode::EntrySizeExceeded)
            ->and(file_exists("$dir/out"))->toBeFalse()
            ->and(glob("$dir/.zipwarden-*"))->toBe([]);
    });
});

it('rejects a nested archive with NESTED_ARCHIVE when maxNestingDepth is 0', function (): void {
    withWorkDir(function (string $dir): void {
        $zip = MaliciousZipFactory::createAll($dir)['nested-bomb'];

        expect(errorCodeOf(fn () => (new ZipWarden)->extract($zip, "$dir/out")))->toBe(ErrorCode::NestedArchive)
            ->and(file_exists("$dir/out"))->toBeFalse()
            ->and(glob("$dir/.zipwarden-*"))->toBe([]);
    });
});

it('still catches the bomb inside a nested archive when nesting is allowed', function (): void {
    withWorkDir(function (string $dir): void {
        $zip = MaliciousZipFactory::createAll($dir)['nested-bomb'];
        $warden = new ZipWarden(new Limits(maxNestingDepth: 1));

        expect(errorCodeOf(fn () => $warden->extract($zip, "$dir/out")))->toBe(ErrorCode::RatioExceeded)
            ->and(file_exists("$dir/out"))->toBeFalse();
    });
});

it('shares the total size budget across nesting levels when nesting is allowed', function (): void {
    withWorkDir(function (string $dir): void {
        $inner = MaliciousZipFactory::build([['name' => 'a.txt', 'data' => str_repeat('a', 100)]]);
        $zip = writeZip("$dir/outer.zip", [['name' => 'inner.zip', 'data' => $inner, 'deflate' => false]]);
        $exact = strlen($inner) + 100;

        (new ZipWarden(new Limits(maxTotalUncompressedSize: $exact, maxNestingDepth: 1)))->extract($zip, "$dir/ok");

        $code = errorCodeOf(fn () => (new ZipWarden(new Limits(maxTotalUncompressedSize: $exact - 1, maxNestingDepth: 1)))
            ->extract($zip, "$dir/no"));

        expect(is_file("$dir/ok/inner.zip"))->toBeTrue()
            ->and($code)->toBe(ErrorCode::TotalSizeExceeded)
            ->and(file_exists("$dir/no"))->toBeFalse();
    });
});
