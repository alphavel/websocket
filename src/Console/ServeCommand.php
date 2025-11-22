<?php

namespace Alphavel\WebSocket\Console;

use Alphavel\Console\Command;
use Alphavel\WebSocket\WebSocketServer;

/**
 * Start WebSocket server
 */
class ServeCommand extends Command
{
    protected string $signature = 'websocket:serve';
    protected string $description = 'Start the WebSocket server';
    
    protected WebSocketServer $server;

    public function __construct(WebSocketServer $server)
    {
        parent::__construct();
        $this->server = $server;
    }

    public function handle(): int
    {
        $config = config('websocket');
        
        $this->info('Starting Alphavel WebSocket Server...');
        $this->line('');
        $this->line("  Host: {$config['host']}");
        $this->line("  Port: {$config['port']}");
        $this->line("  Workers: {$config['options']['worker_num']}");
        $this->line("  Max Connections: {$config['options']['max_connection']}");
        $this->line('');
        $this->info('Server started successfully!');
        $this->line('Press Ctrl+C to stop');
        
        $this->server->start();
        
        return 0;
    }
}
