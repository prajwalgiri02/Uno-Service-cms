<?php

namespace App\Providers;

use Illuminate\Foundation\Support\Providers\RouteServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Route;

class RouteServiceProvider extends ServiceProvider
{
    public const HOME = '/cms/dashboard';

    public function boot(): void
    {
        $this->routes(function () {
            // routes/api/{version}/*.php -> /api/{version}/... named api.{version}.*
            foreach (glob(base_path('routes/api/*'), GLOB_ONLYDIR) as $versionPath) {
                $version = basename($versionPath);

                Route::middleware('api')
                    ->prefix('api/'.$version)
                    ->name('api.'.$version.'.')
                    ->group(function () use ($versionPath) {
                        foreach (glob($versionPath.'/*.php') as $file) {
                            require $file;
                        }
                    });
            }

            Route::middleware('web')
                ->prefix('cms')
                ->name('cms.')
                ->group(function () {
                    foreach (glob(base_path('routes/cms/*.php')) as $file) {
                        require $file;
                    }
                });
        });
    }
}
