<?php

declare(strict_types=1);

use ZipWarden\ErrorCode;
use ZipWarden\Tests\Support\MaliciousZipFactory;
use ZipWarden\ZipWarden;

it('rejects symbolic link entries with SYMLINK', function (): void {
    withWorkDir(function (string $dir): void {
        $zip = MaliciousZipFactory::createAll($dir)['symlink'];

        expect(errorCodeOf(fn () => (new ZipWarden)->extract($zip, "$dir/out")))->toBe(ErrorCode::Symlink);
    });
});

it('never creates a symlink in the destination', function (): void {
    withWorkDir(function (string $dir): void {
        $zip = writeZip("$dir/mixed.zip", [
            ['name' => 'ok.txt', 'data' => 'fine'],
            ['name' => 'link', 'data' => '/etc/passwd', 'mode' => 0120777],
        ]);

        errorCodeOf(fn () => (new ZipWarden)->extract($zip, "$dir/out"));

        expect(file_exists("$dir/out"))->toBeFalse()
            ->and(is_link("$dir/out/link"))->toBeFalse();
    });
});
