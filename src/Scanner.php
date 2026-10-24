<?php

declare(strict_types=1);

namespace ZipWarden;

use ZipArchive;
use ZipWarden\Exception\UnsafeArchiveException;
use ZipWarden\Exception\ZipWardenException;

/**
 * Pre-scan: checks the central directory without extracting anything.
 * See docs/SPEC.md, section 1 step 1 and section 4.
 *
 * @internal
 */
final class Scanner
{
    private const UNIX_TYPE_MASK = 0170000;

    private const UNIX_REGULAR = 0100000;

    private const UNIX_DIRECTORY = 0040000;

    private const UNIX_SYMLINK = 0120000;

    public const RATIO_MIN_BYTES = 1024 * 1024;

    public function __construct(
        private readonly Limits $limits,
        private readonly Policy $policy,
    ) {}

    /**
     * @throws ZipWardenException
     */
    public function open(string $zipPath): ZipArchive
    {
        $size = is_file($zipPath) ? filesize($zipPath) : false;
        if ($size === false) {
            throw new ZipWardenException(ErrorCode::IoError);
        }
        if ($size > $this->limits->maxArchiveSize) {
            throw new UnsafeArchiveException(ErrorCode::ArchiveTooLarge);
        }

        $zip = new ZipArchive;
        $result = $zip->open($zipPath, ZipArchive::RDONLY | ZipArchive::CHECKCONS);
        if ($result !== true) {
            throw new ZipWardenException(
                $result === ZipArchive::ER_MULTIDISK ? ErrorCode::UnsupportedFeature : ErrorCode::InvalidArchive,
            );
        }

        return $zip;
    }

    /**
     * Reject the whole archive on the first violation, in central-directory order.
     *
     * @return list<ScannedEntry>
     *
     * @throws ZipWardenException
     */
    public function scan(ZipArchive $zip, string $zipPath): array
    {
        if ($zip->numFiles > $this->limits->maxEntries) {
            throw new UnsafeArchiveException(ErrorCode::TooManyEntries);
        }

        $names = CentralDirectory::names($zipPath, $zip->numFiles);

        $entries = [];
        $seen = [];

        for ($i = 0; $i < $zip->numFiles; $i++) {
            $stat = $zip->statIndex($i, ZipArchive::FL_ENC_RAW);
            if ($stat === false) {
                throw new ZipWardenException(ErrorCode::InvalidArchive);
            }

            ['path' => $path, 'isDirectory' => $isDirectory] = PathGuard::normalize($names[$i], $this->limits->maxDepth);

            $this->checkType($zip, $i, $stat['encryption_method']);

            // shortcut: case folding is ASCII only; a non-ASCII collision still fails later because files are created exclusively.
            $key = strtolower($path);
            if (isset($seen[$key])) {
                throw new UnsafeArchiveException(ErrorCode::DuplicateEntry);
            }
            $seen[$key] = true;

            if (! $isDirectory) {
                if (! $this->policy->allows($path)) {
                    throw new UnsafeArchiveException(ErrorCode::ExtensionNotAllowed);
                }
                $this->checkSizes($stat['size'], $stat['comp_size']);
            }

            $entries[] = new ScannedEntry($i, $path, $isDirectory, $stat['size'], $stat['comp_size'], $stat['crc']);
        }

        return $entries;
    }

    private function checkType(ZipArchive $zip, int $index, int $encryptionMethod): void
    {
        if ($encryptionMethod !== ZipArchive::EM_NONE) {
            throw new ZipWardenException(ErrorCode::UnsupportedFeature);
        }

        $opsys = 0;
        $attributes = 0;
        if (! $zip->getExternalAttributesIndex($index, $opsys, $attributes) || ! is_int($attributes)) {
            throw new ZipWardenException(ErrorCode::InvalidArchive);
        }
        $type = $opsys === ZipArchive::OPSYS_UNIX ? ($attributes >> 16) & self::UNIX_TYPE_MASK : 0;

        if ($type === self::UNIX_SYMLINK) {
            throw new UnsafeArchiveException(ErrorCode::Symlink);
        }
        if ($type !== 0 && $type !== self::UNIX_REGULAR && $type !== self::UNIX_DIRECTORY) {
            throw new UnsafeArchiveException(ErrorCode::SpecialFile);
        }
    }

    /**
     * Declared sizes only give an early rejection; streaming counters are authoritative.
     */
    private function checkSizes(int $size, int $compressedSize): void
    {
        if ($size > $this->limits->maxEntrySize) {
            throw new UnsafeArchiveException(ErrorCode::EntrySizeExceeded);
        }
        if ($size > self::RATIO_MIN_BYTES && ($compressedSize === 0 || $size / $compressedSize > $this->limits->maxRatio)) {
            throw new UnsafeArchiveException(ErrorCode::RatioExceeded);
        }
    }
}
