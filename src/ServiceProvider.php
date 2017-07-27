<?php namespace LaravelNews\CallRequest;

use Illuminate\Support\ServiceProvider as LServiceProvider;

class ServiceProvider extends LServiceProvider {

    public function boot()
    {
        $this->publishes([__DIR__ . '/../config/birdseye.php' => config_path('birdseye.php')], 'config');
    }

    public function register()
    {

    }

}