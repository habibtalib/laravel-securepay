<?php

namespace HabibTalib\SecurePay;

use Illuminate\Support\ServiceProvider;

class SecurePayServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/securepay.php', 'securepay');

        $this->app->singleton(SecurePayClient::class, function ($app) {
            return new SecurePayClient(config('securepay'));
        });

        $this->app->alias(SecurePayClient::class, 'securepay');
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__ . '/../config/securepay.php' => config_path('securepay.php'),
            ], 'securepay-config');

            $this->publishes([
                __DIR__ . '/../routes/securepay.php' => base_path('routes/securepay.php'),
            ], 'securepay-routes');
        }

        $this->loadRoutesFrom(__DIR__ . '/../routes/securepay.php');
    }
}
