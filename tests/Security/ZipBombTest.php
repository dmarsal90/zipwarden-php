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
    // Fixture: nested-bomb
})->skip('pending: nested archives are handled in phase 3');

it('shares the total size budget across nesting levels when nesting is allowed', function (): void {
    // Fixture: nested-bomb
})->skip('pending: nested archives are handled in phase 3');
