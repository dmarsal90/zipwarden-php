<?php

declare(strict_types=1);

namespace ZipWarden\Laravel;

use Illuminate\Support\ServiceProvider;
use ZipWarden\ZipWarden;

final class ZipWardenServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(ZipWarden::class, static fn (): ZipWarden => new ZipWarden);
    }
}
