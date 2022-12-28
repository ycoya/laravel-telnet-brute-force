<?php

namespace Ycoya\LaravelTelnetBruteForce\Providers;

use Illuminate\Support\ServiceProvider;
use Ycoya\LaravelTelnetBruteForce\Console\Commands\TelnetBruteForcePasswordGenerated;
use Ycoya\LaravelTelnetBruteForce\Console\Commands\TelnetBruteForceDictionary;

class TelnetBruteForceProvider extends ServiceProvider
{
    /**
     * Register services.
     *
     * @return void
     */
    public function register()
    {
        //
    }

    /**
     * Bootstrap services.
     *
     * @return void
     */
    public function boot()
    {
        $this->mergeConfigFrom(__DIR__ . '/../../config/filesystems.php', "filesystems.disks" );

        if ($this->app->runningInConsole()) {
            $this->commands([
                TelnetBruteForceDictionary::class,
                TelnetBruteForcePasswordGenerated::class,
            ]);
        }
    }
}
