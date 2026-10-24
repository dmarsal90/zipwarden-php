<?php

declare(strict_types=1);

namespace ZipWarden;

use ZipWarden\Exception\ZipWardenException;

/**
 * Reads the raw entry names from the central directory.
 *
 * ZipArchive rewrites control characters (a NUL byte becomes a space), so the
 * name rules of docs/SPEC.md section 4.1 cannot be checked on what it returns.
 *
 * @internal
 */
final class CentralDirectory
{
    private const EOCD_SIZE = 22;

    private const MAX_COMMENT = 0xFFFF;

    /**
     * @return list<string> Names in central-directory order.
     *
     * @throws ZipWardenException
     */
    public static function names(string $zipPath, int $expectedCount): array
    {
        $file = fopen($zipPath, 'rb');
        if ($file === false) {
            throw new ZipWardenException(ErrorCode::IoError);
        }

        try {
            [$count, $offset] = self::locate($file);
            if ($count !== $expectedCount) {
                throw new ZipWardenException(ErrorCode::InvalidArchive);
            }

            if (fseek($file, $offset) !== 0) {
                throw new ZipWardenException(ErrorCode::InvalidArchive);
            }

            $names = [];
            for ($i = 0; $i < $count; $i++) {
                $header = fread($file, 46);
                if ($header === false || strlen($header) !== 46 || ! str_starts_with($header, "PK\x01\x02")) {
                    throw new ZipWardenException(ErrorCode::InvalidArchive);
                }
                /** @var array{n: int, e: int, c: int} $lengths */
                $lengths = unpack('vn/ve/vc', $header, 28);

                $name = $lengths['n'] > 0 ? fread($file, $lengths['n']) : '';
                if ($name === false || strlen($name) !== $lengths['n']) {
                    throw new ZipWardenException(ErrorCode::InvalidArchive);
                }
                $names[] = $name;

                if (fseek($file, $lengths['e'] + $lengths['c'], SEEK_CUR) !== 0) {
                    throw new ZipWardenException(ErrorCode::InvalidArchive);
                }
            }

            return $names;
        } finally {
            fclose($file);
        }
    }

    /**
     * @param  resource  $file
     * @return array{int, int} Entry count and central directory offset.
     */
    private static function locate($file): array
    {
        $size = fstat($file)['size'] ?? 0;
        $tailLength = min($size, self::EOCD_SIZE + self::MAX_COMMENT);
        if ($tailLength < self::EOCD_SIZE || fseek($file, $size - $tailLength) !== 0) {
            throw new ZipWardenException(ErrorCode::InvalidArchive);
        }
        $tail = (string) fread($file, $tailLength);

        // Only an end record whose comment reaches the end of the file counts,
        // so a fake record hidden inside a comment cannot be picked.
        $position = strlen($tail) - self::EOCD_SIZE;
        for (; $position >= 0; $position--) {
            if (substr($tail, $position, 4) !== "PK\x05\x06") {
                continue;
            }
            /** @var array{count: int, size: int, offset: int, comment: int} $eocd */
            $eocd = unpack('vdisk/vcddisk/ventriesdisk/vcount/Vsize/Voffset/vcomment', $tail, $position + 4);
            if ($position + self::EOCD_SIZE + $eocd['comment'] === strlen($tail)) {
                break;
            }
        }
        if ($position < 0 || ! isset($eocd)) {
            throw new ZipWardenException(ErrorCode::InvalidArchive);
        }

        if ($eocd['count'] !== 0xFFFF && $eocd['offset'] !== 0xFFFFFFFF && $eocd['size'] !== 0xFFFFFFFF) {
            return [$eocd['count'], $eocd['offset']];
        }

        return self::locateZip64($file, $tail, $position, $size);
    }

    /**
     * @param  resource  $file
     * @return array{int, int}
     */
    private static function locateZip64($file, string $tail, int $eocdPosition, int $size): array
    {
        $locator = $eocdPosition >= 20 ? substr($tail, $eocdPosition - 20, 20) : '';
        if (! str_starts_with($locator, "PK\x06\x07")) {
            throw new ZipWardenException(ErrorCode::InvalidArchive);
        }
        /** @var array{offset: int} $located */
        $located = unpack('Poffset', $locator, 8);

        if ($located['offset'] < 0 || $size < $located['offset'] + 56 || fseek($file, $located['offset']) !== 0) {
            throw new ZipWardenException(ErrorCode::InvalidArchive);
        }
        $record = (string) fread($file, 56);
        if (strlen($record) !== 56 || ! str_starts_with($record, "PK\x06\x06")) {
            throw new ZipWardenException(ErrorCode::InvalidArchive);
        }
        /** @var array{count: int, size: int, offset: int} $zip64 */
        $zip64 = unpack('Pcount/Psize/Poffset', $record, 32);

        if ($zip64['count'] < 0 || $zip64['offset'] < 0 || $zip64['offset'] > $size) {
            throw new ZipWardenException(ErrorCode::InvalidArchive);
        }

        return [$zip64['count'], $zip64['offset']];
    }
}
