<?php

namespace Alphavel\WebSocket\Broadcasting;

use Alphavel\WebSocket\Connection\ConnectionManager;

/**
 * Broadcasting manager for WebSocket channels
 * 
 * Laravel-compatible API for broadcasting events
 * 
 * @package Alphavel\WebSocket\Broadcasting
 */
class BroadcastManager
{
    protected ConnectionManager $connections;
    protected array $config;
    protected ?string $targetChannel = null;
    protected ?int $targetUser = null;
    protected array $except = [];

    public function __construct(array $config, ConnectionManager $connections)
    {
        $this->config = $config;
        $this->connections = $connections;
    }

    /**
     * Broadcast to specific channel
     */
    public function toChannel(string $channel): self
    {
        $instance = clone $this;
        $instance->targetChannel = $channel;
        return $instance;
    }

    /**
     * Broadcast to specific user (all their connections)
     */
    public function toUser(int $userId): self
    {
        $instance = clone $this;
        $instance->targetUser = $userId;
        return $instance;
    }

    /**
     * Broadcast to all except specific connections
     */
    public function except(array $fds): self
    {
        $instance = clone $this;
        $instance->except = $fds;
        return $instance;
    }

    /**
     * Push message to target
     */
    public function push(array $message): int
    {
        $recipients = $this->getRecipients();
        $recipients = array_diff($recipients, $this->except);
        
        $sent = 0;
        $payload = json_encode($message);
        
        foreach ($recipients as $fd) {
            if (swoole_websocket_server_push((int)$fd, $payload)) {
                $sent++;
            }
        }
        
        return $sent;
    }

    /**
     * Broadcast message (alias for push)
     */
    public function broadcast(array $message): int
    {
        return $this->push($message);
    }

    /**
     * Send message to specific connection
     */
    public function send(int $fd, array $message): bool
    {
        return swoole_websocket_server_push($fd, json_encode($message));
    }

    /**
     * Get recipient connections based on target
     */
    protected function getRecipients(): array
    {
        if ($this->targetChannel) {
            return $this->connections->getChannelSubscribers($this->targetChannel);
        }
        
        if ($this->targetUser) {
            return $this->connections->getUserConnections($this->targetUser);
        }
        
        // Broadcast to all
        $all = [];
        foreach ($this->connections->connections as $fd => $_) {
            $all[] = (int)$fd;
        }
        return $all;
    }

    /**
     * Get channel presence (who's online)
     */
    public function getPresence(string $channel): array
    {
        $subscribers = $this->connections->getChannelSubscribers($channel);
        $members = [];
        
        foreach ($subscribers as $fd) {
            $info = $this->connections->getUserInfo($fd);
            if ($info['id']) {
                $members[$info['id']] = $info;
            }
        }
        
        return array_values($members);
    }

    /**
     * Get channel statistics
     */
    public function getChannelStats(string $channel): array
    {
        return $this->connections->getChannelStats($channel);
    }

    /**
     * Get all active channels
     */
    public function getChannels(): array
    {
        return $this->connections->getChannels();
    }
}
