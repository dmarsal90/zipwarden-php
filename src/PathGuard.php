<?php

declare(strict_types=1);

namespace ZipWarden;

use ZipWarden\Exception\UnsafeArchiveException;

/**
 * Validates and normalises entry names. See docs/SPEC.md, section 4.1.
 *
 * @internal
 */
final class PathGuard
{
    /**
     * @return array{path: string, isDirectory: bool} Path uses "/" and has no trailing slash.
     *
     * @throws UnsafeArchiveException
     */
    public static function normalize(string $name, int $maxDepth): array
    {
        if (str_contains($name, "\0")) {
            throw new UnsafeArchiveException(ErrorCode::NullByte);
        }
        if ($name === '' || preg_match('/[\x00-\x1f]/', $name) === 1) {
            throw new UnsafeArchiveException(ErrorCode::InvalidPath);
        }
        if ($name[0] === '/' || $name[0] === '\\' || preg_match('/^[A-Za-z]:/', $name) === 1) {
            throw new UnsafeArchiveException(ErrorCode::AbsolutePath);
        }

        $name = str_replace('\\', '/', $name);
        $segments = explode('/', $name);
        if (in_array('..', $segments, true)) {
            throw new UnsafeArchiveException(ErrorCode::PathTraversal);
        }
        // shortcut: names flagged as CP437 are rejected too, ZipArchive does not expose the language-encoding flag; revisit if real archives need it.
        if (preg_match('//u', $name) !== 1) {
            throw new UnsafeArchiveException(ErrorCode::InvalidPath);
        }

        $isDirectory = str_ends_with($name, '/');
        $segments = array_values(array_filter($segments, static fn (string $s): bool => $s !== '' && $s !== '.'));
        if ($segments === []) {
            throw new UnsafeArchiveException(ErrorCode::InvalidPath);
        }
        if (count($segments) - ($isDirectory ? 0 : 1) > $maxDepth) {
            throw new UnsafeArchiveException(ErrorCode::DepthExceeded);
        }

        return ['path' => implode('/', $segments), 'isDirectory' => $isDirectory];
    }
}
