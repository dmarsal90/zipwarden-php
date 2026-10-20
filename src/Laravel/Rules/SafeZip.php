<?php

declare(strict_types=1);

namespace ZipWarden\Laravel\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use LogicException;

final class SafeZip implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        throw new LogicException('Not implemented yet.');
    }
}
