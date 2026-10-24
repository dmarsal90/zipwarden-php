<?php

declare(strict_types=1);

use ZipWarden\ErrorCode;
use ZipWarden\Laravel\Rules\SafeZip;
use ZipWarden\Limits;
use ZipWarden\Tests\Support\MaliciousZipFactory;
use ZipWarden\ZipWarden;

/**
 * @return list<string> Failure messages the rule reported.
 */
function failuresOf(SafeZip $rule, mixed $value): array
{
    $messages = [];
    $rule->validate('archive', $value, function (string $message) use (&$messages): void {
        $messages[] = $message;
    });

    return $messages;
}

it('passes a safe uploaded ZIP', function (): void {
    withWorkDir(function (string $dir): void {
        $zip = writeZip("$dir/ok.zip", [['name' => 'a.txt', 'data' => 'hello']]);

        expect(failuresOf(new SafeZip, new SplFileInfo($zip)))->toBe([]);
    });
});

it('fails hostile archives with one generic message', function (string $fixture): void {
    withWorkDir(function (string $dir) use ($fixture): void {
        $zip = MaliciousZipFactory::createAll($dir)[$fixture];

        expect(failuresOf(new SafeZip, new SplFileInfo($zip)))->toBe(['The :attribute must be a safe ZIP archive.']);
    });
})->with(['zip-slip', 'symlink', 'null-byte', 'bomb']);

it('fails what is not an uploaded ZIP file', function (mixed $value): void {
    expect(failuresOf(new SafeZip, $value))->toHaveCount(1);
})->with([
    'null' => [null],
    'a string path' => ['/etc/passwd'],
    'a missing file' => [new SplFileInfo('/nonexistent/x.zip')],
    'a text file' => [new SplFileInfo(__FILE__)],
]);

it('uses the limits of the warden it receives', function (): void {
    withWorkDir(function (string $dir): void {
        $zip = writeZip("$dir/two.zip", [['name' => 'a', 'data' => 'x'], ['name' => 'b', 'data' => 'y']]);
        $rule = new SafeZip(new ZipWarden(new Limits(maxEntries: 1)));

        expect(failuresOf($rule, new SplFileInfo($zip)))->toHaveCount(1);
    });
});

it('validates without writing anything and reports the code', function (): void {
    withWorkDir(function (string $dir): void {
        $ok = writeZip("$dir/ok.zip", [['name' => 'a.txt', 'data' => 'hello']]);
        $slip = MaliciousZipFactory::createAll($dir)['zip-slip'];
        $before = scandir($dir);

        (new ZipWarden)->validate($ok);

        expect(errorCodeOf(fn () => (new ZipWarden)->validate($slip)))->toBe(ErrorCode::PathTraversal)
            ->and(scandir($dir))->toBe($before);
    });
});
