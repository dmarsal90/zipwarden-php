<?php

declare(strict_types=1);

use ZipWarden\ErrorCode;
use ZipWarden\Tests\Support\MaliciousZipFactory;
use ZipWarden\ZipWarden;

it('rejects names containing a NUL byte with NULL_BYTE', function (): void {
    withWorkDir(function (string $dir): void {
        $zip = MaliciousZipFactory::createAll($dir)['null-byte'];

        expect(errorCodeOf(fn () => (new ZipWarden)->extract($zip, "$dir/out")))->toBe(ErrorCode::NullByte)
            ->and(file_exists("$dir/out"))->toBeFalse();
    });
});
