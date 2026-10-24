<?php

declare(strict_types=1);

use ZipWarden\ErrorCode;
use ZipWarden\Limits;
use ZipWarden\ZipWarden;

it('extracts files and directories with fixed modes', function (): void {
    withWorkDir(function (string $dir): void {
        $zip = writeZip("$dir/a.zip", [
            ['name' => 'docs/', 'data' => ''],
            ['name' => 'docs/a.txt', 'data' => 'hello'],
            ['name' => 'b.txt', 'data' => 'world', 'mode' => 0104755],
        ]);

        $result = (new ZipWarden)->extract($zip, "$dir/out");

        expect($result->directory)->toBe(realpath($dir).'/out')
            ->and($result->files)->toBe(['docs/a.txt', 'b.txt'])
            ->and(file_get_contents("$dir/out/docs/a.txt"))->toBe('hello')
            ->and(fileperms("$dir/out/b.txt") & 07777)->toBe(0644)
            ->and(fileperms("$dir/out/docs") & 07777)->toBe(0755)
            ->and(fileperms("$dir/out") & 07777)->toBe(0755)
            ->and(glob("$dir/.zipwarden-*"))->toBe([]);
    });
});

it('creates implicit parent directories', function (): void {
    withWorkDir(function (string $dir): void {
        $zip = writeZip("$dir/a.zip", [['name' => 'a/b/c.txt', 'data' => 'x']]);

        (new ZipWarden)->extract($zip, "$dir/out");

        expect(file_get_contents("$dir/out/a/b/c.txt"))->toBe('x');
    });
});

it('extracts into a private temporary directory by default', function (): void {
    withWorkDir(function (string $dir): void {
        $zip = writeZip("$dir/a.zip", [['name' => 'a.txt', 'data' => 'x']]);

        $result = (new ZipWarden)->extract($zip);

        try {
            expect(fileperms($result->directory) & 07777)->toBe(0700)
                ->and(file_get_contents($result->directory.'/a.txt'))->toBe('x');
        } finally {
            removeDir($result->directory);
        }
    });
});

it('accepts an existing empty destination and rejects anything else', function (): void {
    withWorkDir(function (string $dir): void {
        $zip = writeZip("$dir/a.zip", [['name' => 'a.txt', 'data' => 'new']]);
        mkdir("$dir/empty");
        mkdir("$dir/full");
        file_put_contents("$dir/full/keep.txt", 'old');
        file_put_contents("$dir/file", 'x');

        (new ZipWarden)->extract($zip, "$dir/empty");

        expect(file_get_contents("$dir/empty/a.txt"))->toBe('new')
            ->and(errorCodeOf(fn () => (new ZipWarden)->extract($zip, "$dir/full")))->toBe(ErrorCode::DestinationNotEmpty)
            ->and(file_get_contents("$dir/full/keep.txt"))->toBe('old')
            ->and(errorCodeOf(fn () => (new ZipWarden)->extract($zip, "$dir/file")))->toBe(ErrorCode::DestinationNotEmpty)
            ->and(errorCodeOf(fn () => (new ZipWarden)->extract($zip, "$dir/missing/out")))->toBe(ErrorCode::IoError);
    });
});

it('removes everything written when a later entry fails', function (): void {
    withWorkDir(function (string $dir): void {
        $zip = writeZip("$dir/a.zip", [
            ['name' => 'ok.txt', 'data' => 'fine'],
            ['name' => 'bad.txt', 'data' => 'abcdef', 'declaredSize' => 2],
        ]);
        $temporaryBefore = glob(sys_get_temp_dir().'/zipwarden-*');

        expect(errorCodeOf(fn () => (new ZipWarden)->extract($zip, "$dir/out")))->toBe(ErrorCode::SizeMismatch)
            ->and(file_exists("$dir/out"))->toBeFalse()
            ->and(glob("$dir/.zipwarden-*"))->toBe([])
            ->and(errorCodeOf(fn () => (new ZipWarden)->extract($zip)))->toBe(ErrorCode::SizeMismatch)
            ->and(glob(sys_get_temp_dir().'/zipwarden-*'))->toBe($temporaryBefore);
    });
});

it('accepts the total size exactly at the limit and rejects one byte more', function (): void {
    withWorkDir(function (string $dir): void {
        $zip = writeZip("$dir/a.zip", [
            ['name' => 'a.txt', 'data' => str_repeat('a', 10)],
            ['name' => 'b.txt', 'data' => str_repeat('b', 10)],
        ]);

        (new ZipWarden(new Limits(maxTotalUncompressedSize: 20)))->extract($zip, "$dir/ok");

        expect(is_file("$dir/ok/b.txt"))->toBeTrue()
            ->and(errorCodeOf(fn () => (new ZipWarden(new Limits(maxTotalUncompressedSize: 19)))->extract($zip, "$dir/no")))
            ->toBe(ErrorCode::TotalSizeExceeded);
    });
});

it('rejects an entry whose real size passes the limit even if the header is below it', function (): void {
    withWorkDir(function (string $dir): void {
        $exact = writeZip("$dir/exact.zip", [['name' => 'a.txt', 'data' => str_repeat('a', 10)]]);
        $over = writeZip("$dir/over.zip", [['name' => 'a.txt', 'data' => str_repeat('a', 11), 'declaredSize' => 10]]);
        $warden = new ZipWarden(new Limits(maxEntrySize: 10));

        $warden->extract($exact, "$dir/ok");

        expect(is_file("$dir/ok/a.txt"))->toBeTrue()
            ->and(errorCodeOf(fn () => $warden->extract($over, "$dir/no")))->toBe(ErrorCode::EntrySizeExceeded);
    });
});

it('aborts with TIMEOUT when the time budget is spent', function (): void {
    withWorkDir(function (string $dir): void {
        $zip = writeZip("$dir/a.zip", [['name' => 'a.bin', 'data' => str_repeat("\0", 4 * 1024 * 1024)]]);
        $warden = new ZipWarden(new Limits(maxRatio: PHP_INT_MAX, timeoutSeconds: 0));

        expect(errorCodeOf(fn () => $warden->extract($zip, "$dir/out")))->toBe(ErrorCode::Timeout)
            ->and(file_exists("$dir/out"))->toBeFalse();
    });
});
