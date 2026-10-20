<?php

declare(strict_types=1);

namespace ZipWarden\Exception;

use RuntimeException;
use ZipWarden\ErrorCode;

class ZipWardenException extends RuntimeException
{
    public function __construct(public readonly ErrorCode $errorCode, string $message = '')
    {
        parent::__construct($message !== '' ? $message : $errorCode->value);
    }
}
