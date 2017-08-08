<?php

namespace Restmodel;

use Illuminate\Support\ServiceProvider;

class RestmodelServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap the application services.
     *
     * @return void
     */
    public function boot()
    {
        $this->publishes([
            __DIR__.'/../resources/config/restmodel.php' => config_path('restmodel.php'),
        ]);
    }

    /**
     * Register the application services.
     *
     * @return void
     */
    public function register()
    {
        $this->mergeConfigFrom(
           __DIR__.'/../resources/config/restmodel.php', 'restmodel'
       );
    }
}
