<?php

declare(strict_types=1);

it('rejects a high-ratio entry with RATIO_EXCEEDED', function (): void {
    // Fixture: bomb
})->skip('pending: extraction is not implemented yet');

it('stops streaming as soon as the limit is crossed and removes partial output', function (): void {
    // Fixture: bomb
})->skip('pending: extraction is not implemented yet');

it('rejects a nested archive with NESTED_ARCHIVE when maxNestingDepth is 0', function (): void {
    // Fixture: nested-bomb
})->skip('pending: extraction is not implemented yet');

it('shares the total size budget across nesting levels when nesting is allowed', function (): void {
    // Fixture: nested-bomb
})->skip('pending: extraction is not implemented yet');
