<?php

declare(strict_types=1);

use ZipWarden\ErrorCode;
use ZipWarden\Tests\Support\MaliciousZipFactory;
use ZipWarden\ZipWarden;

it('rejects an entry whose real size exceeds its declared size with SIZE_MISMATCH', function (): void {
    withWorkDir(function (string $dir): void {
        $zip = MaliciousZipFactory::createAll($dir)['false-size'];

        expect(errorCodeOf(fn () => (new ZipWarden)->extract($zip, "$dir/out")))->toBe(ErrorCode::SizeMismatch)
            ->and(file_exists("$dir/out"))->toBeFalse();
    });
});

it('rejects an entry whose CRC-32 does not match its content', function (): void {
    withWorkDir(function (string $dir): void {
        $bytes = MaliciousZipFactory::build([['name' => 'a.txt', 'data' => 'hello world', 'deflate' => false]]);
        file_put_contents("$dir/crc.zip", str_replace('hello world', 'hellX world', $bytes));

        expect(errorCodeOf(fn () => (new ZipWarden)->extract("$dir/crc.zip", "$dir/out")))->toBe(ErrorCode::SizeMismatch);
    });
});
