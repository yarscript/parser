<?php


namespace App\Providers;


use App\Repositories\DxRepository;
use GuzzleHttp\Client;
use Illuminate\Support\ServiceProvider;

class DxRepositoryServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->app->singleton(DxRepository::class, fn() => new DxRepository(new Client(), ['url' => config('app.xcart_api_url')]));
    }
}
