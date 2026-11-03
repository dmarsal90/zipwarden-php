<?php

declare(strict_types=1);

namespace ZipWarden\Symfony\Constraints;

use SplFileInfo;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;
use ZipWarden\Exception\ZipWardenException;
use ZipWarden\ZipWarden;

/**
 * Only uploaded files are accepted: a plain string is never read as a path.
 */
final class SafeZipValidator extends ConstraintValidator
{
    public function __construct(private readonly ZipWarden $warden = new ZipWarden) {}

    public function validate(mixed $value, Constraint $constraint): void
    {
        if (! $constraint instanceof SafeZip) {
            throw new UnexpectedTypeException($constraint, SafeZip::class);
        }

        // Empty values are left to NotBlank / NotNull.
        if ($value === null || $value === '') {
            return;
        }

        $path = $value instanceof SplFileInfo ? $value->getRealPath() : false;

        if ($path !== false) {
            try {
                $this->warden->validate($path);

                return;
            } catch (ZipWardenException) {
                // Falls through to the single violation; the error code stays out of user-facing text.
            }
        }

        $this->context->buildViolation($constraint->message)->addViolation();
    }
}
