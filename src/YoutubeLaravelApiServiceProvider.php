<?php

namespace ZeroDUDDU\YoutubeLaravelApi;

use Illuminate\Support\ServiceProvider;

class YoutubeLaravelApiServiceProvider extends ServiceProvider
{
    /**
     * Perform post-registration booting of services.
     *
     * @return void
     */
    public function boot()
    {
        $this->publishes(
            [
            __DIR__ . '/config/google-config.php' => config_path('google-config.php')
            ],
            'google-config'
        );
    }

    /**
     * Register any package services.
     *
     * @return void
     */
    public function register()
    {
        //
    }
}
