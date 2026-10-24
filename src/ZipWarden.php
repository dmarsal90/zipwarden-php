<?php

declare(strict_types=1);

namespace ZipWarden;

use Throwable;
use ZipWarden\Exception\ZipWardenException;

final class ZipWarden
{
    public function __construct(
        public readonly Limits $limits = new Limits,
        public readonly Policy $policy = new Policy,
    ) {}

    /**
     * Extract $zipPath into $destination, or into a new private temporary
     * directory when $destination is null. Failures raise a ZipWardenException.
     */
    public function extract(string $zipPath, ?string $destination = null): ExtractionResult
    {
        $scanner = new Scanner($this->limits, $this->policy);
        $zip = $scanner->open($zipPath);

        try {
            $entries = $scanner->scan($zip, $zipPath);
            [$work, $final] = $destination === null
                ? [$this->createTemporaryDirectory(), null]
                : $this->createStagingDirectory($destination);

            try {
                $files = (new Extraction($this->limits, $this->policy, $work))->run($zip, $entries);

                if ($final !== null) {
                    // shortcut: renaming over the empty destination replaces the directory itself, so its owner and group are lost (the mode is kept) and POSIX rename semantics are required; revisit for Windows.
                    $mode = is_dir($final) ? (fileperms($final) & 07777) : 0755;
                    if (! chmod($work, $mode) || ! @rename($work, $final)) {
                        throw new ZipWardenException(ErrorCode::IoError);
                    }
                }
            } catch (Throwable $e) {
                self::removeTree($work);
                throw $e;
            }
        } finally {
            $zip->close();
        }

        return new ExtractionResult($final ?? $work, $files);
    }

    private function createTemporaryDirectory(): string
    {
        $directory = sys_get_temp_dir().'/zipwarden-'.bin2hex(random_bytes(8));
        if (! @mkdir($directory, 0700)) {
            throw new ZipWardenException(ErrorCode::IoError);
        }

        return $directory;
    }

    /**
     * Validates the destination (absent or empty) and creates a private staging
     * directory next to it, so the final rename stays on one filesystem.
     *
     * @return array{string, string} Staging directory and final destination.
     */
    private function createStagingDirectory(string $destination): array
    {
        $name = basename($destination);
        $parent = realpath(dirname($destination));
        if ($parent === false || in_array($name, ['', '.', '..'], true)) {
            throw new ZipWardenException(ErrorCode::IoError);
        }

        $final = $parent.'/'.$name;
        if (is_link($final) || (file_exists($final) && ! is_dir($final))) {
            throw new ZipWardenException(ErrorCode::DestinationNotEmpty);
        }
        if (is_dir($final)) {
            $content = scandir($final);
            if ($content === false) {
                throw new ZipWardenException(ErrorCode::IoError);
            }
            if (count($content) > 2) {
                throw new ZipWardenException(ErrorCode::DestinationNotEmpty);
            }
        }

        $staging = $parent.'/.zipwarden-'.bin2hex(random_bytes(8));
        if (! @mkdir($staging, 0700)) {
            throw new ZipWardenException(ErrorCode::IoError);
        }

        return [$staging, $final];
    }

    /**
     * Deletes a tree without following symlinks.
     */
    private static function removeTree(string $path): void
    {
        if (is_link($path) || ! is_dir($path)) {
            @unlink($path);

            return;
        }

        foreach (scandir($path) ?: [] as $item) {
            if ($item !== '.' && $item !== '..') {
                self::removeTree($path.'/'.$item);
            }
        }
        @rmdir($path);
    }
}
