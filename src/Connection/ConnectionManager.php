<?php

namespace Alphavel\WebSocket\Connection;

use Swoole\Table;

/**
 * High-performance connection manager using Swoole Table
 * 
 * Uses lock-free shared memory for O(1) connection lookups
 * 
 * @package Alphavel\WebSocket\Connection
 */
class ConnectionManager
{
    protected Table $connections;
    protected Table $subscriptions;
    protected array $channelIndex = [];

    public function __construct(int $maxConnections = 100000)
    {
        // Connection table: fd => user data
        $this->connections = new Table($maxConnections);
        $this->connections->column('user_id', Table::TYPE_INT, 8);
        $this->connections->column('connected_at', Table::TYPE_INT, 8);
        $this->connections->column('ip', Table::TYPE_STRING, 64);
        $this->connections->create();
        
        // Subscriptions table: fd => channels (JSON)
        $this->subscriptions = new Table($maxConnections);
        $this->subscriptions->column('channels', Table::TYPE_STRING, 4096);
        $this->subscriptions->create();
    }

    /**
     * Add new connection
     */
    public function add(int $fd, array $data): void
    {
        $this->connections->set((string)$fd, [
            'user_id' => $data['user_id'] ?? 0,
            'connected_at' => $data['connected_at'] ?? time(),
            'ip' => $data['ip'] ?? '',
        ]);
        
        $this->subscriptions->set((string)$fd, ['channels' => json_encode([])]);
    }

    /**
     * Remove connection
     */
    public function remove(int $fd): void
    {
        // Remove from channel index
        $channels = $this->getSubscribedChannels($fd);
        foreach ($channels as $channel) {
            if (isset($this->channelIndex[$channel])) {
                unset($this->channelIndex[$channel][$fd]);
                if (empty($this->channelIndex[$channel])) {
                    unset($this->channelIndex[$channel]);
                }
            }
        }
        
        $this->connections->del((string)$fd);
        $this->subscriptions->del((string)$fd);
    }

    /**
     * Subscribe connection to channel
     */
    public function subscribe(int $fd, string $channel): void
    {
        $channels = $this->getSubscribedChannels($fd);
        
        if (!in_array($channel, $channels)) {
            $channels[] = $channel;
            $this->subscriptions->set((string)$fd, ['channels' => json_encode($channels)]);
            
            // Update channel index
            if (!isset($this->channelIndex[$channel])) {
                $this->channelIndex[$channel] = [];
            }
            $this->channelIndex[$channel][$fd] = true;
        }
    }

    /**
     * Unsubscribe connection from channel
     */
    public function unsubscribe(int $fd, string $channel): void
    {
        $channels = $this->getSubscribedChannels($fd);
        $channels = array_filter($channels, fn($ch) => $ch !== $channel);
        
        $this->subscriptions->set((string)$fd, ['channels' => json_encode(array_values($channels))]);
        
        // Update channel index
        if (isset($this->channelIndex[$channel][$fd])) {
            unset($this->channelIndex[$channel][$fd]);
            if (empty($this->channelIndex[$channel])) {
                unset($this->channelIndex[$channel]);
            }
        }
    }

    /**
     * Get subscribed channels for connection
     */
    public function getSubscribedChannels(int $fd): array
    {
        $data = $this->subscriptions->get((string)$fd);
        return $data ? json_decode($data['channels'], true) : [];
    }

    /**
     * Get connections subscribed to channel
     * 
     * O(1) lookup via channel index
     */
    public function getChannelSubscribers(string $channel): array
    {
        return array_keys($this->channelIndex[$channel] ?? []);
    }

    /**
     * Get connection info
     */
    public function get(int $fd): ?array
    {
        $data = $this->connections->get((string)$fd);
        return $data ?: null;
    }

    /**
     * Get user info for presence channels
     */
    public function getUserInfo(int $fd): array
    {
        $data = $this->get($fd);
        
        return [
            'id' => $data['user_id'] ?? null,
            'connected_at' => $data['connected_at'] ?? null,
        ];
    }

    /**
     * Get all connections for user
     */
    public function getUserConnections(int $userId): array
    {
        $connections = [];
        
        foreach ($this->connections as $fd => $data) {
            if ($data['user_id'] === $userId) {
                $connections[] = (int)$fd;
            }
        }
        
        return $connections;
    }

    /**
     * Check if connection exists
     */
    public function exists(int $fd): bool
    {
        return $this->connections->exist((string)$fd);
    }

    /**
     * Get total connections count
     */
    public function count(): int
    {
        return $this->connections->count();
    }

    /**
     * Cleanup stale connections
     */
    public function cleanup(): void
    {
        $now = time();
        $timeout = 600; // 10 minutes
        
        foreach ($this->connections as $fd => $data) {
            if (($now - $data['connected_at']) > $timeout) {
                $this->remove((int)$fd);
            }
        }
    }

    /**
     * Get all channels
     */
    public function getChannels(): array
    {
        return array_keys($this->channelIndex);
    }

    /**
     * Get channel stats
     */
    public function getChannelStats(string $channel): array
    {
        $subscribers = $this->getChannelSubscribers($channel);
        
        return [
            'name' => $channel,
            'subscribers' => count($subscribers),
            'type' => $this->getChannelType($channel),
        ];
    }

    /**
     * Get channel type
     */
    protected function getChannelType(string $channel): string
    {
        if (str_starts_with($channel, 'presence-')) {
            return 'presence';
        }
        if (str_starts_with($channel, 'private-')) {
            return 'private';
        }
        return 'public';
    }
}
