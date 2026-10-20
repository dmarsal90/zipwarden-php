<?php

declare(strict_types=1);

namespace ZipWarden\Exception;

/**
 * The archive broke a safety rule (path, type, size or time limit).
 */
final class UnsafeArchiveException extends ZipWardenException {}
