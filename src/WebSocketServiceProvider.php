<?php

namespace Alphavel\WebSocket;

use Alphavel\ServiceProvider;

class WebSocketServiceProvider extends ServiceProvider
{
    /**
     * Register WebSocket services
     */
    public function register(): void
    {
        // Merge config
        $this->mergeConfigFrom(__DIR__ . '/../config/websocket.php', 'websocket');
        
        // Register WebSocket server as singleton
        $this->app->singleton('websocket.server', function ($app) {
            $config = $app['config']->get('websocket');
            return new WebSocketServer($config);
        });
        
        // Register broadcast manager
        $this->app->singleton('websocket', function ($app) {
            return $app['websocket.server']->broadcaster();
        });
        
        // Register facade
        if (class_exists('Alphavel\Facade')) {
            \Alphavel\Facade::register('WebSocket', \Alphavel\WebSocket\Facades\WebSocket::class);
        }
    }

    /**
     * Bootstrap WebSocket services
     */
    public function boot(): void
    {
        // Publish config
        if (method_exists($this, 'publishes')) {
            $this->publishes([
                __DIR__ . '/../config/websocket.php' => config_path('websocket.php'),
            ], 'websocket-config');
        }
        
        // Register console commands
        if ($this->app->runningInConsole()) {
            $this->registerCommands();
        }
    }

    /**
     * Register console commands
     */
    protected function registerCommands(): void
    {
        $this->app->singleton('command.websocket.serve', function ($app) {
            return new \Alphavel\WebSocket\Console\ServeCommand($app['websocket.server']);
        });
        
        $this->app->singleton('command.websocket.stats', function ($app) {
            return new \Alphavel\WebSocket\Console\StatsCommand($app['websocket.server']);
        });
    }
}
