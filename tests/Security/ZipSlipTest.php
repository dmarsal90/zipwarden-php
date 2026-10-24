<?php

declare(strict_types=1);

use ZipWarden\ErrorCode;
use ZipWarden\Tests\Support\MaliciousZipFactory;
use ZipWarden\ZipWarden;

it('rejects entries with ../ segments with PATH_TRAVERSAL', function (): void {
    withWorkDir(function (string $dir): void {
        $zip = MaliciousZipFactory::createAll($dir)['zip-slip'];

        expect(errorCodeOf(fn () => (new ZipWarden)->extract($zip, "$dir/out")))->toBe(ErrorCode::PathTraversal);
    });
});

it('rejects backslash-separated traversal with PATH_TRAVERSAL', function (): void {
    withWorkDir(function (string $dir): void {
        $zip = MaliciousZipFactory::createAll($dir)['zip-slip-backslash'];

        expect(errorCodeOf(fn () => (new ZipWarden)->extract($zip, "$dir/out")))->toBe(ErrorCode::PathTraversal);
    });
});

it('rejects absolute paths with ABSOLUTE_PATH', function (): void {
    withWorkDir(function (string $dir): void {
        $zip = MaliciousZipFactory::createAll($dir)['absolute-path'];

        expect(errorCodeOf(fn () => (new ZipWarden)->extract($zip, "$dir/out")))->toBe(ErrorCode::AbsolutePath);
    });
});

it('never writes any file outside the destination directory', function (): void {
    withWorkDir(function (string $dir): void {
        $zips = MaliciousZipFactory::createAll($dir);
        mkdir("$dir/dest");
        $before = scandir($dir);

        foreach (['zip-slip', 'zip-slip-backslash', 'absolute-path'] as $name) {
            errorCodeOf(fn () => (new ZipWarden)->extract($zips[$name], "$dir/dest/out"));
        }

        expect(scandir($dir))->toBe($before)
            ->and(scandir("$dir/dest"))->toBe(['.', '..'])
            ->and(file_exists('/tmp/evil.txt'))->toBeFalse();
    });
});
