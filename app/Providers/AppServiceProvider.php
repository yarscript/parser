<?php

namespace App\Providers;

use App\Feeds\Storage\FileStorage;
use App\Feeds\Storage\RabbitStorage;
use App\Feeds\Utils\Collection;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        $this->app->bind(FileStorage::class, fn() => new FileStorage);
        $this->app->bind(RabbitStorage::class, fn() => new RabbitStorage);
        $this->app->bind(Collection::class, fn() => new Collection);
    }

    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        //
    }
}
