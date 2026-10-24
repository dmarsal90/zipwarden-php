<?php

declare(strict_types=1);

namespace ZipWarden\Laravel\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use SplFileInfo;
use ZipWarden\Exception\ZipWardenException;
use ZipWarden\ZipWarden;

/**
 * Fails when an uploaded file is not a ZIP that passes the ZipWarden pre-scan.
 * Only uploaded files are accepted: a plain string is never read as a path.
 */
final class SafeZip implements ValidationRule
{
    public function __construct(private readonly ZipWarden $warden = new ZipWarden) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $path = $value instanceof SplFileInfo ? $value->getRealPath() : false;

        if ($path !== false) {
            try {
                $this->warden->validate($path);

                return;
            } catch (ZipWardenException) {
                // Falls through to the single failure message; the code stays out of user-facing text.
            }
        }

        $fail('The :attribute must be a safe ZIP archive.');
    }
}
