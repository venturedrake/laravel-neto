<?php

declare(strict_types=1);

namespace VentureDrake\Neto;

use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Illuminate\Contracts\Container\Container;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\ServiceProvider;
use Psr\Log\LoggerInterface;

class NetoServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/neto.php', 'neto');

        $this->app->singleton(Neto::class, function (Container $app): Neto {
            $config = $app->make(ConfigRepository::class)->get('neto', []);

            /** @var array<string, mixed> $config */
            $config = is_array($config) ? $config : [];

            return Neto::fromConfig(
                $app->make(HttpFactory::class),
                $config,
                $this->logger($config),
            );
        });

        $this->app->alias(Neto::class, 'neto');
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        if (! $this->app->runningInConsole()) {
            return;
        }

        $this->publishes([
            __DIR__.'/../config/neto.php' => config_path('neto.php'),
        ], ['laravel-neto', 'laravel-neto-config']);
    }

    /**
     * Resolve the logger for request logging, when enabled.
     *
     * @param  array<string, mixed>  $config
     */
    protected function logger(array $config): ?LoggerInterface
    {
        $logging = is_array($config['logging'] ?? null) ? $config['logging'] : [];

        if (! ($logging['enabled'] ?? false)) {
            return null;
        }

        $channel = $logging['channel'] ?? null;

        return Log::channel(is_string($channel) && $channel !== '' ? $channel : null);
    }
}
