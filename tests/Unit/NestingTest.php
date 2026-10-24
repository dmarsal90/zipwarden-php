<?php

declare(strict_types=1);

use ZipWarden\ErrorCode;
use ZipWarden\Limits;
use ZipWarden\Policy;
use ZipWarden\Tests\Support\MaliciousZipFactory;
use ZipWarden\ZipWarden;

/**
 * Wraps $content in $levels ZIP archives, each holding the previous one as inner.zip.
 */
function nestedZip(string $content, int $levels): string
{
    $bytes = MaliciousZipFactory::build([['name' => 'a.txt', 'data' => $content]]);
    for ($i = 0; $i < $levels; $i++) {
        $bytes = MaliciousZipFactory::build([['name' => 'inner.zip', 'data' => $bytes, 'deflate' => false]]);
    }

    return $bytes;
}

it('allows exactly maxNestingDepth levels and rejects one more', function (int $levels, int $max, ?ErrorCode $expected): void {
    withWorkDir(function (string $dir) use ($levels, $max, $expected): void {
        file_put_contents("$dir/a.zip", nestedZip('hello', $levels));
        $warden = new ZipWarden(new Limits(maxNestingDepth: $max));

        expect(errorCodeOf(fn () => $warden->extract("$dir/a.zip", "$dir/out")))->toBe($expected);
    });
})->with([
    'no nesting, plain archive' => [0, 0, null],
    'one level, nesting 0' => [1, 0, ErrorCode::NestedArchive],
    'one level, nesting 1' => [1, 1, null],
    'two levels, nesting 1' => [2, 1, ErrorCode::NestedArchive],
    'two levels, nesting 2' => [2, 2, null],
    'three levels, nesting 2' => [3, 2, ErrorCode::NestedArchive],
]);

it('keeps the nested archive as a regular file and extracts nothing from it', function (): void {
    withWorkDir(function (string $dir): void {
        file_put_contents("$dir/a.zip", nestedZip('hello', 1));

        $result = (new ZipWarden(new Limits(maxNestingDepth: 1)))->extract("$dir/a.zip", "$dir/out");

        expect($result->files)->toBe(['inner.zip'])
            ->and(scandir("$dir/out"))->toBe(['.', '..', 'inner.zip']);
    });
});

it('detects a nested archive by content, not by name', function (): void {
    withWorkDir(function (string $dir): void {
        $inner = MaliciousZipFactory::build([['name' => 'a.txt', 'data' => 'x']]);
        $zip = writeZip("$dir/a.zip", [['name' => 'photo.jpg', 'data' => $inner, 'deflate' => false]]);

        expect(errorCodeOf(fn () => (new ZipWarden)->extract($zip, "$dir/out")))->toBe(ErrorCode::NestedArchive);
    });
});

it('applies the policy and the entry budget to nested entries', function (): void {
    withWorkDir(function (string $dir): void {
        $inner = MaliciousZipFactory::build([['name' => 'a.exe', 'data' => 'x']]);
        $zip = writeZip("$dir/a.zip", [['name' => 'inner.zip', 'data' => $inner, 'deflate' => false]]);
        $nesting = new Limits(maxNestingDepth: 1);

        expect(errorCodeOf(fn () => (new ZipWarden($nesting, new Policy(['zip'])))->extract($zip, "$dir/p")))
            ->toBe(ErrorCode::ExtensionNotAllowed)
            ->and(errorCodeOf(fn () => (new ZipWarden(new Limits(maxEntries: 1, maxNestingDepth: 1)))->extract($zip, "$dir/e")))
            ->toBe(ErrorCode::TooManyEntries);
    });
});

it('rejects a maxNestingDepth outside 0 to 2', function (int $value): void {
    expect(fn () => new Limits(maxNestingDepth: $value))->toThrow(InvalidArgumentException::class);
})->with([-1, 3]);
