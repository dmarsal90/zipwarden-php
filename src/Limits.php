<?php

declare(strict_types=1);

namespace ZipWarden;

/**
 * Extraction limits. Defaults and their reasons: docs/SPEC.md, section 2.
 */
final readonly class Limits
{
    public function __construct(
        public int $maxArchiveSize = 100 * 1024 * 1024,
        public int $maxTotalUncompressedSize = 500 * 1024 * 1024,
        public int $maxEntrySize = 100 * 1024 * 1024,
        public int $maxEntries = 10_000,
        public int $maxRatio = 100,
        public int $maxDepth = 16,
        public int $maxNestingDepth = 0,
        public int $timeoutSeconds = 30,
    ) {}
}
