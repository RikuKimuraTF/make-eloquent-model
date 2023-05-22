<?php

namespace Kamakas\MakeEloquent;

use Illuminate\Support\ServiceProvider;
use Kamakas\MakeEloquent\Console\MakeModel;

class MakeModelServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        //
    }

    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        if (! $this->app->runningInConsole()) {
            return;
        }

        $this->commands([
            MakeModel::class,
        ]);
    }

    /**
     * Get the services provided by the provider.
     *
     * @return array
     */
    public function provides()
    {
        return [
            MakeModel::class,
        ];
    }
}
