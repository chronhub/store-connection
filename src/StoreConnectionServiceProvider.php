<?php

declare(strict_types=1);

namespace Chronhub\Store\Connection;

use Illuminate\Support\ServiceProvider;

final class StoreConnectionServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $loadMigration = config('chronicler.console.load_migration');

            if ($loadMigration === true) {
                $this->loadMigrationsFrom(__DIR__.'/../database');
            }
        }
    }
}
