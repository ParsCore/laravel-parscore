<?php

namespace ParsCore\Laravel;

use Illuminate\Support\ServiceProvider;

/**
 * Service provider for the ParsCore Laravel package.
 */
class ParsCoreServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     *
     * @return void
     */
    public function register()
    {
        $this->app->singleton(ParsCore::class, function () {
            return new ParsCore();
        });
    }

    /**
     * Bootstrap services.
     *
     * @return void
     */
    public function boot()
    {
        //
    }
}