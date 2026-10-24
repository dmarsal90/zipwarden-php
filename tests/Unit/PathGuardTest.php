<?php

declare(strict_types=1);

use ZipWarden\ErrorCode;
use ZipWarden\PathGuard;

it('rejects unsafe names with the documented code', function (string $name, ErrorCode $code): void {
    expect(errorCodeOf(fn () => PathGuard::normalize($name, 16)))->toBe($code);
})->with([
    'nul byte' => ["safe.txt\0.exe", ErrorCode::NullByte],
    'empty' => ['', ErrorCode::InvalidPath],
    'control character' => ["a\x07b", ErrorCode::InvalidPath],
    'only dots and slashes' => ['./', ErrorCode::InvalidPath],
    'leading slash' => ['/etc/passwd', ErrorCode::AbsolutePath],
    'leading backslash' => ['\\evil.txt', ErrorCode::AbsolutePath],
    'drive prefix' => ['C:evil.txt', ErrorCode::AbsolutePath],
    'parent segment' => ['../evil.txt', ErrorCode::PathTraversal],
    'parent in the middle' => ['a/../../evil.txt', ErrorCode::PathTraversal],
    'parent with backslashes' => ['a\\..\\evil.txt', ErrorCode::PathTraversal],
    'invalid utf-8' => ["caf\xE9.txt", ErrorCode::InvalidPath],
]);

it('normalises safe names', function (string $name, string $path, bool $isDirectory): void {
    expect(PathGuard::normalize($name, 16))->toBe(['path' => $path, 'isDirectory' => $isDirectory]);
})->with([
    'plain' => ['a/b/c.txt', 'a/b/c.txt', false],
    'directory' => ['dir/', 'dir', true],
    'current dir segment' => ['./a.txt', 'a.txt', false],
    'doubled slash' => ['a//b.txt', 'a/b.txt', false],
    'backslashes' => ['a\\b.txt', 'a/b.txt', false],
    'utf-8' => ['café.txt', 'café.txt', false],
]);

it('allows exactly maxDepth directory segments and rejects one more', function (): void {
    expect(PathGuard::normalize('a/b/f.txt', 2)['path'])->toBe('a/b/f.txt')
        ->and(PathGuard::normalize('a/b/', 2)['path'])->toBe('a/b')
        ->and(errorCodeOf(fn () => PathGuard::normalize('a/b/c/f.txt', 2)))->toBe(ErrorCode::DepthExceeded)
        ->and(errorCodeOf(fn () => PathGuard::normalize('a/b/c/', 2)))->toBe(ErrorCode::DepthExceeded);
});
