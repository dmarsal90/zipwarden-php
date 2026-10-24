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

    /**
     * Whether a file path passes the allowlist (the last extension, case-insensitive).
     */
    public function allows(string $path): bool
    {
        if ($this->allowedExtensions === []) {
            return true;
        }

        $name = basename($path);
        $dot = strrpos($name, '.');
        $extension = $dot === false ? '' : strtolower(substr($name, $dot + 1));

        return in_array($extension, $this->allowedExtensions, true);
    }
}
