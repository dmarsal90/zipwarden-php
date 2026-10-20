<?php

declare(strict_types=1);

namespace ZipWarden\Tests\Support;

use RuntimeException;

/**
 * Builds malicious ZIP files at test time, so no binaries live in the repo.
 * ZipArchive refuses most of these shapes, so the bytes are written by hand.
 */
final class MaliciousZipFactory
{
    private const UNIX_FILE = 0100644;

    private const UNIX_SYMLINK = 0120777;

    /**
     * Create every fixture inside $dir and return name => path.
     *
     * @return array<string, string>
     */
    public static function createAll(string $dir): array
    {
        $bomb = self::build([['name' => 'zeros.bin', 'data' => str_repeat("\0", 20 * 1024 * 1024)]]);

        $fixtures = [
            'zip-slip' => self::build([['name' => '../evil.txt', 'data' => 'x']]),
            'zip-slip-backslash' => self::build([['name' => '..\\evil.txt', 'data' => 'x']]),
            'absolute-path' => self::build([['name' => '/tmp/evil.txt', 'data' => 'x']]),
            'symlink' => self::build([['name' => 'link', 'data' => '/etc/passwd', 'mode' => self::UNIX_SYMLINK]]),
            'null-byte' => self::build([['name' => "safe.txt\0.exe", 'data' => 'x']]),
            'bomb' => $bomb,
            'nested-bomb' => self::build([['name' => 'inner.zip', 'data' => $bomb, 'deflate' => false]]),
            'false-size' => self::build([['name' => 'big.txt', 'data' => str_repeat('A', 4096), 'declaredSize' => 1]]),
        ];

        $paths = [];
        foreach ($fixtures as $name => $bytes) {
            $paths[$name] = $dir.DIRECTORY_SEPARATOR.$name.'.zip';
            if (file_put_contents($paths[$name], $bytes) === false) {
                throw new RuntimeException("Cannot write {$paths[$name]}");
            }
        }

        return $paths;
    }

    /**
     * @param  list<array{name: string, data: string, mode?: int, declaredSize?: int, deflate?: bool}>  $entries
     */
    public static function build(array $entries): string
    {
        $body = '';
        $central = '';

        foreach ($entries as $entry) {
            $data = $entry['data'];
            $deflate = $entry['deflate'] ?? true;
            $payload = $deflate ? (string) gzdeflate($data) : $data;
            $method = $deflate ? 8 : 0;
            $crc = crc32($data);
            $size = $entry['declaredSize'] ?? strlen($data);
            $name = $entry['name'];
            $mode = $entry['mode'] ?? self::UNIX_FILE;

            // Local header: signature, version, flags, method, time, date, crc, sizes, name length, extra length.
            $local = "PK\x03\x04".pack('vvvvvVVVvv', 20, 0, $method, 0, 33, $crc, strlen($payload), $size, strlen($name), 0).$name;
            $offset = strlen($body);
            $body .= $local.$payload;

            $central .= "PK\x01\x02".pack(
                'vvvvvvVVVvvvvvVV',
                (3 << 8) | 20, 20, 0, $method, 0, 33,
                $crc, strlen($payload), $size,
                strlen($name), 0, 0, 0, 0,
                $mode << 16, $offset,
            ).$name;
        }

        $count = count($entries);

        return $body.$central."PK\x05\x06".pack('vvvvVVv', 0, 0, $count, $count, strlen($central), strlen($body), 0);
    }

    public static function cleanup(string $dir): void
    {
        foreach (glob($dir.DIRECTORY_SEPARATOR.'*') ?: [] as $file) {
            unlink($file);
        }
        rmdir($dir);
    }
}
