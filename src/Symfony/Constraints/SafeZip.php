<?php

declare(strict_types=1);

namespace ZipWarden\Symfony\Constraints;

use Attribute;
use Symfony\Component\Validator\Constraint;

/**
 * Fails when an uploaded file is not a ZIP that passes the ZipWarden pre-scan.
 */
#[Attribute(Attribute::TARGET_PROPERTY | Attribute::TARGET_METHOD | Attribute::IS_REPEATABLE)]
final class SafeZip extends Constraint
{
    public string $message = 'This file is not a safe ZIP archive.';

    /**
     * @param  array<string>|null  $groups
     */
    public function __construct(?string $message = null, ?array $groups = null, mixed $payload = null)
    {
        parent::__construct(null, $groups, $payload);

        $this->message = $message ?? $this->message;
    }
}
