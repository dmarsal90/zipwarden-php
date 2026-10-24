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

/**
 * Run $fn with a fresh scratch directory and delete it afterwards.
 */
function withWorkDir(callable $fn): void
{
    $dir = sys_get_temp_dir().'/zipwarden-test-'.bin2hex(random_bytes(6));
    mkdir($dir, 0700);

    try {
        $fn($dir);
    } finally {
        removeDir($dir);
    }
}

function removeDir(string $path): void
{
    if (is_link($path) || ! is_file($path) && ! is_dir($path)) {
        @unlink($path);

        return;
    }
    if (is_dir($path)) {
        foreach (scandir($path) ?: [] as $item) {
            if ($item !== '.' && $item !== '..') {
                removeDir($path.'/'.$item);
            }
        }
        rmdir($path);

        return;
    }
    unlink($path);
}

/**
 * Write a ZIP built from $entries to $path.
 *
 * @param  list<array{name: string, data: string, mode?: int, declaredSize?: int, deflate?: bool}>  $entries
 */
function writeZip(string $path, array $entries): string
{
    file_put_contents($path, MaliciousZipFactory::build($entries));

    return $path;
}
