<?php

declare(strict_types=1);

namespace ZipWarden;

use LogicException;

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
        throw new LogicException('Not implemented yet.');
    }
}
