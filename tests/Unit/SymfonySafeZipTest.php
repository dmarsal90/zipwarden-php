<?php

declare(strict_types=1);

use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Validator\Validation;
use ZipWarden\Symfony\Constraints\SafeZip;
use ZipWarden\Symfony\Constraints\SafeZipValidator;
use ZipWarden\Tests\Support\MaliciousZipFactory;

/**
 * @return list<string> Violation messages for $value.
 */
function violationsOf(mixed $value): array
{
    $messages = [];
    foreach (Validation::createValidator()->validate($value, new SafeZip) as $violation) {
        $messages[] = (string) $violation->getMessage();
    }

    return $messages;
}

it('passes a safe uploaded ZIP', function (): void {
    withWorkDir(function (string $dir): void {
        $zip = writeZip("$dir/ok.zip", [['name' => 'a.txt', 'data' => 'hello']]);

        expect(violationsOf(new UploadedFile($zip, 'ok.zip', null, null, true)))->toBe([]);
    });
});

it('fails hostile archives with one generic message', function (string $fixture): void {
    withWorkDir(function (string $dir) use ($fixture): void {
        $zip = MaliciousZipFactory::createAll($dir)[$fixture];

        expect(violationsOf(new SplFileInfo($zip)))->toBe(['This file is not a safe ZIP archive.']);
    });
})->with(['zip-slip', 'symlink', 'null-byte', 'bomb']);

it('fails what is not an uploaded ZIP file', function (mixed $value): void {
    expect(violationsOf($value))->toHaveCount(1);
})->with([
    'a string path' => ['/etc/passwd'],
    'a missing file' => [new SplFileInfo('/nonexistent/x.zip')],
    'a text file' => [new SplFileInfo(__FILE__)],
]);

it('leaves empty values to NotBlank', function (mixed $value): void {
    expect(violationsOf($value))->toBe([]);
})->with([null, '']);

it('uses the custom message and its own validator class', function (): void {
    $constraint = new SafeZip(message: 'Nope.');

    expect($constraint->message)->toBe('Nope.')
        ->and($constraint->validatedBy())->toBe(SafeZipValidator::class);
});
