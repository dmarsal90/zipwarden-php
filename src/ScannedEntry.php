<?php

declare(strict_types=1);

namespace ZipWarden;

/**
 * An entry that passed the pre-scan. Sizes and CRC come from the headers and are untrusted.
 *
 * @internal
 */
final readonly class ScannedEntry
{
    public function __construct(
        public int $index,
        public string $path,
        public bool $isDirectory,
        public int $size,
        public int $compressedSize,
        public int $crc,
    ) {}
}
