<?php

declare(strict_types=1);

namespace ZipWarden;

use ZipArchive;
use ZipWarden\Exception\UnsafeArchiveException;
use ZipWarden\Exception\ZipWardenException;

/**
 * Streams the scanned entries into a fresh directory. One instance per
 * extraction: it holds the running totals. See docs/SPEC.md, section 1 step 2.
 *
 * @internal
 */
final class Extraction
{
    private const CHUNK_SIZE = 65536;

    private readonly string $root;

    private readonly float $deadline;

    private int $total = 0;

    private int $entryCount = 0;

    /** Archive levels below the top one that are being inspected. */
    private int $depth = 0;

    /** @var list<string> */
    private array $files = [];

    /**
     * @param  string  $root  Existing, empty directory that only this extraction writes to.
     */
    public function __construct(private readonly Limits $limits, private readonly Policy $policy, string $root)
    {
        $real = realpath($root);
        if ($real === false) {
            throw new ZipWardenException(ErrorCode::IoError);
        }
        $this->root = $real;
        $this->deadline = microtime(true) + $limits->timeoutSeconds;
    }

    /**
     * @param  list<ScannedEntry>  $entries
     * @return list<string> Extracted file paths, relative to the root.
     *
     * @throws ZipWardenException
     */
    public function run(ZipArchive $zip, array $entries): array
    {
        $this->countEntries($entries);

        foreach ($entries as $entry) {
            $this->checkTime();

            if ($entry->isDirectory) {
                $this->ensureDirectory($entry->path);
            } else {
                $this->writeFile($zip, $entry);
            }
        }

        return $this->files;
    }

    private function writeFile(ZipArchive $zip, ScannedEntry $entry): void
    {
        $directory = str_contains($entry->path, '/') ? $this->ensureDirectory(dirname($entry->path)) : $this->root;
        $target = $directory.'/'.basename($entry->path);

        $input = $zip->getStreamIndex($entry->index);
        if ($input === false) {
            throw new ZipWardenException(ErrorCode::InvalidArchive);
        }

        // Exclusive creation: fails on any existing file or link, never follows one.
        $output = @fopen($target, 'xb');
        if ($output === false) {
            fclose($input);
            throw new ZipWardenException(ErrorCode::IoError);
        }

        try {
            $isZip = $this->copy($input, $output, $entry);
        } finally {
            fclose($input);
            fclose($output);
        }

        if (! chmod($target, 0644)) {
            throw new ZipWardenException(ErrorCode::IoError);
        }
        if ($isZip) {
            $this->inspect($target);
        }
        $this->files[] = $entry->path;
    }

    /**
     * Checks an archive nested inside the one being extracted, with the same limits
     * and the shared totals. Its content is counted but not written anywhere.
     */
    private function inspect(string $path): void
    {
        $scanner = new Scanner($this->limits, $this->policy);
        $zip = $scanner->open($path);
        $this->depth++;

        try {
            $entries = $scanner->scan($zip, $path);
            $this->countEntries($entries);

            foreach ($entries as $entry) {
                $this->checkTime();
                if (! $entry->isDirectory) {
                    $this->inspectEntry($zip, $entry);
                }
            }
        } finally {
            $this->depth--;
            $zip->close();
        }
    }

    private function inspectEntry(ZipArchive $zip, ScannedEntry $entry): void
    {
        $input = $zip->getStreamIndex($entry->index);
        if ($input === false) {
            throw new ZipWardenException(ErrorCode::InvalidArchive);
        }

        // A scratch file is only needed when this entry may itself be a nested archive.
        $scratch = $this->depth < $this->limits->maxNestingDepth ? tmpfile() : null;
        if ($scratch === false) {
            fclose($input);
            throw new ZipWardenException(ErrorCode::IoError);
        }

        try {
            $isZip = $this->copy($input, $scratch, $entry);
            if ($isZip && $scratch !== null) {
                fflush($scratch);
                $meta = stream_get_meta_data($scratch);
                if (! isset($meta['uri'])) {
                    throw new ZipWardenException(ErrorCode::IoError);
                }
                $this->inspect($meta['uri']);
            }
        } finally {
            fclose($input);
            if ($scratch !== null) {
                fclose($scratch);
            }
        }
    }

    /**
     * Entries of every level count against the same maxEntries.
     *
     * @param  list<ScannedEntry>  $entries
     */
    private function countEntries(array $entries): void
    {
        $this->entryCount += count($entries);
        if ($this->entryCount > $this->limits->maxEntries) {
            throw new UnsafeArchiveException(ErrorCode::TooManyEntries);
        }
    }

    /**
     * Counts the bytes actually produced; the sizes in the headers are not trusted.
     *
     * @param  resource  $input
     * @param  resource|null  $output  Null counts the bytes without writing them.
     * @return bool Whether the content is itself a ZIP archive.
     */
    private function copy($input, $output, ScannedEntry $entry): bool
    {
        $produced = 0;
        $head = '';
        $isZip = false;
        $crc = hash_init('crc32b');

        while (! feof($input)) {
            $chunk = fread($input, self::CHUNK_SIZE);
            if ($chunk === false) {
                throw new ZipWardenException(ErrorCode::InvalidArchive);
            }
            if ($chunk === '') {
                break;
            }
            $this->checkTime();

            $length = strlen($chunk);
            $produced += $length;
            $this->total += $length;

            // Every limit is checked before the chunk is written, so the output never passes it.
            if ($produced > $this->limits->maxEntrySize) {
                throw new UnsafeArchiveException(ErrorCode::EntrySizeExceeded);
            }
            if ($this->total > $this->limits->maxTotalUncompressedSize) {
                throw new UnsafeArchiveException(ErrorCode::TotalSizeExceeded);
            }
            if ($produced > Scanner::RATIO_MIN_BYTES && ($entry->compressedSize === 0 || $produced / $entry->compressedSize > $this->limits->maxRatio)) {
                throw new UnsafeArchiveException(ErrorCode::RatioExceeded);
            }
            if ($produced > $entry->size) {
                throw new UnsafeArchiveException(ErrorCode::SizeMismatch);
            }

            if (strlen($head) < 4) {
                $head .= substr($chunk, 0, 4 - strlen($head));
                $isZip = $head === "PK\x03\x04" || $head === "PK\x05\x06";
                if ($isZip && $this->depth >= $this->limits->maxNestingDepth) {
                    throw new UnsafeArchiveException(ErrorCode::NestedArchive);
                }
            }

            if ($output !== null && fwrite($output, $chunk) !== $length) {
                throw new ZipWardenException(ErrorCode::IoError);
            }
            hash_update($crc, $chunk);
        }

        if ($produced !== $entry->size || hexdec(hash_final($crc)) !== $entry->crc) {
            throw new UnsafeArchiveException(ErrorCode::SizeMismatch);
        }

        return $isZip;
    }

    /**
     * Creates the directory one segment at a time and returns its resolved path.
     * Nothing in the staging tree may be a link, and the result must stay inside the root.
     */
    private function ensureDirectory(string $relative): string
    {
        $current = $this->root;

        foreach (explode('/', $relative) as $segment) {
            $current .= '/'.$segment;

            if (is_link($current)) {
                throw new UnsafeArchiveException(ErrorCode::PathTraversal);
            }
            if (! is_dir($current) && (! @mkdir($current, 0755) || ! chmod($current, 0755))) {
                throw new ZipWardenException(ErrorCode::IoError);
            }
        }

        $resolved = realpath($current);
        if ($resolved === false || ! str_starts_with($resolved.'/', $this->root.'/')) {
            throw new UnsafeArchiveException(ErrorCode::PathTraversal);
        }

        return $resolved;
    }

    private function checkTime(): void
    {
        if (microtime(true) > $this->deadline) {
            throw new UnsafeArchiveException(ErrorCode::Timeout);
        }
    }
}
