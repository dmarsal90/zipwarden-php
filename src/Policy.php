<?php

declare(strict_types=1);

namespace ZipWarden;

/**
 * Allowlist of file types. See docs/SPEC.md, section 3.
 */
final readonly class Policy
{
    /**
     * @param  list<string>  $allowedExtensions  Lowercase, without the dot. Empty allows everything.
     */
    public function __construct(
        public array $allowedExtensions = [],
    ) {}
}
