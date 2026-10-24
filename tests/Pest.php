<?php

declare(strict_types=1);

use ZipWarden\ErrorCode;
use ZipWarden\Exception\ZipWardenException;
use ZipWarden\Limits;
use ZipWarden\Policy;
use ZipWarden\ScannedEntry;
use ZipWarden\Scanner;
use ZipWarden\Tests\Support\MaliciousZipFactory;

/**
 * Pre-scan a ZIP built from $entries (see MaliciousZipFactory::build).
 *
 * @param  list<array{name: string, data: string, mode?: int, declaredSize?: int, deflate?: bool}>  $entries
 * @return list<ScannedEntry>
 */
function scanEntries(array $entries, ?Limits $limits = null, ?Policy $policy = null): array
{
    return scanBytes(MaliciousZipFactory::build($entries), $limits, $policy);
}

/**
 * @return list<ScannedEntry>
 */
function scanBytes(string $bytes, ?Limits $limits = null, ?Policy $policy = null): array
{
    $path = (string) tempnam(sys_get_temp_dir(), 'zw');
    file_put_contents($path, $bytes);

    try {
        $scanner = new Scanner($limits ?? new Limits, $policy ?? new Policy);

        return $scanner->scan($scanner->open($path), $path);
    } finally {
        unlink($path);
    }
}

function errorCodeOf(callable $fn): ?ErrorCode
{
    try {
        $fn();
    } catch (ZipWardenException $e) {
        return $e->errorCode;
    }

    return null;
}
