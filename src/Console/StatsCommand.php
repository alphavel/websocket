<?php

namespace Alphavel\WebSocket\Console;

use Alphavel\Console\Command;
use Alphavel\WebSocket\WebSocketServer;

/**
 * Show WebSocket server statistics
 */
class StatsCommand extends Command
{
    protected string $signature = 'websocket:stats';
    protected string $description = 'Show WebSocket server statistics';
    
    protected WebSocketServer $server;

    public function __construct(WebSocketServer $server)
    {
        parent::__construct();
        $this->server = $server;
    }

    public function handle(): int
    {
        $connections = $this->server->connections();
        $broadcaster = $this->server->broadcaster();
        
        $this->info('WebSocket Server Statistics');
        $this->line('');
        
        $this->line("Total Connections: " . $connections->count());
        $this->line("Total Channels: " . count($broadcaster->getChannels()));
        $this->line('');
        
        $this->info('Active Channels:');
        foreach ($broadcaster->getChannels() as $channel) {
            $stats = $broadcaster->getChannelStats($channel);
            $this->line("  {$channel} ({$stats['type']}): {$stats['subscribers']} subscribers");
        }
        
        return 0;
    }
}
