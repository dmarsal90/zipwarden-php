<?php

declare(strict_types=1);

namespace ZipWarden;

final readonly class ExtractionResult
{
    /**
     * @param  string  $directory  Directory the archive was extracted into.
     * @param  list<string>  $files  Extracted file paths, relative to $directory.
     */
    public function __construct(
        public string $directory,
        public array $files,
    ) {}
}
