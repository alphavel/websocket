<?php

namespace Alphavel\WebSocket\Facades;

use Alphavel\Facade;

/**
 * WebSocket facade for easy broadcasting
 * 
 * @method static \Alphavel\WebSocket\Broadcasting\BroadcastManager toChannel(string $channel)
 * @method static \Alphavel\WebSocket\Broadcasting\BroadcastManager toUser(int $userId)
 * @method static \Alphavel\WebSocket\Broadcasting\BroadcastManager except(array $fds)
 * @method static int push(array $message)
 * @method static int broadcast(array $message)
 * @method static bool send(int $fd, array $message)
 * @method static array getPresence(string $channel)
 * @method static array getChannelStats(string $channel)
 * @method static array getChannels()
 * 
 * @package Alphavel\WebSocket\Facades
 */
class WebSocket extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return 'websocket';
    }
}
