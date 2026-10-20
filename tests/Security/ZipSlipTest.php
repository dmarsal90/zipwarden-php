<?php

declare(strict_types=1);

it('rejects entries with ../ segments with PATH_TRAVERSAL', function (): void {
    // Fixture: zip-slip
})->skip('pending: extraction is not implemented yet');

it('rejects backslash-separated traversal with PATH_TRAVERSAL', function (): void {
    // Fixture: zip-slip-backslash
})->skip('pending: extraction is not implemented yet');

it('rejects absolute paths with ABSOLUTE_PATH', function (): void {
    // Fixture: absolute-path
})->skip('pending: extraction is not implemented yet');

it('never writes any file outside the destination directory', function (): void {
    // Fixture: zip-slip
})->skip('pending: extraction is not implemented yet');
