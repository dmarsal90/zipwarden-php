<?php

declare(strict_types=1);

use ZipWarden\Limits;
use ZipWarden\Policy;
use ZipWarden\Tests\Support\MaliciousZipFactory;
use ZipWarden\ZipWarden;

it('builds with the documented default limits', function (): void {
    $warden = new ZipWarden;

    expect($warden->limits)->toEqual(new Limits)
        ->and($warden->limits->maxRatio)->toBe(100)
        ->and($warden->limits->maxNestingDepth)->toBe(0)
        ->and($warden->policy)->toEqual(new Policy);
});

it('generates every malicious fixture as a ZIP-shaped file', function (): void {
    $dir = sys_get_temp_dir().'/zipwarden-'.bin2hex(random_bytes(6));
    mkdir($dir, 0700);

    try {
        $paths = MaliciousZipFactory::createAll($dir);

        expect($paths)->toHaveCount(8);
        foreach ($paths as $path) {
            expect(file_get_contents($path, false, null, 0, 4))->toBe("PK\x03\x04");
        }
    } finally {
        MaliciousZipFactory::cleanup($dir);
    }
});
