<?php

namespace Vipertecpro\Consent;

use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Support\ServiceProvider;

class ConsentServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/consent.php', 'consent');

        $this->app->singleton(Consent::class, function ($app) {
            return new Consent(
                purposes: (array) $app['config']->get('consent.purposes', []),
                version: (string) $app['config']->get('consent.version', '1'),
                events: $app->make(Dispatcher::class),
            );
        });
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/consent.php' => config_path('consent.php'),
            ], 'consent-config');
        }
    }
}
